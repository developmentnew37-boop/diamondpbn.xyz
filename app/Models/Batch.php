<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Batch extends Model
{
    use HasFactory;

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

    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }

    public function batchDomainChunks(): HasMany
    {
        return $this->hasMany(BatchDomainChunk::class);
    }

    public function domains(): BelongsToMany
    {
        return $this->belongsToMany(Domain::class, 'batch_domain_chunks', 'batch_id', 'domain_id')
            ->distinct();
    }

    public function totalExpectedPosts(): int
    {
        return ($this->total_links ?? 0) * ($this->total_domains ?? 0);
    }

    /**
     * Progress for list UI: numerator/denominator capped to expected link×domain posts.
     * Retries can inflate raw chunk sums beyond the original batch size.
     *
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

    public function isComplete(): bool
    {
        return $this->processed_count >= $this->totalExpectedPosts() && $this->totalExpectedPosts() > 0;
    }

    /** Re-sync batch totals from chunk success/failed counts (source of truth). */
    public function recalculateCounters(): self
    {
        $aggregates = $this->batchDomainChunks()
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
