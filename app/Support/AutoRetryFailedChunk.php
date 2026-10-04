<?php

namespace App\Support;

use App\Models\BatchDomainChunk;
use App\Models\CampaignDomainChunk;
use App\Models\WpBatchSiteChunk;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AutoRetryFailedChunk
{
    public static function afterPublish(Model $chunk, ?string $error = null): void
    {
        $chunk = $chunk->fresh();
        if (! $chunk || (int) ($chunk->failed_count ?? 0) < 1) {
            return;
        }

        $run = self::run($chunk);
        if (! $run || in_array($run->status, ['paused', 'deleting'], true)) {
            return;
        }

        if (self::isDeadSiteError($error ?? (string) ($chunk->error_message ?? ''))) {
            return;
        }

        if (! self::domainIsActive($chunk)) {
            return;
        }

        $onceKey = 'auto-retry-chunk:'.$chunk::class.':'.$chunk->id;
        if (! Cache::add($onceKey, 1, 1800)) {
            return;
        }

        try {
            $result = DB::transaction(function () use ($chunk) {
                $fresh = $chunk->fresh();
                if (! $fresh || (int) ($fresh->failed_count ?? 0) < 1) {
                    return ['count' => 0, 'ids' => []];
                }

                return RetryFailedChunks::process($fresh);
            });
        } catch (\Throwable $e) {
            Cache::forget($onceKey);
            throw $e;
        }

        if ($result['ids'] === []) {
            return;
        }

        foreach ($result['ids'] as $id) {
            Cache::put('auto-retry-chunk:'.$chunk::class.':'.$id, 1, 1800);
        }

        $type = self::type($chunk);
        PublishPendingChunks::dispatchIds($type, $result['ids'], 5);
        if ($type !== '') {
            RunProgress::markPublishing($type, (int) $run->id, count($result['ids']));
            $run->recalculateCounters(true);
            $fresh = $run->fresh();
            if ($fresh && ! in_array($fresh->status, ['paused', 'deleting', 'semi_deleted', 'delete_failed'], true)) {
                $fresh->update(['status' => 'processing']);
            }
        }
    }

    public static function isDeadSiteError(string $error): bool
    {
        $error = strtolower($error);
        if ($error === '') {
            return false;
        }

        foreach ([
            'connection failed',
            'connection timed out',
            'operation timed out',
            'timed out',
            'timeout',
            'could not resolve host',
            'name or service not known',
            'did not respond',
            'health check',
            'curl error',
            'failed to connect',
        ] as $needle) {
            if (str_contains($error, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function domainIsActive(Model $chunk): bool
    {
        return match ($chunk::class) {
            BatchDomainChunk::class => ($chunk->domain()->value('status') ?? '') === 'active',
            CampaignDomainChunk::class => ($chunk->campaignDomain()->value('status') ?? '') === 'active',
            WpBatchSiteChunk::class => ($chunk->wpSite()->value('status') ?? '') === 'active',
            default => false,
        };
    }

    private static function run(Model $chunk): ?Model
    {
        return match ($chunk::class) {
            BatchDomainChunk::class => $chunk->batch,
            CampaignDomainChunk::class => $chunk->campaign,
            WpBatchSiteChunk::class => $chunk->wpBatch,
            default => null,
        };
    }

    private static function type(Model $chunk): string
    {
        return match ($chunk::class) {
            BatchDomainChunk::class => 'batch',
            CampaignDomainChunk::class => 'campaign',
            WpBatchSiteChunk::class => 'wp-batch',
            default => '',
        };
    }
}
