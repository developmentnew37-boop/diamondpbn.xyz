<?php

namespace App\Support;

use App\Models\Batch;
use App\Models\BatchDomainChunk;
use App\Models\Link;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BatchDeletionTracker
{
    private const TTL_SECONDS = 86400;

    private static function key(int $batchId, string $suffix): string
    {
        return "batch_delete:{$batchId}:{$suffix}";
    }

    public static function start(int $batchId, int $domainCount): void
    {
        Cache::put(self::key($batchId, 'remaining'), $domainCount, self::TTL_SECONDS);
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

    public static function domainCompleted(int $batchId, int $domainId, bool $success, ?string $error = null): void
    {
        $lock = Cache::lock(self::key($batchId, 'lock'), 30);

        $lock->block(10, function () use ($batchId, $domainId, $success, $error) {
            if ($success) {
                $succeeded = Cache::get(self::key($batchId, 'succeeded'), []);
                $succeeded[] = $domainId;
                Cache::put(self::key($batchId, 'succeeded'), $succeeded, self::TTL_SECONDS);
            } else {
                $failed = Cache::get(self::key($batchId, 'failed'), []);
                $failed[] = [
                    'domain_id' => $domainId,
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

        $batch = Batch::find($batchId);
        if (! $batch) {
            return;
        }

        if ($failed === []) {
            $batch->delete();
            Log::info('DeleteBatchJob: batch deleted after all remote domains succeeded', [
                'batch_id' => $batchId,
            ]);

            return;
        }

        if ($succeededIds !== []) {
            BatchDomainChunk::where('batch_id', $batchId)
                ->whereIn('domain_id', $succeededIds)
                ->delete();
        }

        self::pruneOrphanedLinks($batchId);

        $remainingDomains = (int) BatchDomainChunk::where('batch_id', $batchId)
            ->distinct()
            ->count('domain_id');

        $succeededCount = count($succeededIds);
        $failedCount = count($failed);
        $status = $succeededCount > $failedCount ? 'semi_deleted' : 'delete_failed';

        $batch->update([
            'total_domains' => $remainingDomains,
            'status' => $status,
            'completed_at' => null,
        ]);

        $batch->recalculateCounters();

        Log::warning('DeleteBatchJob: partial remote deletion — batch trimmed to undeleted domains', [
            'batch_id' => $batchId,
            'status' => $status,
            'succeeded_domains' => $succeededCount,
            'failed_domains' => $failedCount,
            'remaining_domains' => $remainingDomains,
            'failed_details' => $failed,
        ]);
    }

    /** Keep only links still referenced in remaining (undeleted) domain chunks. */
    private static function pruneOrphanedLinks(int $batchId): void
    {
        $remainingUrls = [];

        BatchDomainChunk::where('batch_id', $batchId)
            ->select(['links_payload'])
            ->cursor()
            ->each(function (BatchDomainChunk $chunk) use (&$remainingUrls) {
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

        Link::where('batch_id', $batchId)
            ->whereNotIn('url', array_keys($remainingUrls))
            ->delete();
    }
}
