<?php



namespace App\Jobs;



use App\Models\WpBatch;

use App\Models\WpBatchSiteChunk;

use App\Support\PbnSettings;

use App\Support\WpBatchDeletionTracker;

use Illuminate\Bus\Queueable;

use Illuminate\Contracts\Queue\ShouldBeUnique;

use Illuminate\Contracts\Queue\ShouldQueue;

use Illuminate\Foundation\Bus\Dispatchable;

use Illuminate\Queue\InteractsWithQueue;

use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\Log;



/**

 * Orchestrates WP batch deletion: dispatches one DeleteWpBatchSiteJob per site,

 * then WpBatchDeletionTracker finalizes when all sites have been processed.

 */

class DeleteWpBatchJob implements ShouldQueue, ShouldBeUnique

{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;



    public const QUEUE = 'delete_wp_batch_links';



    public int $timeout = 120;



    public int $tries = 1;



    public function __construct(public WpBatch $batch)

    {

        $this->onQueue(self::QUEUE);

    }



    public function displayName(): string

    {

        return 'Delete WP batch (ID '.$this->batch->id.') from remote then local';

    }



    public function uniqueId(): string

    {

        return 'delete-wp-batch-'.$this->batch->id;

    }



    public function handle(): void

    {

        $batch = $this->batch->fresh();

        if (! $batch) {

            return;

        }



        $siteIds = WpBatchSiteChunk::where('wp_batch_id', $batch->id)

            ->pluck('wp_site_id')

            ->unique()

            ->values();



        if ($siteIds->isEmpty()) {

            $batch->delete();



            return;

        }



        WpBatchDeletionTracker::start($batch->id, $siteIds->count());

        $batch->update(['status' => 'deleting']);



        $delaySeconds = PbnSettings::getLinkDelaySeconds();

        foreach ($siteIds as $index => $siteId) {

            DeleteWpBatchSiteJob::dispatch($batch->id, (int) $siteId)

                ->delay(now()->addSeconds($index * $delaySeconds));

        }



        Log::info('DeleteWpBatchJob: queued per-site delete jobs', [

            'wp_batch_id' => $batch->id,

            'site_count' => $siteIds->count(),

            'delay_seconds' => $delaySeconds,

        ]);

    }



    public function failed(?\Throwable $e): void

    {

        if ($e) {

            Log::error('DeleteWpBatchJob failed', [

                'wp_batch_id' => $this->batch->id ?? null,

                'message' => $e->getMessage(),

                'exception' => $e::class,

            ]);

        }

    }

}

