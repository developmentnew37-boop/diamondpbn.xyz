<?php

namespace App\Http\Controllers;

use App\Jobs\DeleteWpBatchJob;
use App\Jobs\DeleteWpBatchSiteJob;
use App\Jobs\PublishWpBatchChunkJob;
use App\Jobs\RemoveLinkFromWpBatchJob;
use App\Models\WpBatch;
use App\Models\WpBatchSiteChunk;
use App\Models\WpLink;
use App\Models\WpSite;
use App\Support\PbnSettings;
use App\Support\WpBatchDeletionTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WpBatchController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $search = substr($search, 0, 100);

        $query = WpBatch::where('user_id', auth()->id());

        if ($search !== '') {
            $escaped = str_replace(
                ['\\', '%', '_'],
                ['\\\\', '\%', '\_'],
                $search
            );
            $term = '%'.$escaped.'%';

            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('wpLinks', fn ($lq) => $lq->where('url', 'like', $term)
                        ->orWhere('keyword', 'like', $term)
                    );
            });
        }

        $wpBatches = $query
            ->withSum('wpBatchSiteChunks as chunks_success', 'success_count')
            ->withSum('wpBatchSiteChunks as chunks_failed', 'failed_count')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('wp-batches.index', compact('wpBatches', 'search'));
    }

    public function create()
    {
        $wpSites = WpSite::where('user_id', auth()->id())->where('status', 'active')->get();

        return view('wp-batches.create', compact('wpSites'));
    }

    public function store(Request $request)
    {
        $linksInput = null;

        $linksBulk = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $request->input('links_bulk', '')))));
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
            'wp_site_ids' => 'required|array',
            'wp_site_ids.*' => 'exists:wp_sites,id',
            'links' => 'required|array',
            'links.*.url' => 'required|url|max:2048',
            'links.*.keyword' => 'required|string',
            'links.*.no_follow' => 'nullable|boolean',
        ]);

        $wpSiteIds = array_values(array_unique(array_filter($validated['wp_site_ids'], fn ($id) => WpSite::where('id', $id)->where('user_id', auth()->id())->exists())));
        if (empty($wpSiteIds)) {
            return back()->withErrors(['wp_site_ids' => 'Select at least one WP site you own.'])->withInput();
        }

        $wpBatch = WpBatch::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
            'total_links' => count($validated['links']),
            'total_domains' => count($wpSiteIds),
        ]);

        foreach ($validated['links'] as $linkData) {
            WpLink::create([
                'wp_batch_id' => $wpBatch->id,
                'user_id' => auth()->id(),
                'url' => $linkData['url'],
                'keyword' => $linkData['keyword'],
                'no_follow' => $linkData['no_follow'] ?? false,
            ]);
        }

        $links = $wpBatch->wpLinks()->orderBy('id')->get();
        $linksArray = $links->map(fn ($l) => [
            'url' => $l->url,
            'keyword' => $l->keyword,
            'nofollow' => $l->no_follow,
        ])->toArray();

        $chunkSize = WpBatchSiteChunk::CHUNK_SIZE;
        $linkChunks = array_chunk($linksArray, $chunkSize);

        $chunks = [];
        foreach ($wpSiteIds as $wpSiteId) {
            foreach ($linkChunks as $chunkIndex => $chunkLinks) {
                $chunks[] = WpBatchSiteChunk::create([
                    'wp_batch_id' => $wpBatch->id,
                    'wp_site_id' => $wpSiteId,
                    'chunk_index' => $chunkIndex,
                    'links_payload' => $chunkLinks,
                    'status' => 'pending',
                ]);
            }
        }

        $wpBatch->update(['status' => 'processing', 'started_at' => now()]);

        $delaySeconds = PbnSettings::getLinkDelaySeconds();
        foreach ($chunks as $index => $chunk) {
            PublishWpBatchChunkJob::dispatch($chunk)->delay(now()->addSeconds($index * $delaySeconds));
        }

        return redirect()->route('wp-batches.show', $wpBatch)->with('success', 'WP batch created. Links are being published to remote sites via the queue. Run the queue worker (queue: wp_batch_links) and refresh to see progress.');
    }

    public function show(WpBatch $wpBatch)
    {
        if ($wpBatch->user_id !== auth()->id()) {
            abort(403);
        }

        $siteStats = $this->buildSiteStatsForBatch($wpBatch);
        $problemSiteCount = collect($siteStats)->where('is_problem', true)->count();

        $links = $wpBatch->wpLinks()->orderBy('id')->get(['id', 'wp_batch_id', 'url', 'keyword', 'no_follow']);
        $failedLinksLimit = 500;
        $failedLinks = $this->getFailedLinksForBatch($wpBatch, $failedLinksLimit);
        $failedLinksTotal = (int) ($wpBatch->failed_count ?? 0);
        $failedLinksTruncated = $failedLinksTotal > count($failedLinks);
        $hasPendingChunks = WpBatchSiteChunk::where('wp_batch_id', $wpBatch->id)
            ->whereIn('status', [WpBatchSiteChunk::STATUS_PENDING, WpBatchSiteChunk::STATUS_PROCESSING])
            ->exists();

        return view('wp-batches.show', compact(
            'wpBatch',
            'siteStats',
            'links',
            'failedLinks',
            'failedLinksTotal',
            'failedLinksTruncated',
            'hasPendingChunks',
            'problemSiteCount'
        ));
    }

    public function exportDomains(WpBatch $wpBatch, Request $request)
    {
        if ($wpBatch->user_id !== auth()->id()) {
            abort(403);
        }

        $filter = $request->query('filter', 'all');
        if (! in_array($filter, ['all', 'success', 'pending', 'failed'], true)) {
            $filter = 'all';
        }

        $siteStats = $this->buildSiteStatsForBatch($wpBatch);

        if ($filter !== 'all') {
            $siteStats = array_values(array_filter($siteStats, function (array $row) use ($filter) {
                return match ($filter) {
                    'success' => ($row['success'] ?? 0) > 0,
                    'pending' => ($row['pending'] ?? 0) > 0,
                    'failed' => ($row['failed'] ?? 0) > 0,
                    default => true,
                };
            }));
        }

        usort($siteStats, fn ($a, $b) => strcasecmp($a['domain'] ?? '', $b['domain'] ?? ''));

        $lines = [['Domain', 'Total Links', 'Success', 'Failed', 'Pending', 'Overall Status']];
        foreach ($siteStats as $row) {
            $lines[] = [
                $row['domain'] ?? '',
                $row['total'] ?? 0,
                $row['success'] ?? 0,
                $row['failed'] ?? 0,
                $row['pending'] ?? 0,
                $this->siteOverallStatus($row),
            ];
        }

        $fh = fopen('php://temp', 'r+');
        foreach ($lines as $line) {
            fputcsv($fh, $line);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        $slug = Str::slug($wpBatch->name ?? 'wp-batch-'.$wpBatch->id) ?: 'wp-batch-'.$wpBatch->id;
        $filterSuffix = $filter === 'all' ? '' : '-'.$filter;
        $filename = 'wp-batch-'.$wpBatch->id.'-'.$slug.'-domains'.$filterSuffix.'-'.now()->format('Ymd-His').'.csv';

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function showDomain(WpBatch $wpBatch, WpSite $wpSite)
    {
        if ($wpBatch->user_id !== auth()->id() || $wpSite->user_id !== auth()->id()) {
            abort(403);
        }

        $chunks = WpBatchSiteChunk::where('wp_batch_id', $wpBatch->id)
            ->where('wp_site_id', $wpSite->id)
            ->orderBy('chunk_index')
            ->get();

        if ($chunks->isEmpty()) {
            return back()->with('info', 'No data for this site in this batch.');
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

        $batchLinks = $wpBatch->wpLinks()->orderBy('id')->get();

        return view('wp-batches.domain', compact('wpBatch', 'wpSite', 'chunks', 'linksWithStatus', 'batchLinks'));
    }

    public function publishPending(WpBatch $wpBatch)
    {
        if ($wpBatch->user_id !== auth()->id()) {
            abort(403);
        }

        $staleBefore = now()->subMinutes(10);

        $candidates = WpBatchSiteChunk::where('wp_batch_id', $wpBatch->id)
            ->where(function ($q) use ($staleBefore) {
                $q->where('status', WpBatchSiteChunk::STATUS_PENDING)
                    ->orWhere(function ($qq) use ($staleBefore) {
                        $qq->where('status', WpBatchSiteChunk::STATUS_PROCESSING)
                            ->where(function ($qqq) use ($staleBefore) {
                                $qqq->whereNotNull('error_message')
                                    ->orWhereNull('sent_at')
                                    ->orWhere('sent_at', '<', $staleBefore);
                            });
                    });
            })
            ->orderBy('wp_site_id')
            ->orderBy('chunk_index')
            ->get();

        if ($candidates->isEmpty()) {
            return back()->with('info', 'No pending/stale chunks to publish.');
        }

        WpBatchSiteChunk::whereIn('id', $candidates->pluck('id'))
            ->update(['status' => WpBatchSiteChunk::STATUS_PENDING]);

        $pending = WpBatchSiteChunk::where('wp_batch_id', $wpBatch->id)
            ->where('status', WpBatchSiteChunk::STATUS_PENDING)
            ->orderBy('wp_site_id')
            ->orderBy('chunk_index')
            ->get();

        $wpBatch->update(['status' => 'processing', 'started_at' => $wpBatch->started_at ?? now()]);

        $delaySeconds = PbnSettings::getLinkDelaySeconds();
        foreach ($pending as $index => $chunk) {
            PublishWpBatchChunkJob::dispatch($chunk)->delay(now()->addSeconds($index * $delaySeconds));
        }

        return back()->with('success', 'Queued '.$pending->count().' pending/stale chunk(s). Run the queue worker (wp_batch_links) and refresh to see progress.');
    }

    private function buildSiteStatsForBatch(WpBatch $wpBatch): array
    {
        $batchSettled = in_array($wpBatch->status, ['completed', 'partial', 'failed', 'delete_failed'], true);

        $linkCountExpr = 'CASE WHEN COALESCE(wp_batch_site_chunks.links_count, 0) > 0'
            .' THEN wp_batch_site_chunks.links_count'
            .' ELSE COALESCE(JSON_LENGTH(wp_batch_site_chunks.links_payload), 0)'
            .' END';

        $rows = WpBatchSiteChunk::query()
            ->where('wp_batch_site_chunks.wp_batch_id', $wpBatch->id)
            ->join('wp_sites', 'wp_sites.id', '=', 'wp_batch_site_chunks.wp_site_id')
            ->selectRaw(
                'wp_batch_site_chunks.wp_site_id,
                wp_sites.domain,
                wp_sites.status as domain_status,
                SUM('.$linkCountExpr.') as total,
                SUM(COALESCE(wp_batch_site_chunks.success_count, 0)) as success,
                SUM(COALESCE(wp_batch_site_chunks.failed_count, 0)) as failed,
                SUM(CASE
                    WHEN wp_batch_site_chunks.status IN (?, ?)
                    THEN '.$linkCountExpr.'
                    ELSE 0
                END) as pending',
                [WpBatchSiteChunk::STATUS_PENDING, WpBatchSiteChunk::STATUS_PROCESSING]
            )
            ->groupBy('wp_batch_site_chunks.wp_site_id', 'wp_sites.domain', 'wp_sites.status')
            ->orderBy('wp_sites.domain')
            ->get();

        return $rows->map(function ($row) use ($batchSettled) {
            $success = (int) $row->success;
            $failed = (int) $row->failed;
            $pending = (int) $row->pending;
            $total = (int) $row->total;

            return [
                'wp_site_id' => (int) $row->wp_site_id,
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

    private function siteOverallStatus(array $row): string
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
     * @return array<int, object{wpSite: ?WpSite, url: string, keyword: string, error_message: string}>
     */
    private function getFailedLinksForBatch(WpBatch $wpBatch, int $limit = 500): array
    {
        if ($limit < 1) {
            return [];
        }

        $failed = [];
        $query = WpBatchSiteChunk::query()
            ->where('wp_batch_id', $wpBatch->id)
            ->where('failed_count', '>', 0)
            ->with('wpSite:id,domain')
            ->orderBy('wp_site_id')
            ->orderBy('chunk_index')
            ->select([
                'id',
                'wp_site_id',
                'chunk_index',
                'links_payload',
                'results_payload',
                'failed_count',
            ]);

        foreach ($query->cursor() as $chunk) {
            $results = $chunk->results_payload ?? [];
            $links = $chunk->links_payload ?? [];
            $wpSite = $chunk->wpSite;
            foreach ($chunk->failedLinkIndices() as $i) {
                $r = $results[$i] ?? [];
                $linkData = $links[$i] ?? [];
                $failed[] = (object) [
                    'wpSite' => $wpSite,
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

    public function destroyLink(WpBatch $wpBatch, WpLink $wpLink)
    {
        if ($wpBatch->user_id !== auth()->id() || $wpLink->wp_batch_id !== $wpBatch->id) {
            abort(403);
        }

        $orderedLinks = $wpBatch->wpLinks()->orderBy('id')->get();
        $linkIndex = $orderedLinks->search(fn ($l) => $l->id === $wpLink->id);
        if ($linkIndex === false) {
            return back()->with('error', 'Link not found in batch.');
        }

        RemoveLinkFromWpBatchJob::dispatch($wpBatch, $wpLink);

        return back()->with('success', 'Link removal queued. The link will be deleted from all remote sites first, then removed from this batch. Run the queue worker (queue: remove_link_from_wp_batch).');
    }

    public function retryFailed(WpBatch $wpBatch)
    {
        if ($wpBatch->user_id !== auth()->id()) {
            abort(403);
        }

        $chunks = WpBatchSiteChunk::where('wp_batch_id', $wpBatch->id)->where('failed_count', '>', 0)->get();
        $totalRetrying = 0;
        foreach ($chunks as $chunk) {
            $linksPayload = $chunk->links_payload ?? [];
            $resultsPayload = $chunk->results_payload ?? [];
            $failedIndices = $chunk->failedLinkIndices();
            if (empty($failedIndices)) {
                continue;
            }
            $failedLinks = array_values(array_filter(array_map(fn ($i) => $linksPayload[$i] ?? null, $failedIndices)));
            $retryChunks = array_chunk($failedLinks, WpBatchSiteChunk::CHUNK_SIZE);
            foreach ($retryChunks as $retryIndex => $retryLinks) {
                WpBatchSiteChunk::create([
                    'wp_batch_id' => $wpBatch->id,
                    'wp_site_id' => $chunk->wp_site_id,
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
                if (WpBatchSiteChunk::isFailedLinkResult($r)) {
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
                'status' => $remainingFailed > 0 ? WpBatchSiteChunk::STATUS_PARTIAL : WpBatchSiteChunk::STATUS_COMPLETED,
            ]);
        }
        if ($totalRetrying === 0) {
            return back()->with('info', 'No failed links to retry.');
        }

        $wpBatch->recalculateCounters();
        $wpBatch->update(['status' => 'processing']);

        return back()->with('success', 'Retrying '.$totalRetrying.' failed link(s). Use "Publish pending chunks" to send them.');
    }

    public function destroyDomain(WpBatch $wpBatch, WpSite $wpSite)
    {
        if ($wpBatch->user_id !== auth()->id() || $wpSite->user_id !== auth()->id()) {
            abort(403);
        }

        if ($wpBatch->status === 'deleting') {
            return back()->with('info', 'Cannot remove sites while batch deletion is in progress.');
        }

        $removed = $this->removeSitesFromBatch($wpBatch, [$wpSite->id]);
        if ($removed === 0) {
            return back()->with('info', 'This site is not part of this batch.');
        }

        return back()->with('success', 'Site removed from batch. Any links already posted will be cleaned from the remote site in the background (skipped if the site is down).');
    }

    public function removeProblemDomains(WpBatch $wpBatch)
    {
        if ($wpBatch->user_id !== auth()->id()) {
            abort(403);
        }

        if ($wpBatch->status === 'deleting') {
            return back()->with('info', 'Cannot remove sites while batch deletion is in progress.');
        }

        $wpSiteIds = $this->problemBatchSiteIds($wpBatch);
        if ($wpSiteIds === []) {
            return back()->with('info', 'No problem sites found to remove.');
        }

        $removed = $this->removeSitesFromBatch($wpBatch, $wpSiteIds);

        return back()->with('success', "Removed {$removed} problem site(s) from the batch. Remote cleanup was queued for live sites.");
    }

    /**
     * @param  array<int>  $wpSiteIds
     */
    private function removeSitesFromBatch(WpBatch $wpBatch, array $wpSiteIds): int
    {
        $wpSiteIds = array_values(array_unique(array_map('intval', $wpSiteIds)));
        if ($wpSiteIds === []) {
            return 0;
        }

        $ownedIds = WpSite::where('user_id', auth()->id())
            ->whereIn('id', $wpSiteIds)
            ->pluck('id')
            ->all();

        if ($ownedIds === []) {
            return 0;
        }

        $existingIds = WpBatchSiteChunk::where('wp_batch_id', $wpBatch->id)
            ->whereIn('wp_site_id', $ownedIds)
            ->distinct()
            ->pluck('wp_site_id')
            ->all();

        if ($existingIds === []) {
            return 0;
        }

        foreach ($existingIds as $wpSiteId) {
            DeleteWpBatchSiteJob::dispatch($wpBatch->id, (int) $wpSiteId);
        }

        WpBatchSiteChunk::where('wp_batch_id', $wpBatch->id)
            ->whereIn('wp_site_id', $existingIds)
            ->delete();

        $this->refreshBatchSiteTotals($wpBatch);

        return count($existingIds);
    }

    /** @return array<int> */
    private function problemBatchSiteIds(WpBatch $wpBatch): array
    {
        return collect($this->buildSiteStatsForBatch($wpBatch))
            ->filter(fn (array $row) => $row['is_problem'] ?? false)
            ->pluck('wp_site_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function refreshBatchSiteTotals(WpBatch $wpBatch): void
    {
        $remainingSites = (int) WpBatchSiteChunk::where('wp_batch_id', $wpBatch->id)
            ->distinct()
            ->count('wp_site_id');

        $wpBatch->update(['total_domains' => $remainingSites]);

        if ($remainingSites === 0) {
            WpBatchDeletionTracker::forget($wpBatch->id);
            $wpBatch->update([
                'status' => 'completed',
                'completed_at' => now(),
                'processed_count' => 0,
                'success_count' => 0,
                'failed_count' => 0,
            ]);

            return;
        }

        $wpBatch->recalculateCounters();
    }

    public function destroy(WpBatch $wpBatch)
    {
        if ($wpBatch->user_id !== auth()->id()) {
            abort(403);
        }

        $hasSiteChunks = WpBatchSiteChunk::where('wp_batch_id', $wpBatch->id)->exists();

        if (! $hasSiteChunks) {
            WpBatchDeletionTracker::forget($wpBatch->id);
            $wpBatch->delete();

            return redirect()->route('wp-batches.index')->with('success', 'WP batch deleted.');
        }

        if ($wpBatch->status === 'deleting') {
            return back()->with('info', 'Batch deletion is already in progress. Run the queue worker (delete_wp_batch_links).');
        }

        DeleteWpBatchJob::dispatch($wpBatch)->onQueue('delete_wp_batch_links');

        $message = match ($wpBatch->status) {
            'delete_failed' => 'Batch delete re-queued. Remaining sites will be retried. Run the queue worker (delete_wp_batch_links).',
            'semi_deleted' => 'Batch delete re-queued for remaining sites. Run the queue worker (delete_wp_batch_links).',
            default => 'Batch delete queued. Links will be removed from live sites first; the batch is kept if some sites fail. Run the queue worker (delete_wp_batch_links).',
        };

        return redirect()->route('wp-batches.index')->with('success', $message);
    }
}
