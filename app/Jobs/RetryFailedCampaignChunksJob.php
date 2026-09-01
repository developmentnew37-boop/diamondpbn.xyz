<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignDomainChunk;
use App\Support\RetryFailedChunks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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
            return;
        }

        $total = 0;

        while (true) {
            $retried = (int) DB::transaction(function () {
                $chunk = CampaignDomainChunk::query()
                    ->where('campaign_id', $this->campaignId)
                    ->where('failed_count', '>', 0)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if (! $chunk) {
                    return -1;
                }

                return RetryFailedChunks::process($chunk);
            });

            if ($retried < 0) {
                break;
            }

            $total += $retried;
        }

        $campaign->recalculateCounters();
        if ($total > 0 && $campaign->fresh()?->status !== 'paused') {
            $campaign->update(['status' => 'processing']);
        }

        Log::info('Retry failed campaign chunks finished', [
            'campaign_id' => $this->campaignId,
            'retried' => $total,
        ]);
    }
}
