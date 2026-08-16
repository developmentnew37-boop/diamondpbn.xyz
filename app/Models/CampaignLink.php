<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'user_id',
        'url',
        'keyword',
        'no_follow',
        'link_type',
        'extra_data',
    ];

    protected $casts = [
        'no_follow' => 'boolean',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
