<?php



namespace App\Jobs;



use App\Models\Batch;

use App\Models\BatchDomainChunk;

use App\Models\Domain;

use App\Models\Link;

use App\Services\PbnApiService;

use Illuminate\Bus\Queueable;

use Illuminate\Contracts\Queue\ShouldBeUnique;

use Illuminate\Contracts\Queue\ShouldQueue;

use Illuminate\Foundation\Bus\Dispatchable;

use Illuminate\Queue\InteractsWithQueue;

use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\Log;



/**

 * When an admin deletes a link from the batch (trash icon in "All Links" table):

 * 1. Remove the link from ALL remote sites via DELETE /hidden-links/by-url (exact URL) per domain.

 * 2. Then remove that specific link from the batch (chunk payloads, counts, and link record).

 *

 * Queue: remove_link_from_batch (unique from batch_links, delete_batch_links, domains).

 */

class RemoveLinkFromBatchJob implements ShouldQueue, ShouldBeUnique

{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;



    public const QUEUE = 'remove_link_from_batch';



    public int $timeout = 600;



    public int $tries = 1;



    public function __construct(

        public Batch $batch,

        public Link $link

    ) {

        $this->onQueue(self::QUEUE);

    }



    public function displayName(): string

    {

        return 'Remove link from batch (batch ' . $this->batch->id . ', link ' . $this->link->id . ')';

    }



    public function uniqueId(): string

    {

        return 'remove-link-from-batch-' . $this->batch->id . '-' . $this->link->id;

    }



    public function handle(PbnApiService $api): void

    {

        $batch = $this->batch->fresh();

        $link = $this->link->fresh();

        if (!$batch || !$link || $link->batch_id !== $batch->id) {

            return;

        }



        $linkUrl = $link->url;

        $chunks = BatchDomainChunk::where('batch_id', $batch->id)->with('domain')->get();



        $domainsToUpdate = [];



        foreach ($chunks as $chunk) {

            $linksPayload = $chunk->links_payload ?? [];



            foreach ($linksPayload as $index => $linkData) {

                if (($linkData['url'] ?? '') === $linkUrl) {

                    if (! isset($domainsToUpdate[$chunk->domain_id])) {

                        $domainsToUpdate[$chunk->domain_id] = [

                            'domain' => $chunk->domain,

                            'chunks' => [],

                        ];

                    }



                    $domainsToUpdate[$chunk->domain_id]['chunks'][] = [

                        'chunk' => $chunk,

                        'index' => $index,

                    ];

                    break;

                }

            }

        }



        foreach ($domainsToUpdate as $domainData) {

            $domain = $domainData['domain'];



            if (! $domain || $linkUrl === '') {

                continue;

            }



            try {

                $api->deleteLinkByUrl($domain, $linkUrl);

            } catch (\Throwable $e) {

                Log::warning('RemoveLinkFromBatchJob: failed to delete link on domain', [

                    'batch_id' => $batch->id,

                    'link_id' => $link->id,

                    'domain_id' => $domain->id,

                    'domain_api_url' => $domain->api_url,

                    'url' => $linkUrl,

                    'message' => $e->getMessage(),

                ]);

            }

        }



        $processedToDecrement = 0;

        $successToDecrement = 0;

        $failedToDecrement = 0;



        foreach ($domainsToUpdate as $domainData) {

            foreach ($domainData['chunks'] as $chunkData) {

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

            Log::error('RemoveLinkFromBatchJob failed', [

                'batch_id' => $this->batch->id ?? null,

                'link_id' => $this->link->id ?? null,

                'message' => $e->getMessage(),

            ]);

        }

    }

}


