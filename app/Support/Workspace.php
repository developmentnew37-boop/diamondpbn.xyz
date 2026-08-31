<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class Workspace
{
    public static function ownerId(): int
    {
        $id = User::query()
            ->whereIn('role', ['superadmin', 'admin'])
            ->orderBy('id')
            ->value('id');

        return (int) ($id ?: auth()->id());
    }

    public static function isSuperAdmin(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public static function scopeOwnPosting(Builder $query): Builder
    {
        if (self::isSuperAdmin()) {
            return $query;
        }

        return $query->where('user_id', auth()->id());
    }

    public static function canManageRun(?int $creatorUserId): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return (int) $creatorUserId === (int) $user->id;
    }

    public static function assertCanManageRun(?int $creatorUserId): void
    {
        if (! self::canManageRun($creatorUserId)) {
            abort(403, 'You can only manage runs you created.');
        }
    }

    public static function assertInventoryOwned(?int $userId): void
    {
        if ((int) $userId !== self::ownerId()) {
            abort(403);
        }
    }
}
