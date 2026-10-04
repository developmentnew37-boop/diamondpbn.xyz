<?php

namespace App\Models;

use App\Support\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;

class WpSiteCategory extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'name_normalized',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wpSites(): HasMany
    {
        return $this->hasMany(WpSite::class);
    }

    public static function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    public static function catalog()
    {
        return static::query()->orderBy('name');
    }

    public static function findOrCreateByName(string $name): ?self
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $normalized = mb_substr(self::normalizeName($name), 0, 80);
        $existing = static::query()->where('name_normalized', $normalized)->first();
        if ($existing) {
            return $existing;
        }

        try {
            return static::create([
                'user_id' => Workspace::ownerId(),
                'name' => mb_substr($name, 0, 80),
                'name_normalized' => $normalized,
            ]);
        } catch (QueryException $e) {
            return static::query()->where('name_normalized', $normalized)->first();
        }
    }
}
