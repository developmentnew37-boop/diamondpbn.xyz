<?php

namespace App\Support;

use App\Models\WpBatch;
use App\Models\WpBatchSiteChunk;
use App\Models\WpLink;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WpBatchDeletionTracker
{
    private const TTL_SECONDS = 86400;

    private static function key(int $batchId, string $suffix): string
    {
        return "wp_batch_delete:{$batchId}:{$suffix}";
    }

    public static function start(int $batchId, int $siteCount): void
    {
        Cache::put(self::key($batchId, 'remaining'), $siteCount, self::TTL_SECONDS);
        Cache::forget(self::key($batchId, 'failed'));
        Cache::forget(self::key($batchId, 'succeeded'));
    }

    /** Clear in-progress delete counters (e.g. batch emptied manually or stuck "deleting"). */
    public static function forget(int $batchId): void
    {
        Cache::forget(self::key($batchId, 'remaining'));
        Cache::forget(self::key($batchId, 'failed'));
        Cache::forget(self::key($batchId, 'succeeded'));
    }

    public static function siteCompleted(int $batchId, int $siteId, bool $success, ?string $error = null): void
    {
        $lock = Cache::lock(self::key($batchId, 'lock'), 30);

        $lock->block(10, function () use ($batchId, $siteId, $success, $error) {
            if ($success) {
                $succeeded = Cache::get(self::key($batchId, 'succeeded'), []);
                $succeeded[] = $siteId;
                Cache::put(self::key($batchId, 'succeeded'), $succeeded, self::TTL_SECONDS);
            } else {
                $failed = Cache::get(self::key($batchId, 'failed'), []);
                $failed[] = [
                    'wp_site_id' => $siteId,
                    'error' => $error ?? 'Unknown error',
                ];
                Cache::put(self::key($batchId, 'failed'), $failed, self::TTL_SECONDS);
            }

            $remaining = Cache::decrement(self::key($batchId, 'remaining'));

            if ($remaining !== false && $remaining <= 0) {
                self::finalize($batchId);
            }
        });
    }

    private static function finalize(int $batchId): void
    {
        $failed = Cache::pull(self::key($batchId, 'failed'), []);
        $succeededIds = array_values(array_unique(Cache::pull(self::key($batchId, 'succeeded'), [])));
        Cache::forget(self::key($batchId, 'remaining'));

        $batch = WpBatch::find($batchId);
        if (! $batch) {
            return;
        }

        if ($failed === []) {
            $batch->delete();
            Log::info('DeleteWpBatchJob: batch deleted after all remote sites succeeded', [
                'wp_batch_id' => $batchId,
            ]);

            return;
        }

        if ($succeededIds !== []) {
            WpBatchSiteChunk::where('wp_batch_id', $batchId)
                ->whereIn('wp_site_id', $succeededIds)
                ->delete();
        }

        self::pruneOrphanedLinks($batchId);

        $remainingSites = (int) WpBatchSiteChunk::where('wp_batch_id', $batchId)
            ->distinct()
            ->count('wp_site_id');

        $succeededCount = count($succeededIds);
        $failedCount = count($failed);
        $status = $succeededCount > $failedCount ? 'semi_deleted' : 'delete_failed';

        $batch->update([
            'total_domains' => $remainingSites,
            'status' => $status,
            'completed_at' => null,
        ]);

        $batch->recalculateCounters();

        Log::warning('DeleteWpBatchJob: partial remote deletion — batch trimmed to undeleted sites', [
            'wp_batch_id' => $batchId,
            'status' => $status,
            'succeeded_sites' => $succeededCount,
            'failed_sites' => $failedCount,
            'remaining_sites' => $remainingSites,
            'failed_details' => $failed,
        ]);
    }

    /** Keep only links still referenced in remaining (undeleted) site chunks. */
    private static function pruneOrphanedLinks(int $batchId): void
    {
        $remainingUrls = [];

        WpBatchSiteChunk::where('wp_batch_id', $batchId)
            ->select(['links_payload'])
            ->cursor()
            ->each(function (WpBatchSiteChunk $chunk) use (&$remainingUrls) {
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

        WpLink::where('wp_batch_id', $batchId)
            ->whereNotIn('url', array_keys($remainingUrls))
            ->delete();
    }
}
