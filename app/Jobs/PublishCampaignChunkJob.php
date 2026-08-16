<?php

namespace App\Jobs;

use App\Jobs\Concerns\HandlesChunkPublishFailure;
use App\Jobs\Concerns\NormalizesChunkApiCounts;
use App\Models\CampaignDomainChunk;
use App\Services\PbnApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishCampaignChunkJob implements ShouldQueue, ShouldBeUnique
{
    use HandlesChunkPublishFailure;
    use NormalizesChunkApiCounts;
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'campaign_links';

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public CampaignDomainChunk $chunk)
    {
        $this->onQueue(self::QUEUE);
    }

    public function displayName(): string
    {
        return 'Publish campaign chunk (campaign ' . $this->chunk->campaign_id . ', domain ' . $this->chunk->campaign_domain_id . ', chunk ' . $this->chunk->chunk_index . ')';
    }

    public function uniqueId(): string
    {
        return 'publish-campaign-chunk-' . $this->chunk->campaign_id . '-' . $this->chunk->campaign_domain_id . '-' . $this->chunk->chunk_index;
    }

    public function handle(PbnApiService $api): void
    {
        $chunk = $this->chunk->fresh(['campaignDomain', 'campaign']);
        if (! $chunk || ! $chunk->campaignDomain || ! $chunk->campaign) {
            return;
        }

        if (! $this->shouldProcessChunk($chunk)) {
            return;
        }

        $chunk->update([
            'status' => CampaignDomainChunk::STATUS_PROCESSING,
            'sent_at' => now(),
        ]);

        try {
            $response = $api->postCampaignChunk($chunk->campaignDomain, $chunk);
        } catch (\Throwable $e) {
            if ($this->attempts() >= $this->tries) {
                $this->markCampaignChunkFailed($chunk, $e->getMessage());
            } else {
                $chunk->update([
                    'status' => CampaignDomainChunk::STATUS_PENDING,
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

        $status = $failedCount > 0 ? CampaignDomainChunk::STATUS_PARTIAL : CampaignDomainChunk::STATUS_COMPLETED;
        $chunk->update([
            'results_payload' => $resultsPayload,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'status' => $status,
            'completed_at' => now(),
            'error_message' => null,
        ]);

        $campaign = $chunk->campaign;
        $campaign->recalculateCounters();

        if ($campaign->status === 'processing') {
            $campaign->update(['started_at' => $campaign->started_at ?? now()]);
        }
    }

    public function failed(?\Throwable $e): void
    {
        $chunk = $this->chunk->fresh(['campaign']);
        if (! $chunk || ! $chunk->campaign) {
            return;
        }

        $this->markCampaignChunkFailed($chunk, $e?->getMessage() ?? 'Publish failed after retries');

        Log::warning('PublishCampaignChunkJob exhausted retries', [
            'campaign_id' => $chunk->campaign_id,
            'campaign_domain_id' => $chunk->campaign_domain_id,
            'chunk_index' => $chunk->chunk_index,
            'message' => $e?->getMessage(),
        ]);
    }

    private function shouldProcessChunk(CampaignDomainChunk $chunk): bool
    {
        if ($chunk->status === CampaignDomainChunk::STATUS_PENDING) {
            return true;
        }

        if ($chunk->status === CampaignDomainChunk::STATUS_PROCESSING) {
            $stale = $chunk->sent_at && $chunk->sent_at->lt(now()->subMinutes(5));

            return $stale || $this->attempts() > 1;
        }

        return false;
    }
}
