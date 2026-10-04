<?php

namespace App\Http\Controllers;

use App\Jobs\ImportWpSitesJob;
use App\Jobs\WpSiteHealthCheckJob;
use App\Models\WpSite;
use App\Models\WpSiteCategory;
use App\Models\WpSiteImport;
use App\Rules\SafeApiUrl;
use App\Support\ApiUrlHelper;
use App\Support\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class WpSiteController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $statusFilter = (string) $request->query('status', 'all');
        if (! in_array($statusFilter, ['all', 'active', 'inactive', 'error'], true)) {
            $statusFilter = 'all';
        }
        $categoryFilter = $this->categoryFilter($request);

        $baseQuery = WpSite::where('user_id', Workspace::ownerId());
        if ($search !== '') {
            $term = '%'.$search.'%';
            $baseQuery->where(function ($q) use ($term) {
                $q->where('domain', 'like', $term)
                    ->orWhere('api_url', 'like', $term)
                    ->orWhere('notes', 'like', $term);
            });
        }

        $ids = (clone $baseQuery)
            ->selectRaw('MAX(id) as id')
            ->groupBy('domain_normalized')
            ->pluck('id');

        $dedupedQuery = WpSite::whereIn('id', $ids);

        $statusCounts = [
            'all' => (clone $dedupedQuery)->count(),
            'active' => (clone $dedupedQuery)->where('status', 'active')->count(),
            'inactive' => (clone $dedupedQuery)->where('status', 'inactive')->count(),
            'error' => (clone $dedupedQuery)->where('status', 'error')->count(),
        ];

        if ($statusFilter === 'active') {
            $dedupedQuery->where('status', 'active');
        } elseif ($statusFilter === 'inactive') {
            $dedupedQuery->where('status', 'inactive');
        } elseif ($statusFilter === 'error') {
            $dedupedQuery->where('status', 'error');
        }

        if ($categoryFilter === 'uncategorized') {
            $dedupedQuery->whereNull('wp_site_category_id');
        } elseif (is_numeric($categoryFilter)) {
            $dedupedQuery->where('wp_site_category_id', (int) $categoryFilter);
        }

        $wpSites = $dedupedQuery
            ->with('wpSiteCategory')
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $imports = WpSiteImport::where('user_id', Workspace::ownerId())
            ->withCount('wpSites')
            ->latest()
            ->limit(20)
            ->get();

        $categories = WpSiteCategory::catalog()
            ->withCount(['wpSites' => fn ($q) => $q->where('user_id', Workspace::ownerId())])
            ->get();

        return view('wp-sites.index', [
            'wpSites' => $wpSites,
            'imports' => $imports,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'statusCounts' => $statusCounts,
            'categories' => $categories,
            'categoryFilter' => $categoryFilter,
        ]);
    }

    public function store(Request $request)
    {
        $userId = Workspace::ownerId();
        $validated = $request->validate([
            'domain' => 'required|string',
            'api_url' => ['required', 'url', new SafeApiUrl()],
            'api_key' => 'nullable|string',
            'notes' => 'nullable|string',
            'wp_site_category_id' => ['nullable', 'integer', Rule::exists('wp_site_categories', 'id')],
        ]);

        $normalized = WpSite::normalizeDomain((string) $validated['domain']);
        if (WpSite::where('user_id', $userId)->where('domain_normalized', $normalized)->exists()) {
            return back()->withErrors(['domain' => 'This site already exists.'])->withInput();
        }

        $validated['user_id'] = $userId;
        $validated['domain'] = $normalized;
        $validated['domain_normalized'] = $normalized;
        $validated['api_url'] = ApiUrlHelper::restApiBase($validated['api_url']);
        $validated['status'] = 'inactive';
        $validated['wp_site_category_id'] = $validated['wp_site_category_id'] ?? null;

        $wpSite = WpSite::create($validated);
        WpSiteHealthCheckJob::dispatch($wpSite);

        return back()->with('success', 'WP site added. Health check queued.');
    }

    public function edit(WpSite $wpSite)
    {
        if ($wpSite->user_id !== Workspace::ownerId()) {
            abort(403);
        }

        $categories = WpSiteCategory::catalog()->get();

        return view('wp-sites.edit', compact('wpSite', 'categories'));
    }

    public function import(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
            'wp_site_category_id' => ['nullable', 'integer', Rule::exists('wp_site_categories', 'id')],
        ]);

        $path = $request->file('file')->store('imports');
        $import = WpSiteImport::create([
            'user_id' => Workspace::ownerId(),
            'filename' => $path,
            'status' => 'pending',
        ]);

        ImportWpSitesJob::dispatch($import, $validated['wp_site_category_id'] ?? null)->onQueue('import_wp_sites');

        return redirect()->route('wp-sites.index')->with('success', 'Import queued. Run the wp sites queue worker to process.');
    }

    public function importSample()
    {
        $csv = "Domain,API URL,API Key,Category\n"
            ."example.com,http://example.com/wp-json/pbn-hidden-link-manager/v1,,Casino\n"
            ."anotherdomain.com,http://anotherdomain.com/wp-json/pbn-hidden-link-manager/v1,,Casino\n";

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="wp-sites-import-sample.csv"');
    }

    public function update(Request $request, WpSite $wpSite)
    {
        if ($wpSite->user_id !== Workspace::ownerId()) {
            abort(403);
        }
        $validated = $request->validate([
            'domain' => 'required|string',
            'api_url' => ['required', 'url', new SafeApiUrl()],
            'api_key' => 'nullable|string',
            'notes' => 'nullable|string',
            'wp_site_category_id' => ['nullable', 'integer', Rule::exists('wp_site_categories', 'id')],
        ]);
        $validated['wp_site_category_id'] = $validated['wp_site_category_id'] ?? null;
        $normalized = WpSite::normalizeDomain((string) $validated['domain']);
        $exists = WpSite::where('user_id', Workspace::ownerId())
            ->where('domain_normalized', $normalized)
            ->where('id', '!=', $wpSite->id)
            ->exists();
        if ($exists) {
            return back()->withErrors(['domain' => 'This site already exists.'])->withInput();
        }

        $validated['domain'] = $normalized;
        $validated['domain_normalized'] = $normalized;
        $validated['api_url'] = ApiUrlHelper::restApiBase($validated['api_url']);
        $wpSite->update($validated);

        return redirect()->route('wp-sites.index')->with('success', 'WP site updated.');
    }

    public function destroy(WpSite $wpSite)
    {
        if ($wpSite->user_id !== Workspace::ownerId()) {
            abort(403);
        }
        $wpSite->delete();

        return back()->with('success', 'WP site and all related data deleted.');
    }

    public function recheck(WpSite $wpSite)
    {
        if ($wpSite->user_id !== Workspace::ownerId()) {
            abort(403);
        }
        WpSiteHealthCheckJob::dispatch($wpSite);

        return back()->with('success', 'Health check queued for '.$wpSite->domain.'. Run the queue worker, then refresh — if the check succeeds, status will show Active.');
    }

    public function checkAllHealth(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $statusFilter = (string) $request->query('status', 'all');
        if (! in_array($statusFilter, ['all', 'active', 'inactive', 'error'], true)) {
            $statusFilter = 'all';
        }

        $baseQuery = WpSite::where('user_id', Workspace::ownerId());
        if ($search !== '') {
            $term = '%'.$search.'%';
            $baseQuery->where(function ($q) use ($term) {
                $q->where('domain', 'like', $term)
                    ->orWhere('api_url', 'like', $term)
                    ->orWhere('notes', 'like', $term);
            });
        }

        $ids = (clone $baseQuery)
            ->selectRaw('MAX(id) as id')
            ->groupBy('domain_normalized')
            ->pluck('id');

        $query = WpSite::whereIn('id', $ids);
        if ($statusFilter === 'active') {
            $query->where('status', 'active');
        } elseif ($statusFilter === 'inactive') {
            $query->where('status', 'inactive');
        } elseif ($statusFilter === 'error') {
            $query->where('status', 'error');
        }

        $wpSites = $query->get();

        if ($wpSites->isEmpty()) {
            return back()->with('info', 'No WP sites to check.');
        }

        foreach ($wpSites as $wpSite) {
            WpSiteHealthCheckJob::dispatch($wpSite);
        }

        return back()->with('success', 'Health checks queued for '.$wpSites->count().' WP site(s). Run the queue worker (wp_sites), then refresh to see updated status.');
    }

    public function destroyBulk(Request $request)
    {
        $validated = $request->validate([
            'wp_site_ids' => 'required|array|min:1',
            'wp_site_ids.*' => 'integer',
        ]);

        $ids = WpSite::where('user_id', Workspace::ownerId())
            ->whereIn('id', $validated['wp_site_ids'])
            ->pluck('id');

        if ($ids->isEmpty()) {
            return back()->with('error', 'No matching WP sites found to delete. Select sites from the list and try again.');
        }

        $deleted = WpSite::where('user_id', Workspace::ownerId())
            ->whereIn('id', $ids)
            ->delete();

        return back()->with('success', $deleted.' WP site(s) deleted.');
    }

    public function assignBulk(Request $request)
    {
        $validated = $request->validate([
            'wp_site_ids' => 'required|array|min:1',
            'wp_site_ids.*' => 'integer',
            'wp_site_category_id' => ['nullable', 'integer', Rule::exists('wp_site_categories', 'id')],
        ]);

        $categoryId = $validated['wp_site_category_id'] ?? null;
        $ids = WpSite::where('user_id', Workspace::ownerId())
            ->whereIn('id', $validated['wp_site_ids'])
            ->pluck('id');

        if ($ids->isEmpty()) {
            return back()->with('error', 'No matching WP sites found. Select sites from the list and try again.');
        }

        $updated = 0;
        foreach ($ids->chunk(500) as $part) {
            $updated += WpSite::where('user_id', Workspace::ownerId())
                ->whereIn('id', $part->all())
                ->update(['wp_site_category_id' => $categoryId]);
        }

        $label = $categoryId
            ? (string) WpSiteCategory::query()->whereKey($categoryId)->value('name')
            : 'Uncategorized';

        return back()->with('success', $updated.' WP site(s) set to '.$label.'.');
    }

    public function assignByPaste(Request $request)
    {
        $validated = $request->validate([
            'wp_site_category_id' => ['nullable', 'integer', Rule::exists('wp_site_categories', 'id')],
            'domains' => 'required|string',
        ]);

        $lines = array_values(array_unique(array_filter(array_map(
            fn ($line) => WpSite::normalizeDomain($line),
            preg_split('/\r?\n/', $validated['domains']) ?: []
        ))));

        if ($lines === []) {
            return redirect()->route('wp-sites.categories.index')->with('error', 'Paste at least one domain.');
        }

        $matched = collect();
        foreach (array_chunk($lines, 500) as $part) {
            $matched = $matched->concat(
                WpSite::where('user_id', Workspace::ownerId())
                    ->whereIn('domain_normalized', $part)
                    ->get(['id', 'domain_normalized'])
            );
        }

        $ids = $matched->pluck('id')->unique()->values();
        $unknown = array_values(array_diff($lines, $matched->pluck('domain_normalized')->all()));
        $categoryId = $validated['wp_site_category_id'] ?? null;

        $updated = 0;
        foreach ($ids->chunk(500) as $part) {
            $updated += WpSite::where('user_id', Workspace::ownerId())
                ->whereIn('id', $part->all())
                ->update(['wp_site_category_id' => $categoryId]);
        }

        $label = $categoryId
            ? (string) WpSiteCategory::query()->whereKey($categoryId)->value('name')
            : 'Uncategorized';
        $message = $updated.' WP site(s) set to '.$label.'.';

        if ($unknown !== []) {
            $preview = implode(', ', array_slice($unknown, 0, 10));
            if (count($unknown) > 10) {
                $preview .= ' +'.(count($unknown) - 10).' more';
            }

            return redirect()->route('wp-sites.categories.index')->with('success', $message)->with('info', 'Unknown domains skipped: '.$preview);
        }

        if ($updated === 0) {
            return redirect()->route('wp-sites.categories.index')->with('error', 'No matching WP sites found for the pasted domains.');
        }

        return redirect()->route('wp-sites.categories.index')->with('success', $message);
    }

    public function destroyImport(WpSiteImport $wpSiteImport)
    {
        if ($wpSiteImport->user_id !== Workspace::ownerId()) {
            abort(403);
        }
        if ($wpSiteImport->filename && Storage::exists($wpSiteImport->filename)) {
            Storage::delete($wpSiteImport->filename);
        }
        $wpSiteImport->delete();

        return back()->with('success', 'Import file and record deleted. Note: WP sites imported from this file are still in your list — use "Delete imported sites" to remove them.');
    }

    public function destroyImportSites(WpSiteImport $wpSiteImport)
    {
        if ($wpSiteImport->user_id !== Workspace::ownerId()) {
            abort(403);
        }

        $deleted = WpSite::where('user_id', Workspace::ownerId())
            ->where('wp_site_import_id', $wpSiteImport->id)
            ->delete();

        if ($wpSiteImport->filename && Storage::exists($wpSiteImport->filename)) {
            Storage::delete($wpSiteImport->filename);
        }
        $wpSiteImport->delete();

        if ($deleted === 0) {
            return back()->with('info', 'Import record removed. No WP sites were linked to this import (older imports before tracking was added). Delete sites from the table above using checkboxes.');
        }

        return back()->with('success', $deleted.' imported WP site(s) and the import record deleted.');
    }

    public function export(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $statusFilter = (string) $request->query('status', 'all');
        if (! in_array($statusFilter, ['all', 'active', 'inactive', 'error'], true)) {
            $statusFilter = 'all';
        }
        $categoryFilter = $this->categoryFilter($request);

        $baseQuery = WpSite::where('user_id', Workspace::ownerId());
        if ($search !== '') {
            $term = '%'.$search.'%';
            $baseQuery->where(function ($q) use ($term) {
                $q->where('domain', 'like', $term)
                    ->orWhere('api_url', 'like', $term)
                    ->orWhere('notes', 'like', $term);
            });
        }

        $ids = (clone $baseQuery)
            ->selectRaw('MAX(id) as id')
            ->groupBy('domain')
            ->pluck('id');

        $query = WpSite::whereIn('id', $ids);
        if ($statusFilter === 'active') {
            $query->where('status', 'active');
        } elseif ($statusFilter === 'inactive') {
            $query->where('status', 'inactive');
        } elseif ($statusFilter === 'error') {
            $query->where('status', 'error');
        }
        if ($categoryFilter === 'uncategorized') {
            $query->whereNull('wp_site_category_id');
        } elseif (is_numeric($categoryFilter)) {
            $query->where('wp_site_category_id', (int) $categoryFilter);
        }

        $wpSites = $query
            ->with('wpSiteCategory')
            ->orderBy('domain')
            ->get();

        $lines = [];
        $lines[] = ['Domain', 'API URL', 'Status', 'Last Checked', 'Last Error', 'Notes', 'Category'];
        foreach ($wpSites as $wpSite) {
            $lines[] = [
                $wpSite->domain,
                $wpSite->api_url,
                $wpSite->status ?? 'inactive',
                optional($wpSite->last_checked_at)->toDateTimeString() ?? '',
                $wpSite->last_health_error ?? '',
                $wpSite->notes ?? '',
                $wpSite->wpSiteCategory?->name ?? '',
            ];
        }

        $fh = fopen('php://temp', 'r+');
        foreach ($lines as $row) {
            fputcsv($fh, $row);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        $filename = 'wp-sites-'.now()->format('Ymd-His').'.csv';

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    private function categoryFilter(Request $request): string
    {
        $categoryFilter = (string) $request->query('category', 'all');
        if ($categoryFilter === 'all' || $categoryFilter === 'uncategorized') {
            return $categoryFilter;
        }

        if (ctype_digit($categoryFilter) && WpSiteCategory::query()->whereKey((int) $categoryFilter)->exists()) {
            return $categoryFilter;
        }

        return 'all';
    }
}
