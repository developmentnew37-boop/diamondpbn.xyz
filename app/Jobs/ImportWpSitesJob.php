<?php

namespace App\Jobs;

use App\Models\WpSite;
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

                $apiUrl = ApiUrlHelper::normalizeForStorage((string) $apiUrl);
                if (SafeApiUrl::validate($apiUrl) !== null) {
                    $skipped++;
                    continue;
                }

                $normalized = WpSite::normalizeDomain((string) $domain);
                $siteModel = WpSite::updateOrCreate(
                    ['domain_normalized' => $normalized, 'user_id' => $import->user_id],
                    [
                        'domain' => $normalized,
                        'domain_normalized' => $normalized,
                        'api_url' => $apiUrl,
                        'api_key' => $row['api_key'] ?? $row[2] ?? null,
                        'status' => 'inactive',
                        'wp_site_import_id' => $import->id,
                    ]
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

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $values = array_values($row->toArray());
                    $domain = isset($values[0]) ? trim((string) $values[0]) : null;
                    $apiUrl = isset($values[1]) ? trim((string) $values[1]) : null;
                    if (!$domain && !$apiUrl) {
                        continue;
                    }
                    if (strtolower($domain ?? '') === 'domain' && (strtolower($apiUrl ?? '') === 'api_url' || strtolower($apiUrl ?? '') === 'api url')) {
                        continue;
                    }
                    $rows[] = [
                        'domain' => $domain,
                        'api_url' => $apiUrl,
                        'api_key' => isset($values[2]) ? trim((string) $values[2]) : null,
                    ];
                }
                break;
            }

            $reader->close();
        } elseif ($ext === 'csv' || $ext === 'txt') {
            $handle = fopen($path, 'r');
            $headers = fgetcsv($handle);
            while (($data = fgetcsv($handle)) !== false) {
                $combined = is_array($headers) && count($headers) === count($data)
                    ? array_combine($headers, $data)
                    : $data;
                $rows[] = is_array($combined) ? $combined : [0 => $data[0] ?? '', 1 => $data[1] ?? '', 2 => $data[2] ?? null];
            }
            fclose($handle);
        }

        return $rows;
    }
}
