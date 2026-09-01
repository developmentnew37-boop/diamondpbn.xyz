<?php

namespace App\Support;

use App\Models\BatchDomainChunk;
use App\Models\CampaignDomainChunk;
use App\Models\WpBatchSiteChunk;
use Illuminate\Database\Eloquent\Model;

class RetryFailedChunks
{
    /**
     * Move failed links from one chunk into new pending chunk(s). Returns how many links were queued.
     */
    public static function process(Model $chunk): int
    {
        [$parentKey, $ownerKey] = self::keys($chunk);
        $class = $chunk::class;

        $linksPayload = is_array($chunk->links_payload) ? $chunk->links_payload : [];
        $resultsPayload = is_array($chunk->results_payload) ? $chunk->results_payload : [];
        $failedIndices = method_exists($chunk, 'failedLinkIndices') ? $chunk->failedLinkIndices() : [];

        if ($failedIndices === []) {
            if ((int) ($chunk->failed_count ?? 0) > 0) {
                $chunk->update(['failed_count' => 0]);
            }

            return 0;
        }

        $failedLinks = array_values(array_filter(array_map(
            fn ($i) => $linksPayload[$i] ?? null,
            $failedIndices
        )));

        if ($failedLinks === []) {
            $chunk->update(['failed_count' => 0]);

            return 0;
        }

        $chunkSize = (int) constant($class.'::CHUNK_SIZE');
        $retryChunks = array_chunk($failedLinks, max(1, $chunkSize));
        $nextIndex = (int) $class::query()
            ->where($parentKey, $chunk->{$parentKey})
            ->where($ownerKey, $chunk->{$ownerKey})
            ->max('chunk_index');

        $total = 0;
        foreach ($retryChunks as $retryLinks) {
            $nextIndex++;
            $class::create([
                $parentKey => $chunk->{$parentKey},
                $ownerKey => $chunk->{$ownerKey},
                'chunk_index' => $nextIndex,
                'links_payload' => $retryLinks,
                'status' => $class::STATUS_PENDING,
            ]);
            $total += count($retryLinks);
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

            return $total;
        }

        $chunk->update([
            'links_payload' => $remainingLinks,
            'results_payload' => $remainingResults,
            'success_count' => count($remainingLinks),
            'failed_count' => 0,
            'status' => $class::STATUS_COMPLETED,
        ]);

        return $total;
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
