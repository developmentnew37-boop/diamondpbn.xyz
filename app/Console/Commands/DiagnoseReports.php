<?php

namespace App\Console\Commands;

use App\Models\Batch;
use App\Models\BatchDomainChunk;
use App\Models\Domain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiagnoseReports extends Command
{
    protected $signature = 'reports:diagnose {batch_id? : Optional batch ID to inspect}';

    protected $description = 'Diagnose why Reports may be empty (chunks, links_count, domain joins)';

    public function handle(): int
    {
        $batchId = $this->argument('batch_id');

        $this->info('=== Reports diagnose ===');

        $totalChunks = BatchDomainChunk::count();
        $this->line("Total batch_domain_chunks: {$totalChunks}");

        if ($batchId) {
            $batch = Batch::find($batchId);
            if (! $batch) {
                $this->error("Batch #{$batchId} not found.");

                return self::FAILURE;
            }
            $this->line("Batch #{$batchId}: {$batch->name} (user_id={$batch->user_id})");
        }

        $chunkQuery = BatchDomainChunk::query();
        if ($batchId) {
            $chunkQuery->where('batch_id', $batchId);
        }

        $scopedChunks = (clone $chunkQuery)->count();
        $this->line('Chunks in scope: '.$scopedChunks);

        $orphaned = (clone $chunkQuery)
            ->leftJoin('domains', 'domains.id', '=', 'batch_domain_chunks.domain_id')
            ->whereNull('domains.id')
            ->count();
        $this->line("Chunks with missing domain row (orphaned): {$orphaned}");

        $withInnerJoin = (clone $chunkQuery)
            ->join('batches', 'batches.id', '=', 'batch_domain_chunks.batch_id')
            ->join('domains', 'domains.id', '=', 'batch_domain_chunks.domain_id')
            ->count('batch_domain_chunks.id');
        $this->line("Chunks visible with INNER JOIN domains (old report query): {$withInnerJoin}");

        $withLeftJoin = (clone $chunkQuery)
            ->join('batches', 'batches.id', '=', 'batch_domain_chunks.batch_id')
            ->leftJoin('domains', 'domains.id', '=', 'batch_domain_chunks.domain_id')
            ->count('batch_domain_chunks.id');
        $this->line("Chunks visible with LEFT JOIN domains (fixed report query): {$withLeftJoin}");

        $jsonLinks = (clone $chunkQuery)
            ->selectRaw('COALESCE(SUM(COALESCE(JSON_LENGTH(links_payload), 0)), 0) as total')
            ->value('total');
        $this->line("Total link rows (SUM JSON_LENGTH links_payload): {$jsonLinks}");

        if (Schema::hasColumn('batch_domain_chunks', 'links_count')) {
            $columnLinks = (clone $chunkQuery)
                ->selectRaw('COALESCE(SUM(COALESCE(links_count, 0)), 0) as total')
                ->value('total');
            $this->line("Total link rows (SUM links_count column): {$columnLinks}");

            $zeroCountWithPayload = (clone $chunkQuery)
                ->where('links_count', 0)
                ->whereRaw('COALESCE(JSON_LENGTH(links_payload), 0) > 0')
                ->count();
            $this->line("Chunks where links_count=0 but JSON payload has links: {$zeroCountWithPayload}");

            if ($zeroCountWithPayload > 0) {
                $this->warn('Run: php artisan batches:backfill-links-count');
            }
        } else {
            $this->warn('Column links_count missing — run: php artisan migrate');
        }

        $sample = (clone $chunkQuery)->first(['id', 'batch_id', 'domain_id', 'links_count', 'links_payload', 'status']);
        if ($sample) {
            $payloadCount = is_array($sample->links_payload) ? count($sample->links_payload) : 0;
            $this->line("Sample chunk #{$sample->id}: links_count={$sample->links_count}, php payload count={$payloadCount}, status={$sample->status}");
            if ($sample->domain_id) {
                $domainExists = Domain::whereKey($sample->domain_id)->exists();
                $this->line("Sample domain_id {$sample->domain_id} exists in domains table: ".($domainExists ? 'yes' : 'NO'));
            }
        }

        if ($jsonLinks > 0 && $withInnerJoin === 0 && $orphaned > 0) {
            $this->error('Root cause: domain rows were deleted but chunks remain. Deploy latest ReportController (LEFT JOIN) or restore domains.');
        } elseif ($jsonLinks === 0 && $scopedChunks > 0) {
            $this->error('Root cause: chunks exist but links_payload is empty.');
        } elseif ($jsonLinks > 0) {
            $this->info('Data is present — reports should work after deploying latest code + backfill.');
        }

        return self::SUCCESS;
    }
}
