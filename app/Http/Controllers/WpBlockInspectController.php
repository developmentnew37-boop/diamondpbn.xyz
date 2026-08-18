<?php

namespace App\Http\Controllers;

use App\Jobs\ToggleWpInspectJob;
use App\Models\WpSite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class WpBlockInspectController extends Controller
{
    private const INSPECT_FILTERS = ['all', 'on', 'off', 'unknown', 'unsupported'];

    public function index(Request $request)
    {
        $userId = auth()->id();
        $search = substr(trim((string) $request->query('search', '')), 0, 100);
        $inspectFilter = (string) $request->query('inspect', 'all');
        if (! in_array($inspectFilter, self::INSPECT_FILTERS, true)) {
            $inspectFilter = 'all';
        }

        $baseQuery = WpSite::where('user_id', $userId);
        $stats = $this->buildStats($baseQuery);
        $filterCounts = $this->buildFilterCounts($baseQuery);

        $sitesQuery = clone $baseQuery;
        $this->applySearch($sitesQuery, $search);
        $this->applyInspectFilter($sitesQuery, $inspectFilter);

        $sites = $sitesQuery
            ->orderBy('domain')
            ->paginate(50)
            ->withQueryString();

        $listQuery = array_filter([
            'search' => $search !== '' ? $search : null,
            'inspect' => $inspectFilter !== 'all' ? $inspectFilter : null,
        ], fn ($v) => $v !== null && $v !== '');

        return view('wp-sites.block-inspect', compact(
            'sites',
            'stats',
            'search',
            'inspectFilter',
            'filterCounts',
            'listQuery',
        ));
    }

    public function toggleAll(Request $request)
    {
        $validated = $request->validate([
            'block_inspect' => 'required|boolean',
        ]);

        $sites = WpSite::where('user_id', auth()->id())
            ->where('status', 'active')
            ->where('block_inspect_supported', true)
            ->get();

        if ($sites->isEmpty()) {
            return $this->redirectBack($request)->with('error', 'No active supported WP sites found.');
        }

        $queued = $this->dispatchToggleJobs($sites, (bool) $validated['block_inspect']);

        return $this->redirectBack($request)->with('success', $this->queuedMessage($queued, (bool) $validated['block_inspect']));
    }

    public function toggleSelected(Request $request)
    {
        $validated = $request->validate([
            'block_inspect' => 'required|boolean',
            'wp_site_ids' => 'required|array|min:1',
            'wp_site_ids.*' => 'integer|exists:wp_sites,id',
        ]);

        $sites = WpSite::where('user_id', auth()->id())
            ->whereIn('id', $validated['wp_site_ids'])
            ->where('block_inspect_supported', true)
            ->get();

        if ($sites->isEmpty()) {
            return $this->redirectBack($request)->with('error', 'No supported sites selected.');
        }

        $skipped = count($validated['wp_site_ids']) - $sites->count();
        $queued = $this->dispatchToggleJobs($sites, (bool) $validated['block_inspect']);
        $message = $this->queuedMessage($queued, (bool) $validated['block_inspect']);

        if ($skipped > 0) {
            $message .= " {$skipped} site(s) skipped (unsupported or not owned).";
        }

        return $this->redirectBack($request)->with('success', $message);
    }

    public function toggleManual(Request $request)
    {
        $validated = $request->validate([
            'block_inspect' => 'required|boolean',
            'domains' => 'required|string|max:50000',
        ]);

        $lines = array_values(array_unique(array_filter(array_map(
            fn ($line) => WpSite::normalizeDomain(trim($line)),
            preg_split('/\r\n|\r|\n/', (string) $validated['domains']) ?: []
        ))));

        if ($lines === []) {
            return $this->redirectBack($request)->with('error', 'Enter at least one domain.');
        }

        $sitesByDomain = WpSite::where('user_id', auth()->id())
            ->whereIn('domain_normalized', $lines)
            ->get()
            ->keyBy('domain_normalized');

        $toQueue = collect();
        $notFound = 0;
        $unsupported = 0;
        $inactive = 0;

        foreach ($lines as $normalized) {
            $site = $sitesByDomain->get($normalized);
            if (! $site) {
                $notFound++;

                continue;
            }
            if (! $site->block_inspect_supported) {
                $unsupported++;

                continue;
            }
            if ($site->status !== 'active') {
                $inactive++;

                continue;
            }
            $toQueue->push($site);
        }

        if ($toQueue->isEmpty()) {
            return $this->redirectBack($request)->with('error', $this->manualResultMessage(0, $notFound, $unsupported, $inactive, (bool) $validated['block_inspect'], true));
        }

        $queued = $this->dispatchToggleJobs($toQueue, (bool) $validated['block_inspect']);

        return $this->redirectBack($request)->with('success', $this->manualResultMessage($queued, $notFound, $unsupported, $inactive, (bool) $validated['block_inspect'], false));
    }

    public function toggleSite(Request $request, WpSite $wpSite)
    {
        if ($wpSite->user_id !== auth()->id()) {
            abort(403);
        }

        if (! $wpSite->block_inspect_supported) {
            return $this->redirectBack($request)->with('error', 'This site does not support inspect blocking (plugin v1.3.0+ required).');
        }

        $validated = $request->validate([
            'block_inspect' => 'required|boolean',
        ]);

        ToggleWpInspectJob::dispatch($wpSite, (bool) $validated['block_inspect']);

        $action = $validated['block_inspect'] ? 'enable' : 'disable';

        return $this->redirectBack($request)->with('success', "Queued inspect blocking {$action} for {$wpSite->domain}. Refresh after the wp_sites worker runs.");
    }

    /**
     * @param  Builder<WpSite>  $query
     * @return array<string, int>
     */
    private function buildStats(Builder $query): array
    {
        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('status', 'active')->count(),
            'blocking_on' => (clone $query)->where('block_inspect', true)->count(),
            'blocking_off' => (clone $query)->where('block_inspect', false)->count(),
            'unknown' => (clone $query)->where('block_inspect_supported', true)->whereNull('block_inspect')->count(),
            'unsupported' => (clone $query)->where('block_inspect_supported', false)->count(),
        ];
    }

    /**
     * @param  Builder<WpSite>  $query
     * @return array<string, int>
     */
    private function buildFilterCounts(Builder $query): array
    {
        return [
            'all' => (clone $query)->count(),
            'on' => (clone $query)->where('block_inspect_supported', true)->where('block_inspect', true)->count(),
            'off' => (clone $query)->where('block_inspect_supported', true)->where('block_inspect', false)->count(),
            'unknown' => (clone $query)->where('block_inspect_supported', true)->whereNull('block_inspect')->count(),
            'unsupported' => (clone $query)->where('block_inspect_supported', false)->count(),
        ];
    }

    private function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $term = '%'.$search.'%';
        $query->where(function ($q) use ($term) {
            $q->where('domain', 'like', $term)
                ->orWhere('api_url', 'like', $term);
        });
    }

    private function applyInspectFilter(Builder $query, string $inspectFilter): void
    {
        match ($inspectFilter) {
            'on' => $query->where('block_inspect_supported', true)->where('block_inspect', true),
            'off' => $query->where('block_inspect_supported', true)->where('block_inspect', false),
            'unknown' => $query->where('block_inspect_supported', true)->whereNull('block_inspect'),
            'unsupported' => $query->where('block_inspect_supported', false),
            default => null,
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<int, WpSite>  $sites
     */
    private function dispatchToggleJobs($sites, bool $blockInspect): int
    {
        foreach ($sites as $site) {
            ToggleWpInspectJob::dispatch($site, $blockInspect);
        }

        return $sites->count();
    }

    private function queuedMessage(int $count, bool $blockInspect): string
    {
        $action = $blockInspect ? 'enable' : 'disable';

        return "Queued {$count} site(s) to {$action} inspect blocking. Refresh after the wp_sites worker runs.";
    }

    private function manualResultMessage(int $queued, int $notFound, int $unsupported, int $inactive, bool $blockInspect, bool $failedOnly): string
    {
        if ($failedOnly) {
            $parts = ['No sites were queued.'];
        } else {
            $action = $blockInspect ? 'enable' : 'disable';
            $parts = ["Queued {$queued} site(s) to {$action} inspect blocking."];
        }

        if ($notFound > 0) {
            $parts[] = "{$notFound} not found in your WP Sites.";
        }
        if ($unsupported > 0) {
            $parts[] = "{$unsupported} unsupported (plugin v1.3.0+ required).";
        }
        if ($inactive > 0) {
            $parts[] = "{$inactive} skipped (not active).";
        }

        return implode(' ', $parts);
    }

    private function redirectBack(Request $request)
    {
        $params = array_filter([
            'search' => trim((string) $request->input('search', $request->query('search', ''))) ?: null,
            'inspect' => in_array($request->input('inspect', $request->query('inspect', 'all')), self::INSPECT_FILTERS, true)
                && $request->input('inspect', $request->query('inspect', 'all')) !== 'all'
                ? $request->input('inspect', $request->query('inspect'))
                : null,
        ], fn ($v) => $v !== null && $v !== '');

        return redirect()->route('wp-sites.block-inspect', $params);
    }
}
