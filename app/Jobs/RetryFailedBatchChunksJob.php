<?php

namespace App\Jobs;

use App\Models\Batch;
use App\Models\BatchDomainChunk;
use App\Support\RetryFailedChunks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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
            return;
        }

        $total = 0;

        while (true) {
            $retried = (int) DB::transaction(function () {
                $chunk = BatchDomainChunk::query()
                    ->where('batch_id', $this->batchId)
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

        $batch->recalculateCounters();
        if ($total > 0 && $batch->fresh()?->status !== 'paused') {
            $batch->update(['status' => 'processing']);
        }

        Log::info('Retry failed batch chunks finished', [
            'batch_id' => $this->batchId,
            'retried' => $total,
        ]);
    }
}
