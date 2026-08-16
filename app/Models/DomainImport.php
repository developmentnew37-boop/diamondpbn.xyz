<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'filename',
        'type',
        'total_rows',
        'imported_count',
        'skipped_count',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function domains(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function campaignDomains(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CampaignDomain::class);
    }
}
