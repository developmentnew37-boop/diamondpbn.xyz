<?php

namespace App\Jobs;

use App\Models\WpBatch;
use App\Models\WpBatchSiteChunk;
use App\Support\RetryFailedChunks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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
            return;
        }

        $total = 0;

        while (true) {
            $retried = (int) DB::transaction(function () {
                $chunk = WpBatchSiteChunk::query()
                    ->where('wp_batch_id', $this->wpBatchId)
                    ->where('failed_count', '>', 0)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if (! $chunk) {
                    return -1;
                }

                return RetryFailedChunks::process($chunk);
            });

            if ($retried < 0) {
                break;
            }

            $total += $retried;
        }

        $wpBatch->recalculateCounters();
        if ($total > 0 && $wpBatch->fresh()?->status !== 'paused') {
            $wpBatch->update(['status' => 'processing']);
        }

        Log::info('Retry failed WP batch chunks finished', [
            'wp_batch_id' => $this->wpBatchId,
            'retried' => $total,
        ]);
    }
}
