<?php

namespace App\Console\Commands;

use App\Models\BatchDomainChunk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillBatchLinksCount extends Command
{
    protected $signature = 'batches:backfill-links-count';

    protected $description = 'Backfill batch_domain_chunks.links_count from links_payload (fixes empty Reports)';

    public function handle(): int
    {
        if (! Schema::hasColumn('batch_domain_chunks', 'links_count')) {
            $this->error('Column batch_domain_chunks.links_count does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $updated = DB::table('batch_domain_chunks')->update([
            'links_count' => DB::raw('COALESCE(JSON_LENGTH(links_payload), 0)'),
        ]);

        $withLinks = BatchDomainChunk::query()
            ->where('links_count', '>', 0)
            ->count();

        $this->info("Backfilled links_count on {$updated} chunk row(s). {$withLinks} chunk(s) now have links.");

        return self::SUCCESS;
    }
}
