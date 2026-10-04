<?php

namespace App\Support;

use App\Models\BatchDomainChunk;
use App\Models\CampaignDomainChunk;
use App\Models\WpBatchSiteChunk;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RetryFailedChunks
{
    /**
     * Reset all-failed chunks in one update, then split mixed chunks. Returns retried link count.
     */
    public static function retryRun(string $chunkClass, string $parentKey, int $parentId): int
    {
        $reset = $chunkClass::query()
            ->where($parentKey, $parentId)
            ->where('failed_count', '>', 0)
            ->whereRaw('failed_count >= COALESCE(JSON_LENGTH(links_payload), 0)');

        $resetCount = (int) (clone $reset)->sum('failed_count');
        $reset->update([
            'status' => $chunkClass::STATUS_PENDING,
            'failed_count' => 0,
            'success_count' => 0,
            'results_payload' => json_encode([]),
            'error_message' => null,
            'sent_at' => null,
            'completed_at' => null,
        ]);

        $mixed = 0;
        while (true) {
            $retried = (int) DB::transaction(function () use ($chunkClass, $parentKey, $parentId) {
                $chunk = $chunkClass::query()
                    ->where($parentKey, $parentId)
                    ->where('failed_count', '>', 0)
                    ->orderBy('id')
                    ->first();

                if (! $chunk) {
                    return -1;
                }

                return self::process($chunk)['count'];
            });

            if ($retried < 0) {
                break;
            }

            $mixed += $retried;
        }

        return $resetCount + $mixed;
    }

    /**
     * @return array{count: int, ids: array<int>}
     */
    public static function process(Model $chunk): array
    {
        [$parentKey, $ownerKey] = self::keys($chunk);
        $class = $chunk::class;

        $class::query()
            ->where($parentKey, $chunk->{$parentKey})
            ->where($ownerKey, $chunk->{$ownerKey})
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');

        $chunk = $class::query()->whereKey($chunk->id)->first();
        if (! $chunk || (int) ($chunk->failed_count ?? 0) < 1) {
            return ['count' => 0, 'ids' => []];
        }

        $linksPayload = is_array($chunk->links_payload) ? $chunk->links_payload : [];
        $resultsPayload = is_array($chunk->results_payload) ? $chunk->results_payload : [];
        $failedIndices = method_exists($chunk, 'failedLinkIndices') ? $chunk->failedLinkIndices() : [];
        $linkCount = count($linksPayload);

        if ($failedIndices === []) {
            if ((int) ($chunk->failed_count ?? 0) > 0) {
                $chunk->update(['failed_count' => 0]);
            }

            return ['count' => 0, 'ids' => []];
        }

        if ($linkCount > 0 && count($failedIndices) >= $linkCount) {
            $nextIndex = self::nextChunkIndex($class, $parentKey, $ownerKey, $chunk);

            $chunk->update([
                'chunk_index' => $nextIndex,
                'status' => $class::STATUS_PENDING,
                'failed_count' => 0,
                'success_count' => 0,
                'results_payload' => [],
                'error_message' => null,
                'sent_at' => null,
                'completed_at' => null,
            ]);

            return ['count' => $linkCount, 'ids' => [(int) $chunk->id]];
        }

        $failedLinks = array_values(array_filter(array_map(
            fn ($i) => $linksPayload[$i] ?? null,
            $failedIndices
        )));

        if ($failedLinks === []) {
            $chunk->update(['failed_count' => 0]);

            return ['count' => 0, 'ids' => []];
        }

        $chunkSize = (int) constant($class.'::CHUNK_SIZE');
        $retryChunks = array_chunk($failedLinks, max(1, $chunkSize));
        $nextIndex = self::nextChunkIndex($class, $parentKey, $ownerKey, $chunk);

        $ids = [];
        $total = 0;
        foreach ($retryChunks as $retryLinks) {
            $created = $class::create([
                $parentKey => $chunk->{$parentKey},
                $ownerKey => $chunk->{$ownerKey},
                'chunk_index' => $nextIndex,
                'links_payload' => $retryLinks,
                'status' => $class::STATUS_PENDING,
            ]);
            $ids[] = (int) $created->id;
            $total += count($retryLinks);
            $nextIndex++;
        }

        $failedLookup = array_flip($failedIndices);
        $remainingLinks = [];
        $remainingResults = [];
        foreach ($linksPayload as $k => $v) {
            if (isset($failedLookup[$k])) {
                continue;
            }
            $remainingLinks[] = $v;
            $remainingResults[] = $resultsPayload[$k] ?? null;
        }

        if ($remainingLinks === []) {
            $chunk->delete();

            return ['count' => $total, 'ids' => $ids];
        }

        $chunk->update([
            'links_payload' => $remainingLinks,
            'results_payload' => $remainingResults,
            'success_count' => count($remainingLinks),
            'failed_count' => 0,
            'status' => $class::STATUS_COMPLETED,
        ]);

        return ['count' => $total, 'ids' => $ids];
    }

    /**
     * @param  class-string<Model>  $class
     */
    private static function nextChunkIndex(string $class, string $parentKey, string $ownerKey, Model $chunk): int
    {
        return (int) $class::query()
            ->where($parentKey, $chunk->{$parentKey})
            ->where($ownerKey, $chunk->{$ownerKey})
            ->max('chunk_index') + 1;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function keys(Model $chunk): array
    {
        return match ($chunk::class) {
            BatchDomainChunk::class => ['batch_id', 'domain_id'],
            WpBatchSiteChunk::class => ['wp_batch_id', 'wp_site_id'],
            CampaignDomainChunk::class => ['campaign_id', 'campaign_domain_id'],
            default => throw new \InvalidArgumentException('Unsupported chunk type: '.$chunk::class),
        };
    }
}
