<?php



namespace App\Jobs;



use App\Models\WpSite;

use App\Services\WpApiService;

use App\Support\WpBatchDeletionTracker;

use Illuminate\Bus\Queueable;

use Illuminate\Contracts\Queue\ShouldBeUnique;

use Illuminate\Contracts\Queue\ShouldQueue;

use Illuminate\Foundation\Bus\Dispatchable;

use Illuminate\Queue\InteractsWithQueue;

use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\Log;



class DeleteWpBatchSiteJob implements ShouldQueue, ShouldBeUnique

{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;



    public const QUEUE = 'delete_wp_batch_links';



    /** Allow long-running bulk deletes on slow WP sites. */

    public int $timeout = 1800;



    public int $tries = 1;



    public function __construct(

        public int $batchId,

        public int $siteId

    ) {

        $this->onQueue(self::QUEUE);

    }



    public function displayName(): string

    {

        return "Delete WP batch {$this->batchId} links on site {$this->siteId}";

    }



    public function uniqueId(): string

    {

        return 'delete-wp-batch-site-'.$this->batchId.'-'.$this->siteId;

    }



    public function handle(WpApiService $api): void

    {

        $site = WpSite::find($this->siteId);

        if (! $site) {

            WpBatchDeletionTracker::siteCompleted(

                $this->batchId,

                $this->siteId,

                false,

                'Site not found'

            );



            return;

        }



        $ping = $api->ping($site);

        if (! ($ping['ok'] ?? false)) {

            Log::warning('DeleteWpBatchSiteJob: skipping unresponsive site', [

                'wp_batch_id' => $this->batchId,

                'wp_site_id' => $this->siteId,

                'domain' => $site->domain,

                'error' => $ping['error'] ?? 'Health check failed',

            ]);



            WpBatchDeletionTracker::siteCompleted(

                $this->batchId,

                $this->siteId,

                false,

                $ping['error'] ?? 'Site did not respond to health check'

            );



            return;

        }



        try {

            $api->deleteLinksByBatchId($site, $this->batchId);

            WpBatchDeletionTracker::siteCompleted($this->batchId, $this->siteId, true);

        } catch (\Throwable $e) {

            Log::warning('DeleteWpBatchSiteJob: failed to delete links by batch_id on site', [

                'wp_batch_id' => $this->batchId,

                'wp_site_id' => $this->siteId,

                'domain' => $site->domain,

                'message' => $e->getMessage(),

            ]);



            WpBatchDeletionTracker::siteCompleted(

                $this->batchId,

                $this->siteId,

                false,

                $e->getMessage()

            );

        }

    }

}

