<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignDomainChunk;
use App\Support\PublishPendingChunks;
use App\Support\RetryFailedChunks;
use App\Support\RunProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryFailedCampaignChunksJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'campaign_links';

    public int $timeout = 1800;

    public int $tries = 1;

    public int $uniqueFor = 1800;

    public function __construct(public int $campaignId)
    {
        $this->onQueue(self::QUEUE);
    }

    public function displayName(): string
    {
        return "Retry failed campaign {$this->campaignId} chunks";
    }

    public function uniqueId(): string
    {
        return 'retry-failed-campaign-'.$this->campaignId;
    }

    public function handle(): void
    {
        $campaign = Campaign::find($this->campaignId);
        if (! $campaign || in_array($campaign->status, ['paused', 'deleting'], true)) {
            RunProgress::clearRetrying('campaign', $this->campaignId);

            return;
        }

        $total = RetryFailedChunks::retryRun(CampaignDomainChunk::class, 'campaign_id', $this->campaignId);
        $queued = 0;
        $campaign = $campaign->fresh();
        if ($total > 0 && $campaign && ! in_array($campaign->status, ['paused', 'deleting'], true)) {
            $queued = PublishPendingChunks::forCampaign($campaign);
        }

        $campaign?->recalculateCounters(true);
        RunProgress::clearRetrying('campaign', $this->campaignId);

        Log::info('Retry failed campaign chunks finished', [
            'campaign_id' => $this->campaignId,
            'retried' => $total,
            'published' => $queued,
        ]);
    }

    public function failed(?\Throwable $e): void
    {
        RunProgress::clearRetrying('campaign', $this->campaignId);
    }
}
