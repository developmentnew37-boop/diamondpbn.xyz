<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class WpBatchSiteChunk extends Model
{
    protected $fillable = [
        'wp_batch_id',
        'wp_site_id',
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

    public const CHUNK_SIZE = 25;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PARTIAL = 'partial';

    public function wpBatch(): BelongsTo
    {
        return $this->belongsTo(WpBatch::class);
    }

    public function wpSite(): BelongsTo
    {
        return $this->belongsTo(WpSite::class);
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
