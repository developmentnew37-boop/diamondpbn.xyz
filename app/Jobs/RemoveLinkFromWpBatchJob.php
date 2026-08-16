<?php



namespace App\Jobs;



use App\Models\WpBatch;

use App\Models\WpBatchSiteChunk;

use App\Models\WpLink;

use App\Models\WpSite;

use App\Services\WpApiService;

use Illuminate\Bus\Queueable;

use Illuminate\Contracts\Queue\ShouldBeUnique;

use Illuminate\Contracts\Queue\ShouldQueue;

use Illuminate\Foundation\Bus\Dispatchable;

use Illuminate\Queue\InteractsWithQueue;

use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\Log;



/**

 * When an admin deletes a link from the WP batch (trash icon in "All Links" table):

 * 1. Remove the link from ALL remote sites via DELETE /hidden-links/by-url (exact URL) per site.

 * 2. Then remove that specific link from the batch (chunk payloads, counts, and link record).

 *

 * Queue: remove_link_from_wp_batch (unique from wp_batch_links, delete_wp_batch_links, wp_sites).

 */

class RemoveLinkFromWpBatchJob implements ShouldQueue, ShouldBeUnique

{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;



    public const QUEUE = 'remove_link_from_wp_batch';



    public int $timeout = 600;



    public int $tries = 1;



    public function __construct(

        public WpBatch $batch,

        public WpLink $link

    ) {

        $this->onQueue(self::QUEUE);

    }



    public function displayName(): string

    {

        return 'Remove link from WP batch (batch ' . $this->batch->id . ', link ' . $this->link->id . ')';

    }



    public function uniqueId(): string

    {

        return 'remove-link-from-wp-batch-' . $this->batch->id . '-' . $this->link->id;

    }



    public function handle(WpApiService $api): void

    {

        $batch = $this->batch->fresh();

        $link = $this->link->fresh();

        if (!$batch || !$link || $link->wp_batch_id !== $batch->id) {

            return;

        }



        $linkUrl = $link->url;

        $chunks = WpBatchSiteChunk::where('wp_batch_id', $batch->id)->with('wpSite')->get();



        $sitesToUpdate = [];



        foreach ($chunks as $chunk) {

            $linksPayload = $chunk->links_payload ?? [];



            foreach ($linksPayload as $index => $linkData) {

                if (($linkData['url'] ?? '') === $linkUrl) {

                    if (! isset($sitesToUpdate[$chunk->wp_site_id])) {

                        $sitesToUpdate[$chunk->wp_site_id] = [

                            'site' => $chunk->wpSite,

                            'chunks' => [],

                        ];

                    }



                    $sitesToUpdate[$chunk->wp_site_id]['chunks'][] = [

                        'chunk' => $chunk,

                        'index' => $index,

                    ];

                    break;

                }

            }

        }



        foreach ($sitesToUpdate as $siteData) {

            $site = $siteData['site'];



            if (! $site || $linkUrl === '') {

                continue;

            }



            try {

                $api->deleteLinkByUrl($site, $linkUrl);

            } catch (\Throwable $e) {

                Log::warning('RemoveLinkFromWpBatchJob: failed to delete link on site', [

                    'wp_batch_id' => $batch->id,

                    'wp_link_id' => $link->id,

                    'wp_site_id' => $site->id,

                    'domain_api_url' => $site->api_url,

                    'url' => $linkUrl,

                    'message' => $e->getMessage(),

                ]);

            }

        }



        $processedToDecrement = 0;

        $successToDecrement = 0;

        $failedToDecrement = 0;



        foreach ($sitesToUpdate as $siteData) {

            foreach ($siteData['chunks'] as $chunkData) {

                $chunk = $chunkData['chunk'];

                $index = $chunkData['index'];



                $linksPayload = $chunk->links_payload ?? [];

                $resultsPayload = $chunk->results_payload ?? [];



                $result = $resultsPayload[$index] ?? null;

                $status = $result['status'] ?? '';

                $wasSuccess = in_array($status, ['success', 'completed', 'posted'], true);

                $wasFailed = $result !== null && ! $wasSuccess && ! in_array($status, ['', 'pending', 'processing'], true);

                $wasProcessed = $wasSuccess || $wasFailed;



                if ($wasProcessed) {

                    $processedToDecrement++;

                }

                if ($wasSuccess) {

                    $successToDecrement++;

                } elseif ($wasFailed) {

                    $failedToDecrement++;

                }



                array_splice($linksPayload, $index, 1);

                array_splice($resultsPayload, $index, 1);



                $chunk->update([

                    'links_payload' => $linksPayload,

                    'results_payload' => $resultsPayload,

                    'success_count' => max(0, ($chunk->success_count ?? 0) - ($wasSuccess ? 1 : 0)),

                    'failed_count' => max(0, ($chunk->failed_count ?? 0) - ($wasFailed ? 1 : 0)),

                ]);

            }

        }



        $batch->decrement('total_links');

        if ($processedToDecrement > 0) {

            $batch->decrement('processed_count', $processedToDecrement);

        }

        if ($successToDecrement > 0) {

            $batch->decrement('success_count', $successToDecrement);

        }

        if ($failedToDecrement > 0) {

            $batch->decrement('failed_count', $failedToDecrement);

        }



        $batch->recalculateCounters();

        $link->delete();

    }



    public function failed(?\Throwable $e): void

    {

        if ($e) {

            Log::error('RemoveLinkFromWpBatchJob failed', [

                'wp_batch_id' => $this->batch->id ?? null,

                'wp_link_id' => $this->link->id ?? null,

                'message' => $e->getMessage(),

            ]);

        }

    }

}

