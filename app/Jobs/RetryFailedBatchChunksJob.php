<?php

namespace App\Jobs;

use App\Models\Batch;
use App\Models\BatchDomainChunk;
use App\Support\PublishPendingChunks;
use App\Support\RetryFailedChunks;
use App\Support\RunProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryFailedBatchChunksJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'batch_links';

    public int $timeout = 1800;

    public int $tries = 1;

    public int $uniqueFor = 1800;

    public function __construct(public int $batchId)
    {
        $this->onQueue(self::QUEUE);
    }

    public function displayName(): string
    {
        return "Retry failed batch {$this->batchId} chunks";
    }

    public function uniqueId(): string
    {
        return 'retry-failed-batch-'.$this->batchId;
    }

    public function handle(): void
    {
        $batch = Batch::find($this->batchId);
        if (! $batch || in_array($batch->status, ['paused', 'deleting'], true)) {
            RunProgress::clearRetrying('batch', $this->batchId);

            return;
        }

        $total = RetryFailedChunks::retryRun(BatchDomainChunk::class, 'batch_id', $this->batchId);
        $queued = 0;
        $batch = $batch->fresh();
        if ($total > 0 && $batch && ! in_array($batch->status, ['paused', 'deleting'], true)) {
            $queued = PublishPendingChunks::forBatch($batch);
        }

        $batch?->recalculateCounters(true);
        RunProgress::clearRetrying('batch', $this->batchId);

        Log::info('Retry failed batch chunks finished', [
            'batch_id' => $this->batchId,
            'retried' => $total,
            'published' => $queued,
        ]);
    }

    public function failed(?\Throwable $e): void
    {
        RunProgress::clearRetrying('batch', $this->batchId);
    }
}
