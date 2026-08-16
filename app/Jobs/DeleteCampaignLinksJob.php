<?php

namespace App\Jobs;

use App\Models\CampaignDomain;
use App\Services\PbnApiService;
use App\Support\CampaignDeletionTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeleteCampaignLinksJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'delete_campaign_links';

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        public int $campaignId,
        public int $campaignDomainId
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function displayName(): string
    {
        return "Delete campaign {$this->campaignId} links on domain {$this->campaignDomainId}";
    }

    public function uniqueId(): string
    {
        return 'delete-campaign-domain-'.$this->campaignId.'-'.$this->campaignDomainId;
    }

    public function handle(PbnApiService $api): void
    {
        $domain = CampaignDomain::find($this->campaignDomainId);
        if (! $domain) {
            CampaignDeletionTracker::domainCompleted(
                $this->campaignId,
                $this->campaignDomainId,
                false,
                'Domain not found'
            );

            return;
        }

        $ping = $api->pingCampaignDomain($domain);
        if (! ($ping['ok'] ?? false)) {
            Log::warning('DeleteCampaignLinksJob: skipping unresponsive domain', [
                'campaign_id' => $this->campaignId,
                'campaign_domain_id' => $this->campaignDomainId,
                'domain' => $domain->domain,
                'error' => $ping['error'] ?? 'Health check failed',
            ]);

            CampaignDeletionTracker::domainCompleted(
                $this->campaignId,
                $this->campaignDomainId,
                false,
                $ping['error'] ?? 'Site did not respond to health check'
            );

            return;
        }

        try {
            $api->deleteCampaignLinksByBatchId($domain, $this->campaignId);
            CampaignDeletionTracker::domainCompleted($this->campaignId, $this->campaignDomainId, true);
        } catch (\Throwable $e) {
            Log::warning('DeleteCampaignLinksJob: failed to delete links by batch_id on domain', [
                'campaign_id' => $this->campaignId,
                'campaign_domain_id' => $this->campaignDomainId,
                'domain' => $domain->domain,
                'message' => $e->getMessage(),
            ]);

            CampaignDeletionTracker::domainCompleted(
                $this->campaignId,
                $this->campaignDomainId,
                false,
                $e->getMessage()
            );
        }
    }
}
