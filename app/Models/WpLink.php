<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WpLink extends Model
{
    protected $fillable = [
        'wp_batch_id',
        'user_id',
        'url',
        'keyword',
        'no_follow',
        'link_type',
        'extra_data',
    ];

    protected $casts = [
        'no_follow' => 'boolean',
        'extra_data' => 'array',
    ];

    public function wpBatch(): BelongsTo
    {
        return $this->belongsTo(WpBatch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
