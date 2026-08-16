<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class BatchDomainChunk extends Model
{
    protected $table = 'batch_domain_chunks';

    protected $fillable = [
        'batch_id',
        'domain_id',
        'chunk_index',
        'links_payload',
        'links_count',
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

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $chunk) {
            if (! Schema::hasColumn($chunk->getTable(), 'links_count')) {
                return;
            }

            $chunk->links_count = count(is_array($chunk->links_payload) ? $chunk->links_payload : []);
        });
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

    /** Whether a single link result row counts as failed (matches batch show / errors UI). */
    public static function isFailedLinkResult(?array $result): bool
    {
        if ($result === null) {
            return true;
        }

        $status = $result['status'] ?? '';

        return $status !== 'success' && $status !== 'completed';
    }

    /**
     * Indices of failed links in this chunk's payloads.
     *
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

        // Counters say failures exist but payloads did not match (e.g. unknown status rows).
        if ($indices === [] && $failedCount > 0 && $linkCount > 0) {
            return range(0, $linkCount - 1);
        }

        return $indices;
    }
}
