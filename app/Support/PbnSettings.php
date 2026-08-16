<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * PBN app settings (API timeout, rate limit delay).
 * Stored in cache; falls back to config/env when not set.
 */
class PbnSettings
{
    private const CACHE_KEY = 'pbn_settings';

    private const DEFAULTS = [
        'api_timeout_seconds' => 30,
        'delete_timeout_seconds' => 900,
        'link_delay_seconds' => 5,
        'show_hidden_links' => false,
    ];

    public static function all(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return array_merge(self::DEFAULTS, $cached);
        }
        $fromConfig = config('services.pbn', []);
        return array_merge(self::DEFAULTS, [
            'api_timeout_seconds' => $fromConfig['api_timeout_seconds'] ?? self::DEFAULTS['api_timeout_seconds'],
            'delete_timeout_seconds' => $fromConfig['delete_timeout_seconds'] ?? self::DEFAULTS['delete_timeout_seconds'],
            'link_delay_seconds' => (int) ($fromConfig['link_delay_seconds'] ?? self::DEFAULTS['link_delay_seconds']),
        ]);
    }

    public static function getApiTimeoutSeconds(): int
    {
        $v = (int) (self::all()['api_timeout_seconds'] ?? self::DEFAULTS['api_timeout_seconds']);
        return max(10, min(600, $v));
    }

    public static function getDeleteTimeoutSeconds(): int
    {
        $v = (int) (self::all()['delete_timeout_seconds'] ?? self::DEFAULTS['delete_timeout_seconds']);
        return max(60, min(1800, $v));
    }

    public static function getLinkDelaySeconds(): int
    {
        $v = (int) (self::all()['link_delay_seconds'] ?? self::DEFAULTS['link_delay_seconds']);
        return max(0, min(300, $v));
    }

    public static function getShowHiddenLinks(): bool
    {
        return (bool) (self::all()['show_hidden_links'] ?? self::DEFAULTS['show_hidden_links']);
    }

    public static function setShowHiddenLinks(bool $value): void
    {
        $current = self::all();
        $current['show_hidden_links'] = $value;
        Cache::forever(self::CACHE_KEY, $current);
    }

    public static function set(array $settings): void
    {
        $allowed = [
            'api_timeout_seconds' => null,
            'delete_timeout_seconds' => null,
            'link_delay_seconds' => null,
            'show_hidden_links' => null,
        ];
        $merged = array_merge(self::all(), array_intersect_key($settings, $allowed));
        Cache::forever(self::CACHE_KEY, [
            'api_timeout_seconds' => (int) $merged['api_timeout_seconds'],
            'delete_timeout_seconds' => (int) $merged['delete_timeout_seconds'],
            'link_delay_seconds' => (int) $merged['link_delay_seconds'],
            'show_hidden_links' => (bool) ($merged['show_hidden_links'] ?? false),
        ]);
    }
}
