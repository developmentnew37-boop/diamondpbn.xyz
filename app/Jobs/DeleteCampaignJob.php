<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignDomainChunk;
use App\Support\CampaignDeletionTracker;
use App\Support\PbnSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeleteCampaignJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'delete_campaign_links';

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(public Campaign $campaign)
    {
        $this->onQueue(self::QUEUE);
    }

    public function displayName(): string
    {
        return 'Delete campaign (ID '.$this->campaign->id.') from remote then local';
    }

    public function uniqueId(): string
    {
        return 'delete-campaign-'.$this->campaign->id;
    }

    public function handle(): void
    {
        $campaign = $this->campaign->fresh();
        if (! $campaign) {
            return;
        }

        $domainIds = CampaignDomainChunk::where('campaign_id', $campaign->id)
            ->pluck('campaign_domain_id')
            ->unique()
            ->values();

        if ($domainIds->isEmpty()) {
            $campaign->delete();

            return;
        }

        CampaignDeletionTracker::start($campaign->id, $domainIds->count());
        $campaign->update(['status' => 'deleting']);

        $delaySeconds = PbnSettings::getLinkDelaySeconds();
        foreach ($domainIds as $index => $domainId) {
            DeleteCampaignLinksJob::dispatch($campaign->id, (int) $domainId)
                ->delay(now()->addSeconds($index * $delaySeconds));
        }

        Log::info('DeleteCampaignJob: queued per-domain delete jobs', [
            'campaign_id' => $campaign->id,
            'domain_count' => $domainIds->count(),
            'delay_seconds' => $delaySeconds,
        ]);
    }
}
