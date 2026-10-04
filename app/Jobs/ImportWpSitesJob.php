<?php

namespace App\Jobs;

use App\Models\WpSite;
use App\Models\WpSiteCategory;
use App\Models\WpSiteImport;
use App\Support\ApiUrlHelper;
use App\Support\SafeApiUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class ImportWpSitesJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public const QUEUE = 'import_wp_sites';

    public function __construct(
        public WpSiteImport $wpSiteImport
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function displayName(): string
    {
        return 'Import WP Sites';
    }

    public function handle(): void
    {
        $import = $this->wpSiteImport;
        $import->update(['status' => 'processing']);

        $path = Storage::path($import->filename);
        if (!file_exists($path)) {
            $import->update(['status' => 'failed']);
            return;
        }

        $imported = 0;
        $skipped = 0;

        try {
            $rows = $this->parseFile($path);
            $import->update(['total_rows' => count($rows)]);

            foreach ($rows as $row) {
                $domain = $row['domain'] ?? $row[0] ?? null;
                $apiUrl = $row['api_url'] ?? $row[1] ?? null;
                if (!$domain || !$apiUrl) {
                    $skipped++;
                    continue;
                }

                $apiUrl = ApiUrlHelper::restApiBase((string) $apiUrl);
                if (SafeApiUrl::validate($apiUrl) !== null) {
                    $skipped++;
                    continue;
                }

                $normalized = WpSite::normalizeDomain((string) $domain);
                $attrs = [
                    'domain' => $normalized,
                    'domain_normalized' => $normalized,
                    'api_url' => $apiUrl,
                    'api_key' => $row['api_key'] ?? $row[2] ?? null,
                    'status' => 'inactive',
                    'wp_site_import_id' => $import->id,
                ];
                $categoryName = $this->rowCategory($row);
                if ($categoryName !== null) {
                    $category = WpSiteCategory::findOrCreateByName($categoryName);
                    if ($category) {
                        $attrs['wp_site_category_id'] = $category->id;
                    }
                }
                $siteModel = WpSite::updateOrCreate(
                    ['domain_normalized' => $normalized, 'user_id' => $import->user_id],
                    $attrs
                );
                WpSiteHealthCheckJob::dispatch($siteModel);
                $imported++;
            }

            $import->update([
                'status' => 'completed',
                'imported_count' => $imported,
                'skipped_count' => $skipped,
            ]);

            // Delete file from storage after successful import to save space
            if ($import->filename && Storage::exists($import->filename)) {
                Storage::delete($import->filename);
            }
        } catch (\Exception $e) {
            $import->update(['status' => 'failed']);
            throw $e;
        }
    }

    private function parseFile(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $rows = [];

        if ($ext === 'xlsx' || $ext === 'xls') {
            $reader = new XlsxReader();
            $reader->open($path);

            $headerMap = null;
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $values = array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : '', array_values($row->toArray()));
                    if ($headerMap === null && $this->looksLikeHeader($values)) {
                        $headerMap = $this->headerMap($values);
                        continue;
                    }
                    $mapped = $headerMap ? $this->mapByHeader($values, $headerMap) : [
                        'domain' => $values[0] ?? null,
                        'api_url' => $values[1] ?? null,
                        'api_key' => $values[2] ?? null,
                        'category' => $values[3] ?? null,
                    ];
                    if (($mapped['domain'] ?? '') === '' && ($mapped['api_url'] ?? '') === '') {
                        continue;
                    }
                    $rows[] = $mapped;
                }
                break;
            }

            $reader->close();
        } elseif ($ext === 'csv' || $ext === 'txt') {
            $handle = fopen($path, 'r');
            $headers = fgetcsv($handle);
            $headerMap = is_array($headers) ? $this->headerMap($headers) : [];
            while (($data = fgetcsv($handle)) !== false) {
                $rows[] = $headerMap !== []
                    ? $this->mapByHeader($data, $headerMap)
                    : [0 => $data[0] ?? '', 1 => $data[1] ?? '', 2 => $data[2] ?? null, 'category' => $data[3] ?? null];
            }
            fclose($handle);
        }

        return $rows;
    }

    private function rowCategory(array $row): ?string
    {
        foreach (['category', 'wp_site_category'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $positional = trim((string) ($row[3] ?? ''));

        return $positional !== '' ? $positional : null;
    }

    private function looksLikeHeader(array $values): bool
    {
        $first = strtolower((string) ($values[0] ?? ''));
        $second = strtolower((string) ($values[1] ?? ''));

        return $first === 'domain' && in_array($second, ['api_url', 'api url', 'apiurl'], true);
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return array<string, int>
     */
    private function headerMap(array $headers): array
    {
        $map = [];
        foreach (array_values($headers) as $i => $header) {
            $key = strtolower(trim((string) $header));
            $key = str_replace([' ', '-'], '_', $key);
            if ($key !== '') {
                $map[$key] = $i;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, mixed>  $values
     * @param  array<string, int>  $headerMap
     * @return array<string, mixed>
     */
    private function mapByHeader(array $values, array $headerMap): array
    {
        $get = function (array $aliases) use ($values, $headerMap) {
            foreach ($aliases as $alias) {
                if (isset($headerMap[$alias])) {
                    return trim((string) ($values[$headerMap[$alias]] ?? ''));
                }
            }

            return null;
        };

        return [
            'domain' => $get(['domain']) ?: ($values[0] ?? null),
            'api_url' => $get(['api_url', 'apiurl']) ?: ($values[1] ?? null),
            'api_key' => $get(['api_key', 'apikey']) ?: ($values[2] ?? null),
            'category' => $get(['category', 'wp_site_category']),
        ];
    }
}
