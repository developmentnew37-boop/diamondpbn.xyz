<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class RunRemoteDelete
{
    /**
     * Domain or site ids that may already have this run's links.
     * A chunk still in flight counts, because success_count is written after the HTTP call.
     *
     * @param  class-string  $chunkClass
     * @return list<int>
     */
    public static function ownerIds(string $chunkClass, string $parentKey, string $ownerKey, int $parentId): array
    {
        return self::needsRemote($chunkClass::query()->where($parentKey, $parentId))
            ->distinct()
            ->pluck($ownerKey)
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  class-string  $chunkClass
     */
    public static function ownerNeedsRemote(string $chunkClass, string $parentKey, string $ownerKey, int $parentId, int $ownerId): bool
    {
        return self::needsRemote(
            $chunkClass::query()->where($parentKey, $parentId)->where($ownerKey, $ownerId)
        )->exists();
    }

    public static function applyListView(Builder $query, string $view): Builder
    {
        if ($view === 'deleting') {
            return $query->where('status', 'deleting');
        }

        return $query->where('status', '!=', 'deleting');
    }

    private static function needsRemote(Builder $query): Builder
    {
        return $query->where(function (Builder $inner) {
            $inner->where('success_count', '>', 0)
                ->orWhere('status', 'processing')
                ->orWhereNotNull('sent_at');
        });
    }
}
