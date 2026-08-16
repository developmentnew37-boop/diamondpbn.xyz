<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignDomainChunk extends Model
{
    protected $table = 'campaign_domain_chunks';

    protected $fillable = [
        'campaign_id',
        'campaign_domain_id',
        'chunk_index',
        'links_payload',
        'results_payload',
        'status',
        'attempts',
        'success_count',
        'failed_count',
        'sent_at',
        'completed_at',
        'error_message',
    ];

    protected $casts = [
        'links_payload' => 'array',
        'results_payload' => 'array',
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const CHUNK_SIZE = 100;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PARTIAL = 'partial';

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function campaignDomain(): BelongsTo
    {
        return $this->belongsTo(CampaignDomain::class);
    }

    /** Links in this chunk (decoded from links_payload) */
    public function getLinksAttribute(): array
    {
        return $this->links_payload ?? [];
    }

    /** Results from API response (decoded from results_payload) */
    public function getResultsAttribute(): array
    {
        return $this->results_payload ?? [];
    }
}
