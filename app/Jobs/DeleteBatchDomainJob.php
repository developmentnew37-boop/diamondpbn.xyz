<?php

namespace App\Jobs;

use App\Models\BatchDomainChunk;
use App\Models\Domain;
use App\Services\PbnApiService;
use App\Support\BatchDeletionTracker;
use App\Support\RunRemoteDelete;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeleteBatchDomainJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'delete_batch_links';

    /** Allow long-running bulk deletes on slow PBN sites. */
    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        public int $batchId,
        public int $domainId
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function displayName(): string
    {
        return "Delete batch {$this->batchId} links on domain {$this->domainId}";
    }

    public function uniqueId(): string
    {
        return 'delete-batch-domain-'.$this->batchId.'-'.$this->domainId;
    }

    public function handle(PbnApiService $api): void
    {
        $domain = Domain::find($this->domainId);
        if (! $domain) {
            BatchDeletionTracker::domainCompleted(
                $this->batchId,
                $this->domainId,
                false,
                'Domain not found'
            );

            return;
        }

        if (! RunRemoteDelete::ownerNeedsRemote(BatchDomainChunk::class, 'batch_id', 'domain_id', $this->batchId, $this->domainId)) {
            BatchDeletionTracker::domainCompleted($this->batchId, $this->domainId, true);

            return;
        }

        try {
            $api->deleteLinksByBatchId($domain, $this->batchId);
            BatchDeletionTracker::domainCompleted($this->batchId, $this->domainId, true);
        } catch (\Throwable $e) {
            Log::warning('DeleteBatchDomainJob: failed to delete links by batch_id on domain', [
                'batch_id' => $this->batchId,
                'domain_id' => $this->domainId,
                'domain' => $domain->domain,
                'message' => $e->getMessage(),
            ]);

            BatchDeletionTracker::domainCompleted(
                $this->batchId,
                $this->domainId,
                false,
                $e->getMessage()
            );
        }
    }
}
