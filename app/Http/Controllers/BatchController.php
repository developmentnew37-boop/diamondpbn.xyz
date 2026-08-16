<?php

namespace App\Http\Controllers;

use App\Support\BatchDeletionTracker;
use App\Jobs\DeleteBatchDomainJob;
use App\Jobs\DeleteBatchJob;
use App\Jobs\PublishBatchChunkJob;
use App\Jobs\RemoveLinkFromBatchJob;
use App\Models\Batch;
use App\Models\BatchDomainChunk;
use App\Models\Domain;
use App\Models\Link;
use App\Services\PbnApiService;
use App\Support\PbnSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BatchController extends Controller
{
    //     public function index(Request $request)
    //     {
    //         $query = Batch::where('user_id', auth()->id());
    //         $search = trim((string) $request->input('search', ''));
    //         if ($search !== '') {
    //             $term = '%' . $search . '%';
    //             $query->where(function ($q) use ($term) {
    //                 $q->where('name', 'like', $term)
    //                     ->orWhere('description', 'like', $term)
    //                     ->orWhereHas('links', fn($lq) => $lq->where('url', 'like', $term)->orWhere('keyword', 'like', $term));
    //             });
    //         }
    //         $batches = $query->latest()->get();
    //         return view('batches.index', compact('batches', 'search'));
    //     }

    public function index(Request $request)
    {
        // 1. Validate and limit input length
        $search = trim((string) $request->input('search', ''));
        $search = substr($search, 0, 100); // max 100 chars

        $query = Batch::where('user_id', auth()->id());

        if ($search !== '') {
            // 2. Escape SQL wildcard characters to prevent wildcard abuse
            $escaped = str_replace(
                ['\\', '%', '_'],
                ['\\\\', '\%', '\_'],
                $search
            );
            $term = '%'.$escaped.'%';

            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('links', fn ($lq) => $lq->where('url', 'like', $term)
                        ->orWhere('keyword', 'like', $term)
                    );
            });
        }

        $batches = $query
            ->withSum('batchDomainChunks as chunks_success', 'success_count')
            ->withSum('batchDomainChunks as chunks_failed', 'failed_count')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('batches.index', compact('batches', 'search'));
    }

    public function create()
    {
        $domains = Domain::where('user_id', auth()->id())->where('status', 'active')->get();

        return view('batches.create', compact('domains'));
    }

    public function store(Request $request)
    {

        $linksInput = null;

        // Primary: manual bulk (links + keywords) - paired by line index, ensures 1 link per URL+keyword pair
        $linksBulk = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $request->input('links_bulk', ''))))); // array values
        $keywordsBulk = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $request->input('keywords_bulk', '')))));
        if (! empty($linksBulk) && ! empty($keywordsBulk)) {
            if (count($linksBulk) !== count($keywordsBulk)) {
                return back()->withErrors([
                    'links_bulk' => 'Links and keywords must have the same number of lines ('.count($linksBulk).' vs '.count($keywordsBulk).').',
                ])->withInput();
            }
            $linksInput = [];
            foreach ($linksBulk as $i => $url) {
                $linksInput[] = [
                    'url' => $url,
                    'keyword' => $keywordsBulk[$i],
                    'no_follow' => false,
                ];
            }
        }

        if ($linksInput === null) {
            $linksInput = $request->input('links');
            if (is_string($linksInput)) {
                $linksInput = json_decode($linksInput, true) ?: [];
            }
            $linksInput = is_array($linksInput) ? $linksInput : [];
        }
        $request->merge(['links' => $linksInput]);

        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'domain_ids' => 'required|array',
            'domain_ids.*' => 'exists:domains,id',
            'links' => 'required|array',
            'links.*.url' => 'required|url|max:2048',
            'links.*.keyword' => 'required|string',
            'links.*.no_follow' => 'nullable|boolean',
        ]);

        $domainIds = array_values(array_unique(array_filter($validated['domain_ids'], fn ($id) => Domain::where('id', $id)->where('user_id', auth()->id())->exists())));
        if (empty($domainIds)) {
            return back()->withErrors(['domain_ids' => 'Select at least one domain you own.'])->withInput();
        }

        $batch = Batch::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
            'total_links' => count($validated['links']),
            'total_domains' => count($domainIds),
        ]);

        foreach ($validated['links'] as $linkData) {
            Link::create([
                'batch_id' => $batch->id,
                'user_id' => auth()->id(),
                'url' => $linkData['url'],
                'keyword' => $linkData['keyword'],
                'no_follow' => $linkData['no_follow'] ?? false,
            ]);
        }

        $links = $batch->links()->orderBy('id')->get();
        $linksArray = $links->map(fn ($l) => [
            'url' => $l->url,
            'keyword' => $l->keyword,
            'nofollow' => $l->no_follow,
        ])->toArray();

        $chunkSize = BatchDomainChunk::CHUNK_SIZE;
        $linkChunks = array_chunk($linksArray, $chunkSize);

        $chunks = [];
        foreach ($domainIds as $domainId) {
            foreach ($linkChunks as $chunkIndex => $chunkLinks) {
                $chunks[] = BatchDomainChunk::create([
                    'batch_id' => $batch->id,
                    'domain_id' => $domainId,
                    'chunk_index' => $chunkIndex,
                    'links_payload' => $chunkLinks,
                    'status' => 'pending',
                ]);
            }
        }

        $batch->update(['status' => 'processing', 'started_at' => now()]);

        $delaySeconds = PbnSettings::getLinkDelaySeconds();
        foreach ($chunks as $index => $chunk) {
            PublishBatchChunkJob::dispatch($chunk)->delay(now()->addSeconds($index * $delaySeconds));
        }

        return redirect()->route('batches.show', $batch)->with('success', 'Batch created. Links are being published to remote domains via the queue. Run the queue worker (queue: batch_links) and refresh to see progress.');
    }

    public function show(Batch $batch)
    {
        if ($batch->user_id !== auth()->id()) {
            abort(403);
        }

        $domainStats = $this->buildDomainStatsForBatch($batch);
        $problemDomainCount = collect($domainStats)->where('is_problem', true)->count();

        $links = $batch->links()->orderBy('id')->get(['id', 'batch_id', 'url', 'keyword', 'no_follow']);
        $failedLinksLimit = 500;
        $failedLinks = $this->getFailedLinksForBatch($batch, $failedLinksLimit);
        $failedLinksTotal = (int) ($batch->failed_count ?? 0);
        $failedLinksTruncated = $failedLinksTotal > count($failedLinks);
        $hasPendingChunks = BatchDomainChunk::where('batch_id', $batch->id)
            ->whereIn('status', [BatchDomainChunk::STATUS_PENDING, BatchDomainChunk::STATUS_PROCESSING])
            ->exists();

        return view('batches.show', compact(
            'batch',
            'domainStats',
            'links',
            'failedLinks',
            'failedLinksTotal',
            'failedLinksTruncated',
            'hasPendingChunks',
            'problemDomainCount'
        ));
    }

    public function exportDomains(Batch $batch, Request $request)
    {
        if ($batch->user_id !== auth()->id()) {
            abort(403);
        }

        $filter = $request->query('filter', 'all');
        if (! in_array($filter, ['all', 'success', 'pending', 'failed'], true)) {
            $filter = 'all';
        }

        $domainStats = $this->buildDomainStatsForBatch($batch);

        if ($filter !== 'all') {
            $domainStats = array_values(array_filter($domainStats, function (array $row) use ($filter) {
                return match ($filter) {
                    'success' => ($row['success'] ?? 0) > 0,
                    'pending' => ($row['pending'] ?? 0) > 0,
                    'failed' => ($row['failed'] ?? 0) > 0,
                    default => true,
                };
            }));
        }

        usort($domainStats, fn ($a, $b) => strcasecmp($a['domain'] ?? '', $b['domain'] ?? ''));

        $lines = [['Domain', 'Total Links', 'Success', 'Failed', 'Pending', 'Overall Status']];
        foreach ($domainStats as $row) {
            $lines[] = [
                $row['domain'] ?? '',
                $row['total'] ?? 0,
                $row['success'] ?? 0,
                $row['failed'] ?? 0,
                $row['pending'] ?? 0,
                $this->domainOverallStatus($row),
            ];
        }

        $fh = fopen('php://temp', 'r+');
        foreach ($lines as $line) {
            fputcsv($fh, $line);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        $slug = Str::slug($batch->name ?? 'batch-' . $batch->id) ?: 'batch-' . $batch->id;
        $filterSuffix = $filter === 'all' ? '' : '-' . $filter;
        $filename = 'batch-' . $batch->id . '-' . $slug . '-domains' . $filterSuffix . '-' . now()->format('Ymd-His') . '.csv';

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /** View all links and chunks for a specific domain in a batch */
    public function showDomain(Batch $batch, Domain $domain)
    {
        if ($batch->user_id !== auth()->id() || $domain->user_id !== auth()->id()) {
            abort(403);
        }

        $chunks = BatchDomainChunk::where('batch_id', $batch->id)
            ->where('domain_id', $domain->id)
            ->orderBy('chunk_index')
            ->get();

        if ($chunks->isEmpty()) {
            return back()->with('info', 'No data for this domain in this batch.');
        }

        $linksWithStatus = [];
        foreach ($chunks as $chunk) {
            $linksPayload = $chunk->links_payload ?? [];
            $resultsPayload = $chunk->results_payload ?? [];
            foreach ($linksPayload as $i => $linkData) {
                $result = $resultsPayload[$i] ?? null;
                $status = $result['status'] ?? ($chunk->status === 'pending' || $chunk->status === 'processing' ? 'pending' : '-');
                $linksWithStatus[] = [
                    'url' => $linkData['url'] ?? '-',
                    'keyword' => $linkData['keyword'] ?? '-',
                    'nofollow' => $linkData['nofollow'] ?? false,
                    'status' => $status,
                    'remote_post_id' => $result['remote_post_id'] ?? null,
                    'error' => $result['error'] ?? $result['error_message'] ?? null,
                    'chunk_index' => $chunk->chunk_index,
                    'chunk_id' => $chunk->id,
                ];
            }
        }

        $batchLinks = $batch->links()->orderBy('id')->get();

        return view('batches.domain', compact('batch', 'domain', 'chunks', 'linksWithStatus', 'batchLinks'));
    }

    /** Queue publish jobs for all chunks that are still pending (fixes stuck batches). */
    public function publishPending(Batch $batch)
    {
        if ($batch->user_id !== auth()->id()) {
            abort(403);
        }

        // Some chunks can get "stuck" in processing (worker killed mid-request, timeout, etc.).
        // Re-queue:
        // - anything pending
        // - anything processing with an error_message
        // - anything processing that has been processing for a while (stale)
        $staleBefore = now()->subMinutes(10);

        $candidates = BatchDomainChunk::where('batch_id', $batch->id)
            ->where(function ($q) use ($staleBefore) {
                $q->where('status', BatchDomainChunk::STATUS_PENDING)
                    ->orWhere(function ($qq) use ($staleBefore) {
                        $qq->where('status', BatchDomainChunk::STATUS_PROCESSING)
                            ->where(function ($qqq) use ($staleBefore) {
                                $qqq->whereNotNull('error_message')
                                    ->orWhereNull('sent_at')
                                    ->orWhere('sent_at', '<', $staleBefore);
                            });
                    });
            })
            ->orderBy('domain_id')
            ->orderBy('chunk_index')
            ->get();

        if ($candidates->isEmpty()) {
            return back()->with('info', 'No pending/stale chunks to publish.');
        }

        // Ensure all candidates are pending so the job will run (job ignores non-pending chunks).
        BatchDomainChunk::whereIn('id', $candidates->pluck('id'))
            ->update(['status' => BatchDomainChunk::STATUS_PENDING]);

        $pending = BatchDomainChunk::where('batch_id', $batch->id)
            ->where('status', BatchDomainChunk::STATUS_PENDING)
            ->orderBy('domain_id')
            ->orderBy('chunk_index')
            ->get();

        $batch->update(['status' => 'processing', 'started_at' => $batch->started_at ?? now()]);

        $delaySeconds = PbnSettings::getLinkDelaySeconds();
        foreach ($pending as $index => $chunk) {
            PublishBatchChunkJob::dispatch($chunk)->delay(now()->addSeconds($index * $delaySeconds));
        }

        return back()->with('success', 'Queued '.$pending->count().' pending/stale chunk(s). Run the queue worker (batch_links) and refresh to see progress.');
    }

    /**
     * Per-domain stats without loading links_payload / results_payload (safe for large batches).
     */
    private function buildDomainStatsForBatch(Batch $batch): array
    {
        $batchSettled = in_array($batch->status, ['completed', 'partial', 'failed', 'delete_failed'], true);

        $linkCountExpr = 'CASE WHEN COALESCE(batch_domain_chunks.links_count, 0) > 0'
            .' THEN batch_domain_chunks.links_count'
            .' ELSE COALESCE(JSON_LENGTH(batch_domain_chunks.links_payload), 0)'
            .' END';

        $rows = BatchDomainChunk::query()
            ->where('batch_domain_chunks.batch_id', $batch->id)
            ->join('domains', 'domains.id', '=', 'batch_domain_chunks.domain_id')
            ->selectRaw(
                'batch_domain_chunks.domain_id,
                domains.domain,
                domains.status as domain_status,
                SUM('.$linkCountExpr.') as total,
                SUM(COALESCE(batch_domain_chunks.success_count, 0)) as success,
                SUM(COALESCE(batch_domain_chunks.failed_count, 0)) as failed,
                SUM(CASE
                    WHEN batch_domain_chunks.status IN (?, ?)
                    THEN '.$linkCountExpr.'
                    ELSE 0
                END) as pending',
                [BatchDomainChunk::STATUS_PENDING, BatchDomainChunk::STATUS_PROCESSING]
            )
            ->groupBy('batch_domain_chunks.domain_id', 'domains.domain', 'domains.status')
            ->orderBy('domains.domain')
            ->get();

        return $rows->map(function ($row) use ($batchSettled) {
            $success = (int) $row->success;
            $failed = (int) $row->failed;
            $pending = (int) $row->pending;
            $total = (int) $row->total;

            return [
                'domain_id' => (int) $row->domain_id,
                'domain' => $row->domain ?? 'N/A',
                'domain_status' => $row->domain_status ?? 'unknown',
                'total' => $total,
                'success' => $success,
                'failed' => $failed,
                'pending' => $pending,
                'is_problem' => $failed > 0 || ($batchSettled && $total > 0 && $success < $total),
            ];
        })->values()->all();
    }

    private function domainOverallStatus(array $row): string
    {
        $total = (int) ($row['total'] ?? 0);
        $success = (int) ($row['success'] ?? 0);
        $failed = (int) ($row['failed'] ?? 0);
        $pending = (int) ($row['pending'] ?? 0);

        if ($total === 0) {
            return 'empty';
        }
        if ($success === $total) {
            return 'success';
        }
        if ($pending === $total) {
            return 'pending';
        }
        if ($failed === $total) {
            return 'failed';
        }
        if ($pending > 0) {
            return 'in_progress';
        }

        return 'partial';
    }

    /**
     * @return array<int, object{domain: ?Domain, url: string, keyword: string, error_message: string}>
     */
    private function getFailedLinksForBatch(Batch $batch, int $limit = 500): array
    {
        if ($limit < 1) {
            return [];
        }

        $failed = [];
        $query = BatchDomainChunk::query()
            ->where('batch_id', $batch->id)
            ->where('failed_count', '>', 0)
            ->with('domain:id,domain')
            ->orderBy('domain_id')
            ->orderBy('chunk_index')
            ->select([
                'id',
                'domain_id',
                'chunk_index',
                'links_payload',
                'results_payload',
                'failed_count',
            ]);

        foreach ($query->cursor() as $chunk) {
            $results = $chunk->results_payload ?? [];
            $links = $chunk->links_payload ?? [];
            $domain = $chunk->domain;
            foreach ($chunk->failedLinkIndices() as $i) {
                $r = $results[$i] ?? [];
                $linkData = $links[$i] ?? [];
                $failed[] = (object) [
                    'domain' => $domain,
                    'url' => $linkData['url'] ?? '-',
                    'keyword' => $linkData['keyword'] ?? '-',
                    'error_message' => $r['error'] ?? $r['error_message'] ?? 'Failed',
                ];
                if (count($failed) >= $limit) {
                    return $failed;
                }
            }
        }

        return $failed;
    }

    public function destroyLink(Batch $batch, Link $link)
    {
        if ($batch->user_id !== auth()->id() || $link->batch_id !== $batch->id) {
            abort(403);
        }

        $orderedLinks = $batch->links()->orderBy('id')->get();
        $linkIndex = $orderedLinks->search(fn ($l) => $l->id === $link->id);
        if ($linkIndex === false) {
            return back()->with('error', 'Link not found in batch.');
        }

        RemoveLinkFromBatchJob::dispatch($batch, $link);

        return back()->with('success', 'Link removal queued. The link will be deleted from all remote sites first, then removed from this batch. Run the queue worker (queue: remove_link_from_batch).');
    }

    public function retryFailed(Batch $batch)
    {
        if ($batch->user_id !== auth()->id()) {
            abort(403);
        }

        $chunks = BatchDomainChunk::where('batch_id', $batch->id)->where('failed_count', '>', 0)->get();
        $totalRetrying = 0;
        foreach ($chunks as $chunk) {
            $linksPayload = $chunk->links_payload ?? [];
            $resultsPayload = $chunk->results_payload ?? [];
            $failedIndices = $chunk->failedLinkIndices();
            if (empty($failedIndices)) {
                continue;
            }
            $failedLinks = array_values(array_filter(array_map(fn ($i) => $linksPayload[$i] ?? null, $failedIndices)));
            $retryChunks = array_chunk($failedLinks, BatchDomainChunk::CHUNK_SIZE);
            foreach ($retryChunks as $retryIndex => $retryLinks) {
                BatchDomainChunk::create([
                    'batch_id' => $batch->id,
                    'domain_id' => $chunk->domain_id,
                    'chunk_index' => $chunk->chunk_index + 1000 + $retryIndex,
                    'links_payload' => $retryLinks,
                    'status' => 'pending',
                ]);
                $totalRetrying += count($retryLinks);
            }

            $remainingLinks = array_values(array_filter($linksPayload, fn ($v, $k) => ! in_array($k, $failedIndices, true), ARRAY_FILTER_USE_BOTH));
            $remainingResults = array_values(array_filter($resultsPayload, fn ($v, $k) => ! in_array($k, $failedIndices, true), ARRAY_FILTER_USE_BOTH));

            if ($remainingLinks === []) {
                $chunk->delete();
                continue;
            }

            $remainingSuccess = 0;
            $remainingFailed = 0;
            foreach ($remainingResults as $r) {
                if (BatchDomainChunk::isFailedLinkResult($r)) {
                    $remainingFailed++;
                } else {
                    $remainingSuccess++;
                }
            }

            $chunk->update([
                'links_payload' => $remainingLinks,
                'results_payload' => $remainingResults,
                'success_count' => $remainingSuccess,
                'failed_count' => $remainingFailed,
                'status' => $remainingFailed > 0 ? BatchDomainChunk::STATUS_PARTIAL : BatchDomainChunk::STATUS_COMPLETED,
            ]);
        }
        if ($totalRetrying === 0) {
            return back()->with('info', 'No failed links to retry.');
        }

        $batch->recalculateCounters();
        $batch->update(['status' => 'processing']);

        return back()->with('success', 'Retrying '.$totalRetrying.' failed link(s). Use "Publish pending chunks" to send them.');
    }

    public function destroyDomain(Batch $batch, Domain $domain)
    {
        if ($batch->user_id !== auth()->id() || $domain->user_id !== auth()->id()) {
            abort(403);
        }

        if ($batch->status === 'deleting') {
            return back()->with('info', 'Cannot remove domains while batch deletion is in progress.');
        }

        $removed = $this->removeDomainsFromBatch($batch, [$domain->id]);
        if ($removed === 0) {
            return back()->with('info', 'This domain is not part of this batch.');
        }

        return back()->with('success', 'Domain removed from batch. Any links already posted will be cleaned from the remote site in the background (skipped if the site is down).');
    }

    public function removeProblemDomains(Batch $batch)
    {
        if ($batch->user_id !== auth()->id()) {
            abort(403);
        }

        if ($batch->status === 'deleting') {
            return back()->with('info', 'Cannot remove domains while batch deletion is in progress.');
        }

        $domainIds = $this->problemBatchDomainIds($batch);
        if ($domainIds === []) {
            return back()->with('info', 'No problem domains found to remove.');
        }

        $removed = $this->removeDomainsFromBatch($batch, $domainIds);

        return back()->with('success', "Removed {$removed} problem domain(s) from the batch. Remote cleanup was queued for live sites.");
    }

    /**
     * @param  array<int>  $domainIds
     */
    private function removeDomainsFromBatch(Batch $batch, array $domainIds): int
    {
        $domainIds = array_values(array_unique(array_map('intval', $domainIds)));
        if ($domainIds === []) {
            return 0;
        }

        $ownedIds = Domain::where('user_id', auth()->id())
            ->whereIn('id', $domainIds)
            ->pluck('id')
            ->all();

        if ($ownedIds === []) {
            return 0;
        }

        $existingIds = BatchDomainChunk::where('batch_id', $batch->id)
            ->whereIn('domain_id', $ownedIds)
            ->distinct()
            ->pluck('domain_id')
            ->all();

        if ($existingIds === []) {
            return 0;
        }

        foreach ($existingIds as $domainId) {
            DeleteBatchDomainJob::dispatch($batch->id, (int) $domainId);
        }

        BatchDomainChunk::where('batch_id', $batch->id)
            ->whereIn('domain_id', $existingIds)
            ->delete();

        $this->refreshBatchDomainTotals($batch);

        return count($existingIds);
    }

    /** @return array<int> */
    private function problemBatchDomainIds(Batch $batch): array
    {
        return collect($this->buildDomainStatsForBatch($batch))
            ->filter(fn (array $row) => $row['is_problem'] ?? false)
            ->pluck('domain_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function refreshBatchDomainTotals(Batch $batch): void
    {
        $remainingDomains = (int) BatchDomainChunk::where('batch_id', $batch->id)
            ->distinct()
            ->count('domain_id');

        $batch->update(['total_domains' => $remainingDomains]);

        if ($remainingDomains === 0) {
            BatchDeletionTracker::forget($batch->id);
            $batch->update([
                'status' => 'completed',
                'completed_at' => now(),
                'processed_count' => 0,
                'success_count' => 0,
                'failed_count' => 0,
            ]);

            return;
        }

        $batch->recalculateCounters();
    }

    public function destroy(Batch $batch)
    {
        if ($batch->user_id !== auth()->id()) {
            abort(403);
        }

        $hasDomainChunks = BatchDomainChunk::where('batch_id', $batch->id)->exists();

        // No domains left in this batch — delete locally now (no remote API calls needed).
        if (! $hasDomainChunks) {
            BatchDeletionTracker::forget($batch->id);
            $batch->delete();

            return redirect()->route('batches.index')->with('success', 'Batch deleted.');
        }

        if ($batch->status === 'deleting') {
            return back()->with('info', 'Batch deletion is already in progress. Run the queue worker (delete_batch_links).');
        }

        DeleteBatchJob::dispatch($batch)->onQueue('delete_batch_links');

        $message = match ($batch->status) {
            'delete_failed' => 'Batch delete re-queued. Remaining domains will be retried. Run the queue worker (delete_batch_links).',
            'semi_deleted' => 'Batch delete re-queued for remaining domains. Run the queue worker (delete_batch_links).',
            default => 'Batch delete queued. Links will be removed from live sites first; the batch is kept if some domains fail. Run the queue worker (delete_batch_links).',
        };

        return redirect()->route('batches.index')->with('success', $message);
    }
}
