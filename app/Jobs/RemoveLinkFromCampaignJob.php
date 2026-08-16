<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignDomain;
use App\Models\CampaignDomainChunk;
use App\Models\CampaignLink;
use App\Services\PbnApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * When an admin deletes a link from a campaign:
 * 1. Remove the link from ALL remote sites via DELETE /hidden-links/by-url (exact URL) per domain.
 * 2. Then remove that specific link from the campaign (chunk payloads, counts, and link record).
 */
class RemoveLinkFromCampaignJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'remove_link_from_campaign';

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public Campaign $campaign,
        public CampaignLink $link
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function displayName(): string
    {
        return 'Remove link from campaign (campaign ' . $this->campaign->id . ', link ' . $this->link->id . ')';
    }

    public function uniqueId(): string
    {
        return 'remove-link-from-campaign-' . $this->campaign->id . '-' . $this->link->id;
    }

    public function handle(PbnApiService $api): void
    {
        $campaign = $this->campaign->fresh();
        $link = $this->link->fresh();

        if (!$campaign || !$link || $link->campaign_id !== $campaign->id) {
            return;
        }

        $linkUrl = $link->url;

        // Find all chunks that contain this link
        $chunks = CampaignDomainChunk::where('campaign_id', $campaign->id)->get();

        $domainsToUpdate = [];

        foreach ($chunks as $chunk) {
            $linksPayload = $chunk->links_payload ?? [];

            // Find if this link exists in this chunk
            foreach ($linksPayload as $index => $linkData) {
                if (($linkData['url'] ?? '') === $linkUrl) {
                    // Found the link in this chunk
                    if (!isset($domainsToUpdate[$chunk->campaign_domain_id])) {
                        $domainsToUpdate[$chunk->campaign_domain_id] = [
                            'domain' => $chunk->campaignDomain,
                            'chunks' => [],
                        ];
                    }

                    $domainsToUpdate[$chunk->campaign_domain_id]['chunks'][] = [
                        'chunk' => $chunk,
                        'index' => $index,
                    ];
                    break; // Link found in this chunk, move to next chunk
                }
            }
        }

        // Step 1: Remove link from ALL remote sites
        foreach ($domainsToUpdate as $domainData) {
            $domain = $domainData['domain'];

            if (!$domain || $linkUrl === '') {
                continue;
            }

            try {
                // Use the campaign domain specific API method
                $api->deleteLinkByUrlFromCampaignDomain($domain, $linkUrl);

                Log::info("Link removed from campaign domain: {$domain->domain}", [
                    'campaign_id' => $campaign->id,
                    'link_id' => $link->id,
                    'domain_id' => $domain->id,
                    'url' => $linkUrl,
                ]);
            } catch (\Throwable $e) {
                Log::warning('RemoveLinkFromCampaignJob: failed to delete link on domain', [
                    'campaign_id' => $campaign->id,
                    'link_id' => $link->id,
                    'domain_id' => $domain->id,
                    'domain_api_url' => $domain->api_url,
                    'url' => $linkUrl,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        // Step 2: Remove the link from campaign chunk payloads and update counts
        $totalProcessedToDecrement = 0;
        $totalSuccessToDecrement = 0;
        $totalFailedToDecrement = 0;

        foreach ($domainsToUpdate as $domainData) {
            foreach ($domainData['chunks'] as $chunkData) {
                $chunk = $chunkData['chunk'];
                $index = $chunkData['index'];

                $linksPayload = $chunk->links_payload ?? [];
                $resultsPayload = $chunk->results_payload ?? [];

                $result = $resultsPayload[$index] ?? null;
                $status = $result['status'] ?? '';
                $wasSuccess = in_array($status, ['success', 'completed', 'posted'], true);
                $wasFailed = $result !== null && ! $wasSuccess && ! in_array($status, ['', 'pending', 'processing'], true);
                $wasProcessed = $wasSuccess || $wasFailed;

                if ($wasProcessed) {
                    $totalProcessedToDecrement++;
                }
                if ($wasSuccess) {
                    $totalSuccessToDecrement++;
                } elseif ($wasFailed) {
                    $totalFailedToDecrement++;
                }

                // Remove the link from payloads
                array_splice($linksPayload, $index, 1);
                array_splice($resultsPayload, $index, 1);

                $chunk->update([
                    'links_payload' => $linksPayload,
                    'results_payload' => $resultsPayload,
                    'success_count' => max(0, ($chunk->success_count ?? 0) - ($wasSuccess ? 1 : 0)),
                    'failed_count' => max(0, ($chunk->failed_count ?? 0) - ($wasFailed ? 1 : 0)),
                ]);
            }
        }

        // Update campaign counts
        $campaign->decrement('total_links', 1);
        if ($totalProcessedToDecrement > 0) {
            $campaign->decrement('processed_count', $totalProcessedToDecrement);
        }
        if ($totalSuccessToDecrement > 0) {
            $campaign->decrement('success_count', $totalSuccessToDecrement);
        }
        if ($totalFailedToDecrement > 0) {
            $campaign->decrement('failed_count', $totalFailedToDecrement);
        }

        // Step 3: Delete the link record
        $link->delete();
    }

    public function failed(?\Throwable $e): void
    {
        if ($e) {
            Log::error('RemoveLinkFromCampaignJob failed', [
                'campaign_id' => $this->campaign->id ?? null,
                'link_id' => $this->link->id ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
