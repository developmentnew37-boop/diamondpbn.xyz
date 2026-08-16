<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'status',
        'total_links',
        'total_domains',
        'links_per_domain',
        'total_distributed_links',
        'processed_count',
        'success_count',
        'failed_count',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(CampaignLink::class);
    }

    public function campaignDomainChunks(): HasMany
    {
        return $this->hasMany(CampaignDomainChunk::class);
    }

    public function campaignDomains(): BelongsToMany
    {
        return $this->belongsToMany(CampaignDomain::class, 'campaign_domain_chunks', 'campaign_id', 'campaign_domain_id')
            ->distinct();
    }

    public function isComplete(): bool
    {
        return $this->processed_count >= $this->total_distributed_links && $this->total_distributed_links > 0;
    }

    public function recalculateCounters(): self
    {
        $aggregates = $this->campaignDomainChunks()
            ->selectRaw('COALESCE(SUM(success_count), 0) as success_total, COALESCE(SUM(failed_count), 0) as failed_total')
            ->first();

        $success = (int) ($aggregates->success_total ?? 0);
        $failed = (int) ($aggregates->failed_total ?? 0);
        $processed = $success + $failed;
        $totalExpected = $this->total_distributed_links ?? 0;

        $updates = [
            'processed_count' => $processed,
            'success_count' => $success,
            'failed_count' => $failed,
        ];

        $terminalStatuses = ['semi_deleted', 'delete_failed', 'deleting'];
        if (! in_array($this->status, $terminalStatuses, true)) {
            if ($totalExpected > 0 && $processed >= $totalExpected) {
                $updates['status'] = $failed > 0 ? 'partial' : 'completed';
                if (! $this->completed_at) {
                    $updates['completed_at'] = now();
                }
            } elseif ($processed > 0) {
                $updates['status'] = 'processing';
            }
        }

        $this->update($updates);

        return $this->fresh();
    }
}
