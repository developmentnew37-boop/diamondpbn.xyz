<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WpSiteImport extends Model
{
    protected $fillable = [
        'user_id',
        'filename',
        'total_rows',
        'imported_count',
        'skipped_count',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wpSites(): HasMany
    {
        return $this->hasMany(WpSite::class);
    }
}
