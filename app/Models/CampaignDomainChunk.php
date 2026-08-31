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

    public static function isFailedLinkResult(?array $result): bool
    {
        if ($result === null) {
            return true;
        }

        $status = $result['status'] ?? '';

        return $status !== 'success' && $status !== 'completed';
    }

    /**
     * @return array<int, int>
     */
    public function failedLinkIndices(): array
    {
        $linksPayload = $this->links_payload ?? [];
        $resultsPayload = $this->results_payload ?? [];
        $indices = [];

        foreach ($linksPayload as $i => $_) {
            if (self::isFailedLinkResult($resultsPayload[$i] ?? null)) {
                $indices[] = $i;
            }
        }

        $failedCount = (int) ($this->failed_count ?? 0);
        $linkCount = count($linksPayload);

        if ($indices === [] && $failedCount > 0 && $linkCount > 0) {
            return range(0, $linkCount - 1);
        }

        return $indices;
    }
}
