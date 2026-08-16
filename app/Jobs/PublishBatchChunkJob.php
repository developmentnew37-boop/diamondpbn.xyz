<?php

namespace App\Jobs;

use App\Jobs\Concerns\HandlesChunkPublishFailure;
use App\Jobs\Concerns\NormalizesChunkApiCounts;
use App\Models\BatchDomainChunk;
use App\Services\PbnApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishBatchChunkJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    use HandlesChunkPublishFailure;

    use NormalizesChunkApiCounts;

    public const QUEUE = 'batch_links';

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public BatchDomainChunk $chunk)
    {

        $this->onQueue(self::QUEUE);

    }

    public function displayName(): string
    {

        return 'Publish batch chunk (batch '.$this->chunk->batch_id.', domain '.$this->chunk->domain_id.', chunk '.$this->chunk->chunk_index.')';

    }

    /**
     * Unique name so the same chunk is never published twice.
     */
    public function uniqueId(): string
    {

        return 'publish-batch-chunk-'.$this->chunk->batch_id.'-'.$this->chunk->domain_id.'-'.$this->chunk->chunk_index;

    }

    public function handle(PbnApiService $api): void
    {

        $chunk = $this->chunk->fresh(['domain', 'batch']);

        if (! $chunk || ! $chunk->domain || ! $chunk->batch) {

            return;

        }

        if (! $this->shouldProcessChunk($chunk)) {

            return;

        }

        $chunk->update([

            'status' => BatchDomainChunk::STATUS_PROCESSING,

            'sent_at' => now(),

        ]);

        try {

            $response = $api->postChunk($chunk->domain, $chunk);

        } catch (\Throwable $e) {

            if ($this->attempts() >= $this->tries) {

                $this->markBatchChunkFailed($chunk, $e->getMessage());

            } else {

                $chunk->update([

                    'status' => BatchDomainChunk::STATUS_PENDING,

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

        $status = $failedCount > 0 ? BatchDomainChunk::STATUS_PARTIAL : BatchDomainChunk::STATUS_COMPLETED;

        $chunk->update([

            'results_payload' => $resultsPayload,

            'success_count' => $successCount,

            'failed_count' => $failedCount,

            'status' => $status,

            'completed_at' => now(),

            'error_message' => null,

        ]);

        $batch = $chunk->batch;

        $batch->recalculateCounters();

        if ($batch->status === 'processing') {

            $batch->update(['started_at' => $batch->started_at ?? now()]);

        }

    }

    public function failed(?\Throwable $e): void
    {

        $chunk = $this->chunk->fresh(['batch']);

        if (! $chunk || ! $chunk->batch) {

            return;

        }

        $this->markBatchChunkFailed($chunk, $e?->getMessage() ?? 'Publish failed after retries');

        Log::warning('PublishBatchChunkJob exhausted retries', [

            'batch_id' => $chunk->batch_id,

            'domain_id' => $chunk->domain_id,

            'chunk_index' => $chunk->chunk_index,

            'message' => $e?->getMessage(),

        ]);

    }

    private function shouldProcessChunk(BatchDomainChunk $chunk): bool
    {

        if ($chunk->status === BatchDomainChunk::STATUS_PENDING) {

            return true;

        }

        if ($chunk->status === BatchDomainChunk::STATUS_PROCESSING) {

            $stale = $chunk->sent_at && $chunk->sent_at->lt(now()->subMinutes(5));

            return $stale || $this->attempts() > 1;

        }

        return false;

    }

}
