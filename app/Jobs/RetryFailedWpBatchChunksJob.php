<?php

namespace App\Jobs;

use App\Models\WpBatch;
use App\Models\WpBatchSiteChunk;
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

class RetryFailedWpBatchChunksJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'wp_batch_links';

    public int $timeout = 1800;

    public int $tries = 1;

    public int $uniqueFor = 1800;

    public function __construct(public int $wpBatchId)
    {
        $this->onQueue(self::QUEUE);
    }

    public function displayName(): string
    {
        return "Retry failed WP batch {$this->wpBatchId} chunks";
    }

    public function uniqueId(): string
    {
        return 'retry-failed-wp-batch-'.$this->wpBatchId;
    }

    public function handle(): void
    {
        $wpBatch = WpBatch::find($this->wpBatchId);
        if (! $wpBatch || in_array($wpBatch->status, ['paused', 'deleting'], true)) {
            RunProgress::clearRetrying('wp-batch', $this->wpBatchId);

            return;
        }

        $total = RetryFailedChunks::retryRun(WpBatchSiteChunk::class, 'wp_batch_id', $this->wpBatchId);
        $queued = 0;
        $wpBatch = $wpBatch->fresh();
        if ($total > 0 && $wpBatch && ! in_array($wpBatch->status, ['paused', 'deleting'], true)) {
            $queued = PublishPendingChunks::forWpBatch($wpBatch);
        }

        $wpBatch?->recalculateCounters(true);
        RunProgress::clearRetrying('wp-batch', $this->wpBatchId);

        Log::info('Retry failed WP batch chunks finished', [
            'wp_batch_id' => $this->wpBatchId,
            'retried' => $total,
            'published' => $queued,
        ]);
    }

    public function failed(?\Throwable $e): void
    {
        RunProgress::clearRetrying('wp-batch', $this->wpBatchId);
    }
}
