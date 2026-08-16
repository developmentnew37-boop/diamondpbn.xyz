<?php

namespace App\Console\Commands;

use App\Jobs\Concerns\HandlesChunkPublishFailure;
use App\Models\Batch;
use App\Models\BatchDomainChunk;
use Illuminate\Console\Command;

class ResolveStuckBatchChunks extends Command
{
    use HandlesChunkPublishFailure;

    protected $signature = 'batches:resolve-stuck-chunks {batch? : Batch ID to fix (optional — all batches if omitted)}';

    protected $description = 'Mark pending/processing batch chunks as failed when publish jobs already exhausted retries';

    public function handle(): int
    {
        $batchId = $this->argument('batch');

        $query = BatchDomainChunk::query()
            ->whereIn('status', [BatchDomainChunk::STATUS_PENDING, BatchDomainChunk::STATUS_PROCESSING])
            ->where(function ($q) {
                $q->whereNotNull('error_message')
                    ->orWhere(function ($q2) {
                        $q2->where('success_count', 0)
                            ->where('failed_count', 0)
                            ->whereNotNull('sent_at')
                            ->where('sent_at', '<', now()->subMinutes(5));
                    });
            });

        if ($batchId) {
            $query->where('batch_id', (int) $batchId);
        }

        $chunks = $query->with('batch')->get();
        if ($chunks->isEmpty()) {
            $this->info('No stuck chunks found.');

            return self::SUCCESS;
        }

        $batchIds = [];
        foreach ($chunks as $chunk) {
            $message = $chunk->error_message ?: 'Publish did not complete (stuck chunk recovered)';
            $this->markBatchChunkFailed($chunk, $message);
            $batchIds[$chunk->batch_id] = true;
            $this->line("Chunk batch={$chunk->batch_id} domain={$chunk->domain_id} → failed ({$message})");
        }

        foreach (array_keys($batchIds) as $id) {
            Batch::find($id)?->recalculateCounters();
        }

        $this->info('Resolved '.$chunks->count().' stuck chunk(s).');

        return self::SUCCESS;
    }
}
