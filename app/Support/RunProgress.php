<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class RunProgress
{
    public static function markRetrying(string $type, int $id): bool
    {
        return Cache::add(self::retryKey($type, $id), now()->toDateTimeString(), 1800);
    }

    public static function isRetrying(string $type, int $id): bool
    {
        return Cache::has(self::retryKey($type, $id));
    }

    public static function retryStartedAt(string $type, int $id): ?string
    {
        $value = Cache::get(self::retryKey($type, $id));

        return is_string($value) ? $value : null;
    }

    public static function clearRetrying(string $type, int $id): void
    {
        Cache::forget(self::retryKey($type, $id));
    }

    public static function setPublishing(string $type, int $id, int $queued): void
    {
        Cache::put(self::publishKey($type, $id), max(0, $queued), 1800);
    }

    public static function markPublishing(string $type, int $id, int $queued): void
    {
        $key = self::publishKey($type, $id);
        Cache::put($key, (int) Cache::get($key, 0) + max(0, $queued), 1800);
    }

    public static function publishingCount(string $type, int $id): int
    {
        return (int) Cache::get(self::publishKey($type, $id), 0);
    }

    public static function clearPublishing(string $type, int $id): void
    {
        Cache::forget(self::publishKey($type, $id));
    }

    private static function retryKey(string $type, int $id): string
    {
        return 'run:'.$type.':'.$id.':retrying';
    }

    private static function publishKey(string $type, int $id): string
    {
        return 'run:'.$type.':'.$id.':publishing';
    }
}
