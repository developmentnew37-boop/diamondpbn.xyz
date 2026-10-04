<?php

namespace App\Http\Controllers;

use App\Jobs\DeleteCampaignLinksJob;
use App\Jobs\PublishCampaignChunkJob;
use App\Jobs\RetryFailedCampaignChunksJob;
use App\Models\Campaign;
use App\Models\CampaignDomain;
use App\Models\CampaignDomainChunk;
use App\Models\CampaignLink;
use App\Support\PbnSettings;
use App\Support\PublishPendingChunks;
use App\Support\ReplaceFailedChunkLink;
use App\Support\RunProgress;
use App\Support\UnicodeUrl;
use App\Support\Workspace;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $search = substr($search, 0, 100);

        $query = Workspace::scopeOwnPosting(Campaign::query());

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
                    ->orWhereHas('links', function ($linkQuery) use ($term) {
                        $linkQuery->where('url', 'like', $term)
                            ->orWhere('keyword', 'like', $term);
                    });
            });
        }

        $campaigns = $query
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('campaigns.index', compact('campaigns', 'search'));
    }

    public function create()
    {
        $baseQuery = CampaignDomain::where('user_id', Workspace::ownerId());
        $ids = (clone $baseQuery)
            ->selectRaw('MAX(id) as id')
            ->groupBy('domain_normalized')
            ->pluck('id');

        $dedupedQuery = CampaignDomain::whereIn('id', $ids);
        $targetDomainStats = [
            'active' => (clone $dedupedQuery)->where('status', 'active')->count(),
            'inactive' => (clone $dedupedQuery)->where('status', 'inactive')->count(),
            'error' => (clone $dedupedQuery)->where('status', 'error')->count(),
        ];

        $domains = (clone $dedupedQuery)
            ->where('status', 'active')
            ->orderBy('domain')
            ->get(['id', 'domain', 'api_url', 'status']);

        return view('campaigns.create', compact('domains', 'targetDomainStats'));
    }

    public function store(Request $request)
    {
        $linksInput = null;

        // Primary: manual bulk (links + keywords) - paired by line index
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
        $request->merge(['links' => UnicodeUrl::normalizePayload($linksInput)]);

        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'domain_ids' => 'required|array',
            'domain_ids.*' => 'exists:campaign_domains,id',
            'links' => 'required|array|min:1',
            'links.*.url' => 'required|url|max:2048',
            'links.*.keyword' => 'required|string',
            'links.*.no_follow' => 'nullable|boolean',
            'links_per_domain' => 'required|integer|min:1|max:1000',
        ]);

        $domainIds = array_values(array_unique(array_filter($validated['domain_ids'], fn ($id) => CampaignDomain::where('id', $id)->where('user_id', Workspace::ownerId())->exists())));
        if (empty($domainIds)) {
            return back()->withErrors(['domain_ids' => 'Select at least one campaign domain you own.'])->withInput();
        }

        $linksPerDomain = (int) $validated['links_per_domain'];
        $totalDomains = count($domainIds);
        $totalLinks = count($validated['links']);

        // Calculate total distributed links
        $totalDistributedLinks = $totalDomains * $linksPerDomain;

        // Create campaign
        $campaign = Campaign::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
            'total_links' => $totalLinks,
            'total_domains' => $totalDomains,
            'links_per_domain' => $linksPerDomain,
            'total_distributed_links' => $totalDistributedLinks,
        ]);

        // Save original links
        foreach ($validated['links'] as $linkData) {
            CampaignLink::create([
                'campaign_id' => $campaign->id,
                'user_id' => auth()->id(),
                'url' => $linkData['url'],
                'keyword' => $linkData['keyword'],
                'no_follow' => $linkData['no_follow'] ?? false,
            ]);
        }

        // Distribute links across domains
        $distributedLinks = $this->distributeLinks($validated['links'], $totalDomains, $linksPerDomain);

        // Create chunks for each domain
        $chunkSize = CampaignDomainChunk::CHUNK_SIZE;
        $chunks = [];

        foreach ($domainIds as $domainIndex => $domainId) {
            $domainLinks = $distributedLinks[$domainIndex] ?? [];

            // Convert to API format
            $domainLinksFormatted = array_map(fn ($l) => [
                'url' => $l['url'],
                'keyword' => $l['keyword'],
                'nofollow' => $l['no_follow'] ?? false,
            ], $domainLinks);

            // Split into chunks of 100
            $linkChunks = array_chunk($domainLinksFormatted, $chunkSize);

            foreach ($linkChunks as $chunkIndex => $chunkLinks) {
                $chunks[] = CampaignDomainChunk::create([
                    'campaign_id' => $campaign->id,
                    'campaign_domain_id' => $domainId,
                    'chunk_index' => $chunkIndex,
                    'links_payload' => $chunkLinks,
                    'status' => 'pending',
                ]);
            }
        }

        $campaign->update(['status' => 'processing', 'started_at' => now()]);

        // Dispatch jobs with delay
        $delaySeconds = PbnSettings::getLinkDelaySeconds();
        foreach ($chunks as $index => $chunk) {
            PublishCampaignChunkJob::dispatch($chunk)->delay(now()->addSeconds($index * $delaySeconds));
        }

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaign created. Links are being distributed and published to campaign domains via the queue.');
    }

    /**
     * Distribute links across domains with looping and remainder handling.
     *
     * @param array $links Original links array
     * @param int $totalDomains Number of domains
     * @param int $linksPerDomain Links each domain should receive
     * @return array Array of links per domain
     */
    private function distributeLinks(array $links, int $totalDomains, int $linksPerDomain): array
    {
        $totalNeeded = $totalDomains * $linksPerDomain;
        $totalAvailable = count($links);

        // Create expanded link pool by looping
        $expandedLinks = [];
        $linkIndex = 0;

        for ($i = 0; $i < $totalNeeded; $i++) {
            $expandedLinks[] = $links[$linkIndex % $totalAvailable];
            $linkIndex++;
        }

        // Distribute to domains
        $distribution = [];
        $currentIndex = 0;

        for ($domainIndex = 0; $domainIndex < $totalDomains; $domainIndex++) {
            $distribution[$domainIndex] = array_slice($expandedLinks, $currentIndex, $linksPerDomain);
            $currentIndex += $linksPerDomain;
        }

        return $distribution;
    }

    public function show(Request $request, Campaign $campaign)
    {
        Workspace::assertCanManageRun($campaign->user_id);

        $domainStats = $this->buildDomainStatsForCampaign($campaign);
        $problemDomainCount = collect($domainStats)->where('is_problem', true)->count();

        $links = $this->paginateCampaignLinks($request, $campaign);
        $hasPendingChunks = CampaignDomainChunk::where('campaign_id', $campaign->id)
            ->whereIn('status', [CampaignDomainChunk::STATUS_PENDING, CampaignDomainChunk::STATUS_PROCESSING])
            ->exists();
        $isRetrying = RunProgress::isRetrying('campaign', $campaign->id);
        $publishingCount = RunProgress::publishingCount('campaign', $campaign->id);

        if (! $hasPendingChunks && ! $isRetrying) {
            $campaign = $campaign->recalculateCounters(true);
            if ($publishingCount > 0) {
                RunProgress::clearPublishing('campaign', $campaign->id);
                $publishingCount = 0;
            }
        }

        return view('campaigns.show', compact(
            'campaign',
            'domainStats',
            'links',
            'hasPendingChunks',
            'problemDomainCount',
            'isRetrying',
            'publishingCount'
        ));
    }

    private function paginateCampaignLinks(Request $request, Campaign $campaign)
    {
        $search = substr(trim((string) $request->input('links_search', '')), 0, 100);
        $query = $campaign->links()->orderBy('id');

        if ($search !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);
            $term = '%'.$escaped.'%';
            $query->where(function ($q) use ($term) {
                $q->where('url', 'like', $term)
                    ->orWhere('keyword', 'like', $term);
            });
        }

        return $query
            ->paginate(100, ['id', 'campaign_id', 'url', 'keyword', 'no_follow'], 'links_page')
            ->withQueryString();
    }

    private function campaignChunkLinkCountExpr(): string
    {
        return 'COALESCE(JSON_LENGTH(campaign_domain_chunks.links_payload), 0)';
    }

    private function buildDomainStatsForCampaign(Campaign $campaign): array
    {
        $campaignSettled = in_array($campaign->status, ['completed', 'partial', 'failed', 'delete_failed'], true);

        $linkCountExpr = $this->campaignChunkLinkCountExpr();

        $rows = CampaignDomainChunk::query()
            ->where('campaign_domain_chunks.campaign_id', $campaign->id)
            ->join('campaign_domains', 'campaign_domains.id', '=', 'campaign_domain_chunks.campaign_domain_id')
            ->selectRaw(
                'campaign_domain_chunks.campaign_domain_id as domain_id,
                campaign_domains.domain,
                campaign_domains.status as domain_status,
                SUM('.$linkCountExpr.') as total,
                SUM(COALESCE(campaign_domain_chunks.success_count, 0)) as success,
                SUM(COALESCE(campaign_domain_chunks.failed_count, 0)) as failed,
                SUM(CASE
                    WHEN campaign_domain_chunks.status IN (?, ?)
                    THEN '.$linkCountExpr.'
                    ELSE 0
                END) as pending',
                [CampaignDomainChunk::STATUS_PENDING, CampaignDomainChunk::STATUS_PROCESSING]
            )
            ->groupBy('campaign_domain_chunks.campaign_domain_id', 'campaign_domains.domain', 'campaign_domains.status')
            ->orderBy('campaign_domains.domain')
            ->get();

        return $rows->map(function ($row) use ($campaignSettled) {
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
                'is_problem' => $failed > 0 || ($campaignSettled && $total > 0 && $success < $total),
            ];
        })->values()->all();
    }

    public function showDomain(Campaign $campaign, CampaignDomain $campaignDomain)
    {
        Workspace::assertCanManageRun($campaign->user_id);
        Workspace::assertInventoryOwned($campaignDomain->user_id);

        $chunks = CampaignDomainChunk::where('campaign_id', $campaign->id)
            ->where('campaign_domain_id', $campaignDomain->id)
            ->orderBy('chunk_index')
            ->get();

        if ($chunks->isEmpty()) {
            return back()->with('info', 'No data for this domain in this campaign.');
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
                    'chunk_status' => $chunk->status,
                    'link_index' => $i,
                ];
            }
        }

        return view('campaigns.domain', compact('campaign', 'campaignDomain', 'chunks', 'linksWithStatus'));
    }

    public function pause(Campaign $campaign)
    {
        Workspace::assertCanManageRun($campaign->user_id);

        if (! $campaign->canPause()) {
            return back()->with('error', 'This campaign cannot be paused in its current state.');
        }

        $campaign->update(['status' => 'paused']);

        return back()->with('success', 'Campaign paused. Remaining queued posts will wait until you resume. One chunk already in progress may still finish.');
    }

    public function resume(Campaign $campaign)
    {
        Workspace::assertCanManageRun($campaign->user_id);

        if (! $campaign->canResume()) {
            return back()->with('error', 'This campaign is not paused.');
        }

        $campaign->update(['status' => 'processing', 'started_at' => $campaign->started_at ?? now()]);

        return $this->publishPending($campaign);
    }

    public function replaceFailedLink(Request $request, Campaign $campaign)
    {
        Workspace::assertCanManageRun($campaign->user_id);

        $request->merge([
            'url' => UnicodeUrl::normalize((string) $request->input('url')),
        ]);

        $validated = $request->validate([
            'chunk_id' => 'required|integer',
            'link_index' => 'required|integer|min:0',
            'url' => 'required|url|max:2048',
            'keyword' => 'required|string',
        ]);

        $chunk = CampaignDomainChunk::where('campaign_id', $campaign->id)
            ->where('id', $validated['chunk_id'])
            ->firstOrFail();

        try {
            ReplaceFailedChunkLink::update(
                $chunk,
                (int) $validated['link_index'],
                $validated['url'],
                $validated['keyword']
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Failed link updated. Use Retry failed, then Publish pending, to post the new URL.');
    }

    public function retryFailed(Campaign $campaign)
    {
        Workspace::assertCanManageRun($campaign->user_id);

        if ($campaign->status === 'paused') {
            return back()->with('error', 'Resume the campaign before retrying failed links.');
        }

        if ($campaign->status === 'deleting') {
            return back()->with('error', 'Cannot retry failed links while campaign deletion is in progress.');
        }

        $failedChunks = CampaignDomainChunk::where('campaign_id', $campaign->id)->where('failed_count', '>', 0)->count();
        if ($failedChunks === 0) {
            return back()->with('info', 'No failed links to retry.');
        }

        if (! RunProgress::markRetrying('campaign', $campaign->id)) {
            return back()->with('info', 'Retry is already running. Refresh to see progress.');
        }

        RetryFailedCampaignChunksJob::dispatch($campaign->id);
        $campaign->update(['status' => 'processing']);

        return back()->with('success', 'Retrying '.number_format((int) ($campaign->failed_count ?? 0)).' failed link(s). Status is now Processing. Pending chunks publish automatically when retry finishes.');
    }

    public function publishPending(Campaign $campaign)
    {
        Workspace::assertCanManageRun($campaign->user_id);

        if ($campaign->status === 'deleting') {
            return back()->with('info', 'Cannot publish while campaign deletion is in progress.');
        }

        $queued = PublishPendingChunks::forCampaign($campaign);
        if ($queued === 0) {
            $campaign->recalculateCounters(true);

            return back()->with('info', 'No pending/stale chunks to publish.');
        }

        return back()->with('success', 'Publishing '.$queued.' pending/stale chunk(s). Status is now Processing. Refresh to see progress.');
    }

    public function destroyLink(Campaign $campaign, CampaignLink $link)
    {
        Workspace::assertCanManageRun($campaign->user_id);
        if ($link->campaign_id !== $campaign->id) {
            abort(403);
        }

        // Dispatch job to remove link from all remote sites
        \App\Jobs\RemoveLinkFromCampaignJob::dispatch($campaign, $link);

        return back()->with('success', 'Link removal queued. The link will be removed from all target domains shortly.');
    }

    public function destroyDomain(Campaign $campaign, CampaignDomain $campaignDomain)
    {
        Workspace::assertCanManageRun($campaign->user_id);
        Workspace::assertInventoryOwned($campaignDomain->user_id);

        if ($campaign->status === 'deleting') {
            return back()->with('info', 'Cannot remove domains while campaign deletion is in progress.');
        }

        $removed = $this->removeDomainsFromCampaign($campaign, [$campaignDomain->id]);
        if ($removed === 0) {
            return back()->with('info', 'This domain is not part of this campaign.');
        }

        return back()->with('success', 'Domain removed from campaign. Any links already posted will be cleaned from the remote site in the background (skipped if the site is down).');
    }

    public function removeProblemDomains(Campaign $campaign)
    {
        Workspace::assertCanManageRun($campaign->user_id);

        if ($campaign->status === 'deleting') {
            return back()->with('info', 'Cannot remove domains while campaign deletion is in progress.');
        }

        $domainIds = $this->problemCampaignDomainIds($campaign);
        if ($domainIds === []) {
            return back()->with('info', 'No problem domains found to remove.');
        }

        $removed = $this->removeDomainsFromCampaign($campaign, $domainIds);

        return back()->with('success', "Removed {$removed} problem domain(s) from the campaign. Remote cleanup was queued for live sites.");
    }

    /**
     * @param  array<int>  $campaignDomainIds
     */
    private function removeDomainsFromCampaign(Campaign $campaign, array $campaignDomainIds): int
    {
        $campaignDomainIds = array_values(array_unique(array_map('intval', $campaignDomainIds)));
        if ($campaignDomainIds === []) {
            return 0;
        }

        $ownedIds = CampaignDomain::where('user_id', Workspace::ownerId())
            ->whereIn('id', $campaignDomainIds)
            ->pluck('id')
            ->all();

        if ($ownedIds === []) {
            return 0;
        }

        $existingIds = CampaignDomainChunk::where('campaign_id', $campaign->id)
            ->whereIn('campaign_domain_id', $ownedIds)
            ->distinct()
            ->pluck('campaign_domain_id')
            ->all();

        if ($existingIds === []) {
            return 0;
        }

        foreach ($existingIds as $domainId) {
            DeleteCampaignLinksJob::dispatch($campaign->id, (int) $domainId);
        }

        CampaignDomainChunk::where('campaign_id', $campaign->id)
            ->whereIn('campaign_domain_id', $existingIds)
            ->delete();

        $this->refreshCampaignDomainTotals($campaign);

        return count($existingIds);
    }

    /** @return array<int> */
    private function problemCampaignDomainIds(Campaign $campaign): array
    {
        return collect($this->buildDomainStatsForCampaign($campaign))
            ->filter(fn (array $row) => $row['is_problem'] ?? false)
            ->pluck('domain_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function refreshCampaignDomainTotals(Campaign $campaign): void
    {
        $linkCountExpr = $this->campaignChunkLinkCountExpr();

        $remainingDomains = (int) CampaignDomainChunk::where('campaign_id', $campaign->id)
            ->distinct()
            ->count('campaign_domain_id');

        $totalDistributed = (int) CampaignDomainChunk::where('campaign_id', $campaign->id)
            ->selectRaw('COALESCE(SUM('.$linkCountExpr.'), 0) as aggregate_total')
            ->value('aggregate_total');

        $campaign->update([
            'total_domains' => $remainingDomains,
            'total_distributed_links' => $totalDistributed,
        ]);

        if ($remainingDomains === 0) {
            $campaign->update([
                'status' => 'completed',
                'completed_at' => now(),
                'processed_count' => 0,
                'success_count' => 0,
                'failed_count' => 0,
            ]);

            return;
        }

        $campaign->recalculateCounters();
    }

    public function destroy(Campaign $campaign)
    {
        Workspace::assertCanManageRun($campaign->user_id);

        if ($campaign->status === 'deleting') {
            return back()->with('info', 'Campaign deletion is already in progress. Run the queue worker (delete_campaign_links).');
        }

        \App\Jobs\DeleteCampaignJob::dispatch($campaign)->onQueue(\App\Jobs\DeleteCampaignJob::QUEUE);

        $message = match ($campaign->status) {
            'delete_failed' => 'Campaign delete re-queued. Remaining domains will be retried. Run the queue worker (delete_campaign_links).',
            'semi_deleted' => 'Campaign delete re-queued for remaining domains. Run the queue worker (delete_campaign_links).',
            default => 'Campaign delete queued. Links will be removed from live sites first; the campaign is kept if some domains fail. Run the queue worker (delete_campaign_links).',
        };

        return redirect()->route('campaigns.index')->with('success', $message);
    }
}
