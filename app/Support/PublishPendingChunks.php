<?php

namespace App\Support;

use App\Jobs\PublishBatchChunkJob;
use App\Jobs\PublishCampaignChunkJob;
use App\Jobs\PublishWpBatchChunkJob;
use App\Models\Batch;
use App\Models\BatchDomainChunk;
use App\Models\Campaign;
use App\Models\CampaignDomainChunk;
use App\Models\WpBatch;
use App\Models\WpBatchSiteChunk;
use Illuminate\Support\Collection;

class PublishPendingChunks
{
    public static function forBatch(Batch $batch): int
    {
        if (in_array($batch->status, ['paused', 'deleting'], true)) {
            return 0;
        }

        $ids = self::pendingIds(
            BatchDomainChunk::query()->where('batch_id', $batch->id),
            'domain_id'
        );
        if ($ids->isEmpty()) {
            return 0;
        }

        self::markPending(BatchDomainChunk::class, $ids);
        $chunks = self::loadByIds(BatchDomainChunk::class, $ids, ['id', 'batch_id', 'domain_id', 'chunk_index'])
            ->sortBy(['domain_id', 'chunk_index'])
            ->values();

        $batch->update(['status' => 'processing', 'started_at' => $batch->started_at ?? now()]);
        $queued = self::dispatch($chunks, PublishBatchChunkJob::class);
        RunProgress::setPublishing('batch', $batch->id, $queued);

        return $queued;
    }

    public static function forCampaign(Campaign $campaign): int
    {
        if (in_array($campaign->status, ['paused', 'deleting'], true)) {
            return 0;
        }

        $ids = self::pendingIds(
            CampaignDomainChunk::query()->where('campaign_id', $campaign->id),
            'campaign_domain_id'
        );
        if ($ids->isEmpty()) {
            return 0;
        }

        self::markPending(CampaignDomainChunk::class, $ids);
        $chunks = self::loadByIds(CampaignDomainChunk::class, $ids, ['id', 'campaign_id', 'campaign_domain_id', 'chunk_index'])
            ->sortBy(['campaign_domain_id', 'chunk_index'])
            ->values();

        $campaign->update(['status' => 'processing', 'started_at' => $campaign->started_at ?? now()]);
        $queued = self::dispatch($chunks, PublishCampaignChunkJob::class);
        RunProgress::setPublishing('campaign', $campaign->id, $queued);

        return $queued;
    }

    public static function forWpBatch(WpBatch $wpBatch): int
    {
        if (in_array($wpBatch->status, ['paused', 'deleting'], true)) {
            return 0;
        }

        $ids = self::pendingIds(
            WpBatchSiteChunk::query()->where('wp_batch_id', $wpBatch->id),
            'wp_site_id'
        );
        if ($ids->isEmpty()) {
            return 0;
        }

        self::markPending(WpBatchSiteChunk::class, $ids);
        $chunks = self::loadByIds(WpBatchSiteChunk::class, $ids, ['id', 'wp_batch_id', 'wp_site_id', 'chunk_index'])
            ->sortBy(['wp_site_id', 'chunk_index'])
            ->values();

        $wpBatch->update(['status' => 'processing', 'started_at' => $wpBatch->started_at ?? now()]);
        $queued = self::dispatch($chunks, PublishWpBatchChunkJob::class);
        RunProgress::setPublishing('wp-batch', $wpBatch->id, $queued);

        return $queued;
    }

    /**
     * @param  array<int>  $ids
     */
    public static function dispatchIds(string $type, array $ids, int $extraDelaySeconds = 0): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return 0;
        }

        return match ($type) {
            'batch' => self::dispatch(
                self::loadByIds(BatchDomainChunk::class, $ids, ['id', 'batch_id', 'domain_id', 'chunk_index']),
                PublishBatchChunkJob::class,
                $extraDelaySeconds
            ),
            'campaign' => self::dispatch(
                self::loadByIds(CampaignDomainChunk::class, $ids, ['id', 'campaign_id', 'campaign_domain_id', 'chunk_index']),
                PublishCampaignChunkJob::class,
                $extraDelaySeconds
            ),
            'wp-batch' => self::dispatch(
                self::loadByIds(WpBatchSiteChunk::class, $ids, ['id', 'wp_batch_id', 'wp_site_id', 'chunk_index']),
                PublishWpBatchChunkJob::class,
                $extraDelaySeconds
            ),
            default => 0,
        };
    }

    /**
     * @return Collection<int, int>
     */
    private static function pendingIds($query, string $ownerKey): Collection
    {
        $staleBefore = now()->subMinutes(10);

        return $query
            ->where(function ($q) use ($staleBefore) {
                $q->where('status', 'pending')
                    ->orWhere(function ($qq) use ($staleBefore) {
                        $qq->where('status', 'processing')
                            ->where(function ($qqq) use ($staleBefore) {
                                $qqq->whereNull('sent_at')
                                    ->orWhere('sent_at', '<', $staleBefore);
                            });
                    });
            })
            ->orderBy($ownerKey)
            ->orderBy('chunk_index')
            ->pluck('id');
    }

    /**
     * @param  Collection<int, int>|array<int>  $ids
     */
    private static function markPending(string $class, Collection|array $ids): void
    {
        foreach (array_chunk(Collection::wrap($ids)->all(), 500) as $part) {
            $class::whereIn('id', $part)->update(['status' => $class::STATUS_PENDING]);
        }
    }

    /**
     * @param  array<int, string>  $columns
     * @param  Collection<int, int>|array<int>  $ids
     * @return Collection<int, object>
     */
    private static function loadByIds(string $class, Collection|array $ids, array $columns): Collection
    {
        $rows = collect();
        foreach (array_chunk(Collection::wrap($ids)->all(), 500) as $part) {
            $rows = $rows->concat($class::whereIn('id', $part)->select($columns)->get());
        }

        return $rows;
    }

    /**
     * @param  Collection<int, object>  $chunks
     */
    private static function dispatch(Collection $chunks, string $jobClass, int $extraDelaySeconds = 0): int
    {
        $delaySeconds = PbnSettings::getLinkDelaySeconds();
        foreach ($chunks as $index => $chunk) {
            $jobClass::dispatch($chunk)->delay(now()->addSeconds($extraDelaySeconds + ($index * $delaySeconds)));
        }

        return $chunks->count();
    }
}
