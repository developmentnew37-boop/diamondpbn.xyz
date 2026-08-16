<?php

namespace App\Jobs\Concerns;

use App\Models\WpBatchSiteChunk;

trait HandlesWpChunkPublishFailure
{
    use HandlesChunkPublishFailure;

    protected function markWpBatchChunkFailed(WpBatchSiteChunk $chunk, string $message): void
    {
        if (in_array($chunk->status, [WpBatchSiteChunk::STATUS_COMPLETED, WpBatchSiteChunk::STATUS_PARTIAL], true)
            && ($chunk->success_count + $chunk->failed_count) > 0) {
            return;
        }

        $linksPayload = $chunk->links_payload ?? [];
        $linkCount = count($linksPayload);

        $chunk->update([
            'results_payload' => $this->buildFailedResultsPayload($linksPayload, $message),
            'success_count' => 0,
            'failed_count' => $linkCount,
            'status' => WpBatchSiteChunk::STATUS_PARTIAL,
            'completed_at' => now(),
            'error_message' => $message,
        ]);

        $chunk->wpBatch?->recalculateCounters();
    }
}
