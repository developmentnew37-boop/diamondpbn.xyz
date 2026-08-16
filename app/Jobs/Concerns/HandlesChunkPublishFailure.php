<?php

namespace App\Jobs\Concerns;

use App\Models\BatchDomainChunk;

trait HandlesChunkPublishFailure
{
    /**
     * @param  array<int, array<string, mixed>>  $linksPayload
     * @return array<int, array<string, mixed>>
     */
    protected function buildFailedResultsPayload(array $linksPayload, string $message): array
    {
        $resultsPayload = [];
        foreach ($linksPayload as $link) {
            $resultsPayload[] = [
                'status' => 'failed',
                'remote_post_id' => null,
                'error' => $message,
                'url' => $link['url'] ?? null,
                'keyword' => $link['keyword'] ?? null,
            ];
        }

        return $resultsPayload;
    }

    protected function markBatchChunkFailed(BatchDomainChunk $chunk, string $message): void
    {
        if (in_array($chunk->status, [BatchDomainChunk::STATUS_COMPLETED, BatchDomainChunk::STATUS_PARTIAL], true)
            && ($chunk->success_count + $chunk->failed_count) > 0) {
            return;
        }

        $linksPayload = $chunk->links_payload ?? [];
        $linkCount = count($linksPayload);

        $chunk->update([
            'results_payload' => $this->buildFailedResultsPayload($linksPayload, $message),
            'success_count' => 0,
            'failed_count' => $linkCount,
            'status' => BatchDomainChunk::STATUS_PARTIAL,
            'completed_at' => now(),
            'error_message' => $message,
        ]);

        $chunk->batch?->recalculateCounters();
    }

    protected function markCampaignChunkFailed(\App\Models\CampaignDomainChunk $chunk, string $message): void
    {
        if (in_array($chunk->status, [\App\Models\CampaignDomainChunk::STATUS_COMPLETED, \App\Models\CampaignDomainChunk::STATUS_PARTIAL], true)
            && (($chunk->success_count ?? 0) + ($chunk->failed_count ?? 0)) > 0) {
            return;
        }

        $linksPayload = $chunk->links_payload ?? [];
        $linkCount = count($linksPayload);

        $chunk->update([
            'results_payload' => $this->buildFailedResultsPayload($linksPayload, $message),
            'success_count' => 0,
            'failed_count' => $linkCount,
            'status' => \App\Models\CampaignDomainChunk::STATUS_PARTIAL,
            'completed_at' => now(),
            'error_message' => $message,
        ]);

        $chunk->campaign?->recalculateCounters();
    }
}
