<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WpBatch extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'status',
        'total_links',
        'total_domains',
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

    public function wpLinks(): HasMany
    {
        return $this->hasMany(WpLink::class);
    }

    public function wpBatchSiteChunks(): HasMany
    {
        return $this->hasMany(WpBatchSiteChunk::class);
    }

    public function wpSites(): BelongsToMany
    {
        return $this->belongsToMany(WpSite::class, 'wp_batch_site_chunks', 'wp_batch_id', 'wp_site_id')
            ->distinct();
    }

    public function totalExpectedPosts(): int
    {
        return ($this->total_links ?? 0) * ($this->total_domains ?? 0);
    }

    /**
     * @return array{processed: int, total: int, percent: int}
     */
    public function displayProgress(?int $chunksSuccessSum = null, ?int $chunksFailedSum = null): array
    {
        $expected = $this->totalExpectedPosts();
        $total = max(1, $expected);

        $processed = (int) ($this->processed_count ?? 0);
        if ($processed === 0 && $chunksSuccessSum !== null && $chunksFailedSum !== null) {
            $processed = (int) $chunksSuccessSum + (int) $chunksFailedSum;
        }

        if ($expected > 0) {
            $processed = min($processed, $expected);
        }

        $percent = min(100, (int) round(($processed / $total) * 100));

        return [
            'processed' => $processed,
            'total' => $expected > 0 ? $expected : $total,
            'percent' => $percent,
        ];
    }

    public function recalculateCounters(): self
    {
        $aggregates = $this->wpBatchSiteChunks()
            ->selectRaw('COALESCE(SUM(success_count), 0) as success_total, COALESCE(SUM(failed_count), 0) as failed_total')
            ->first();

        $success = (int) ($aggregates->success_total ?? 0);
        $failed = (int) ($aggregates->failed_total ?? 0);
        $processedRaw = $success + $failed;
        $totalExpected = $this->totalExpectedPosts();
        $processed = ($totalExpected > 0) ? min($processedRaw, $totalExpected) : $processedRaw;

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
