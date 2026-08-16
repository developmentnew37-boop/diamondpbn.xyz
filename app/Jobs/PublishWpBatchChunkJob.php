<?php

namespace App\Jobs;

use App\Jobs\Concerns\HandlesWpChunkPublishFailure;
use App\Jobs\Concerns\NormalizesChunkApiCounts;
use App\Models\WpBatchSiteChunk;
use App\Services\WpApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishWpBatchChunkJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    use HandlesWpChunkPublishFailure;

    use NormalizesChunkApiCounts;

    public const QUEUE = 'wp_batch_links';

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public WpBatchSiteChunk $chunk)
    {

        $this->onQueue(self::QUEUE);

    }

    public function displayName(): string
    {

        return 'Publish WP batch chunk (batch '.$this->chunk->wp_batch_id.', site '.$this->chunk->wp_site_id.', chunk '.$this->chunk->chunk_index.')';

    }

    /**
     * Unique name so the same chunk is never published twice.
     */
    public function uniqueId(): string
    {

        return 'publish-wp-batch-chunk-'.$this->chunk->wp_batch_id.'-'.$this->chunk->wp_site_id.'-'.$this->chunk->chunk_index;

    }

    public function handle(WpApiService $api): void
    {

        $chunk = $this->chunk->fresh(['wpSite', 'wpBatch']);

        if (! $chunk || ! $chunk->wpSite || ! $chunk->wpBatch) {

            return;

        }

        if (! $this->shouldProcessChunk($chunk)) {

            return;

        }

        $chunk->update([

            'status' => WpBatchSiteChunk::STATUS_PROCESSING,

            'sent_at' => now(),

        ]);

        try {

            $response = $api->postChunk($chunk->wpSite, $chunk);

        } catch (\Throwable $e) {

            if ($this->attempts() >= $this->tries) {

                $this->markWpBatchChunkFailed($chunk, $e->getMessage());

            } else {

                $chunk->update([

                    'status' => WpBatchSiteChunk::STATUS_PENDING,

                    'error_message' => $e->getMessage(),

                ]);

            }

            throw $e;
        }

        $responsePayload = $response['payload'] ?? [];

        $linkCount = count($chunk->links_payload ?? []);

        [$successCount, $failedCount] = $this->resolveChunkCounts($responsePayload, $linkCount, $response);

        $resultsPayload = [];

        foreach ($responsePayload as $i => $item) {

            $resultsPayload[] = [

                'status' => $item['status'] ?? 'unknown',

                'remote_post_id' => $item['link_id'] ?? null,

                'error' => ($item['status'] ?? '') === 'failed' ? ($item['error'] ?? 'Failed') : null,

            ];

        }

        $status = $failedCount > 0 ? WpBatchSiteChunk::STATUS_PARTIAL : WpBatchSiteChunk::STATUS_COMPLETED;

        $chunk->update([

            'results_payload' => $resultsPayload,

            'success_count' => $successCount,

            'failed_count' => $failedCount,

            'status' => $status,

            'completed_at' => now(),

            'error_message' => null,

        ]);

        $batch = $chunk->wpBatch;

        $batch->recalculateCounters();

        if ($batch->status === 'processing') {

            $batch->update(['started_at' => $batch->started_at ?? now()]);

        }

    }

    public function failed(?\Throwable $e): void
    {

        $chunk = $this->chunk->fresh(['wpBatch']);

        if (! $chunk || ! $chunk->wpBatch) {

            return;

        }

        $this->markWpBatchChunkFailed($chunk, $e?->getMessage() ?? 'Publish failed after retries');

        Log::warning('PublishWpBatchChunkJob exhausted retries', [

            'wp_batch_id' => $chunk->wp_batch_id,

            'wp_site_id' => $chunk->wp_site_id,

            'chunk_index' => $chunk->chunk_index,

            'message' => $e?->getMessage(),

        ]);

    }

    private function shouldProcessChunk(WpBatchSiteChunk $chunk): bool
    {

        if ($chunk->status === WpBatchSiteChunk::STATUS_PENDING) {

            return true;

        }

        if ($chunk->status === WpBatchSiteChunk::STATUS_PROCESSING) {

            $stale = $chunk->sent_at && $chunk->sent_at->lt(now()->subMinutes(5));

            return $stale || $this->attempts() > 1;

        }

        return false;

    }

}
