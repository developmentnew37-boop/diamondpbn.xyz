<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\CampaignDomainChunk;
use App\Models\CampaignLink;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CampaignDeletionTracker
{
    private const TTL_SECONDS = 86400;

    private static function key(int $campaignId, string $suffix): string
    {
        return "campaign_delete:{$campaignId}:{$suffix}";
    }

    public static function start(int $campaignId, int $domainCount): void
    {
        Cache::put(self::key($campaignId, 'remaining'), $domainCount, self::TTL_SECONDS);
        Cache::forget(self::key($campaignId, 'failed'));
        Cache::forget(self::key($campaignId, 'succeeded'));
    }

    public static function domainCompleted(int $campaignId, int $campaignDomainId, bool $success, ?string $error = null): void
    {
        $lock = Cache::lock(self::key($campaignId, 'lock'), 30);

        $lock->block(10, function () use ($campaignId, $campaignDomainId, $success, $error) {
            if ($success) {
                $succeeded = Cache::get(self::key($campaignId, 'succeeded'), []);
                $succeeded[] = $campaignDomainId;
                Cache::put(self::key($campaignId, 'succeeded'), $succeeded, self::TTL_SECONDS);
            } else {
                $failed = Cache::get(self::key($campaignId, 'failed'), []);
                $failed[] = [
                    'campaign_domain_id' => $campaignDomainId,
                    'error' => $error ?? 'Unknown error',
                ];
                Cache::put(self::key($campaignId, 'failed'), $failed, self::TTL_SECONDS);
            }

            $remaining = Cache::decrement(self::key($campaignId, 'remaining'));

            if ($remaining !== false && $remaining <= 0) {
                self::finalize($campaignId);
            }
        });
    }

    private static function finalize(int $campaignId): void
    {
        $failed = Cache::pull(self::key($campaignId, 'failed'), []);
        $succeededIds = array_values(array_unique(Cache::pull(self::key($campaignId, 'succeeded'), [])));
        Cache::forget(self::key($campaignId, 'remaining'));

        $campaign = Campaign::find($campaignId);
        if (! $campaign) {
            return;
        }

        if ($failed === []) {
            $campaign->delete();
            Log::info('DeleteCampaignJob: campaign deleted after all remote domains succeeded', [
                'campaign_id' => $campaignId,
            ]);

            return;
        }

        if ($succeededIds !== []) {
            CampaignDomainChunk::where('campaign_id', $campaignId)
                ->whereIn('campaign_domain_id', $succeededIds)
                ->delete();
        }

        self::pruneOrphanedLinks($campaignId);

        $remainingDomains = (int) CampaignDomainChunk::where('campaign_id', $campaignId)
            ->distinct()
            ->count('campaign_domain_id');

        $succeededCount = count($succeededIds);
        $failedCount = count($failed);
        $status = $succeededCount > $failedCount ? 'semi_deleted' : 'delete_failed';

        $campaign->update([
            'total_domains' => $remainingDomains,
            'status' => $status,
            'completed_at' => null,
        ]);

        $campaign->recalculateCounters();

        Log::warning('DeleteCampaignJob: partial remote deletion — campaign trimmed to undeleted domains', [
            'campaign_id' => $campaignId,
            'status' => $status,
            'succeeded_domains' => $succeededCount,
            'failed_domains' => $failedCount,
            'remaining_domains' => $remainingDomains,
            'failed_details' => $failed,
        ]);
    }

    private static function pruneOrphanedLinks(int $campaignId): void
    {
        $remainingUrls = [];

        CampaignDomainChunk::where('campaign_id', $campaignId)
            ->select(['links_payload'])
            ->cursor()
            ->each(function (CampaignDomainChunk $chunk) use (&$remainingUrls) {
                foreach ($chunk->links_payload ?? [] as $link) {
                    $url = $link['url'] ?? null;
                    if (is_string($url) && $url !== '') {
                        $remainingUrls[$url] = true;
                    }
                }
            });

        if ($remainingUrls === []) {
            return;
        }

        CampaignLink::where('campaign_id', $campaignId)
            ->whereNotIn('url', array_keys($remainingUrls))
            ->delete();
    }
}
