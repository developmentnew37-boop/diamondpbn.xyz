<?php

namespace App\Console\Commands;

use App\Models\Batch;
use Illuminate\Console\Command;

class RecalculateBatchCounters extends Command
{
    protected $signature = 'batches:recalculate-counters {batch? : Optional batch ID}';

    protected $description = 'Re-sync batch processed/success/failed counts from chunk totals';

    public function handle(): int
    {
        $query = Batch::query();

        if ($this->argument('batch')) {
            $query->whereKey($this->argument('batch'));
        }

        $count = 0;
        $query->orderBy('id')->each(function (Batch $batch) use (&$count) {
            $before = $batch->only(['processed_count', 'success_count', 'failed_count', 'status']);
            $batch->recalculateCounters();
            $after = $batch->fresh()->only(['processed_count', 'success_count', 'failed_count', 'status']);

            $this->line("Batch #{$batch->id} ({$batch->name}): ".json_encode($before).' → '.json_encode($after));
            $count++;
        });

        $this->info("Recalculated {$count} batch(es).");

        return self::SUCCESS;
    }
}
