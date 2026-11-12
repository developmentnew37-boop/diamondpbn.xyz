<?php

namespace App\Support;

class ImportSiteRow
{
    /**
     * Normalize spreadsheet headers so "API Key" / "API URL" map to api_key / api_url.
     *
     * @param  array<int|string, mixed>  $row
     * @return array<int|string, mixed>
     */
    public static function normalize(array $row): array
    {
        $out = [];
        foreach ($row as $key => $value) {
            if (is_int($key)) {
                $out[$key] = $value;
                continue;
            }

            $normalized = strtolower(trim((string) $key));
            $normalized = preg_replace('/\s+/', '_', $normalized) ?? $normalized;
            $out[$normalized] = $value;
        }

        return $out;
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    public static function domain(array $row): ?string
    {
        $row = self::normalize($row);
        $value = $row['domain'] ?? $row[0] ?? null;
        $value = is_string($value) || is_numeric($value) ? trim((string) $value) : '';

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    public static function apiUrl(array $row): ?string
    {
        $row = self::normalize($row);
        $value = $row['api_url'] ?? $row[1] ?? null;
        $value = is_string($value) || is_numeric($value) ? trim((string) $value) : '';

        return $value !== '' ? $value : null;
    }

    /**
     * Prefer a named api_key column. Positional column 3 is ignored when it looks like status.
     *
     * @param  array<int|string, mixed>  $row
     */
    public static function apiKey(array $row): ?string
    {
        $row = self::normalize($row);

        if (array_key_exists('api_key', $row)) {
            $named = trim((string) ($row['api_key'] ?? ''));

            return $named !== '' ? $named : null;
        }

        if (! array_key_exists(2, $row)) {
            return null;
        }

        $positional = trim((string) ($row[2] ?? ''));
        if ($positional === '') {
            return null;
        }

        if (in_array(strtolower($positional), ['active', 'inactive', 'error', 'status'], true)) {
            return null;
        }

        return $positional;
    }
}
