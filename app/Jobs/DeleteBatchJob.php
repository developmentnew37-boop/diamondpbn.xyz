<?php

namespace App\Jobs;

use App\Models\Batch;
use App\Models\BatchDomainChunk;
use App\Support\BatchDeletionTracker;
use App\Support\PbnSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates batch deletion: dispatches one DeleteBatchDomainJob per domain,
 * then BatchDeletionTracker finalizes when all domains have been processed.
 */
class DeleteBatchJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'delete_batch_links';

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(public Batch $batch)
    {
        $this->onQueue(self::QUEUE);
    }

    public function displayName(): string
    {
        return 'Delete batch (ID '.$this->batch->id.') from remote then local';
    }

    public function uniqueId(): string
    {
        return 'delete-batch-'.$this->batch->id;
    }

    public function handle(): void
    {
        $batch = $this->batch->fresh();
        if (! $batch) {
            return;
        }

        $domainIds = BatchDomainChunk::where('batch_id', $batch->id)
            ->pluck('domain_id')
            ->unique()
            ->values();

        if ($domainIds->isEmpty()) {
            $batch->delete();

            return;
        }

        BatchDeletionTracker::start($batch->id, $domainIds->count());
        $batch->update(['status' => 'deleting']);

        $delaySeconds = PbnSettings::getLinkDelaySeconds();
        foreach ($domainIds as $index => $domainId) {
            DeleteBatchDomainJob::dispatch($batch->id, (int) $domainId)
                ->delay(now()->addSeconds($index * $delaySeconds));
        }

        Log::info('DeleteBatchJob: queued per-domain delete jobs', [
            'batch_id' => $batch->id,
            'domain_count' => $domainIds->count(),
            'delay_seconds' => $delaySeconds,
        ]);
    }

    public function failed(?\Throwable $e): void
    {
        if ($e) {
            Log::error('DeleteBatchJob failed', [
                'batch_id' => $this->batch->id ?? null,
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }
}
