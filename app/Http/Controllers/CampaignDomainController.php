<?php

namespace App\Http\Controllers;

use App\Jobs\CampaignDomainHealthCheckJob;
use App\Jobs\DomainHealthCheckJob;
use App\Jobs\ImportCampaignDomainsJob;
use App\Models\CampaignDomain;
use App\Models\DomainImport;
use App\Rules\SafeApiUrl;
use App\Support\ApiUrlHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CampaignDomainController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $statusFilter = (string) $request->query('status', 'all');
        if (! in_array($statusFilter, ['all', 'active', 'inactive', 'error'], true)) {
            $statusFilter = 'all';
        }

        $baseQuery = CampaignDomain::where('user_id', auth()->id());
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

        $dedupedQuery = CampaignDomain::whereIn('id', $ids);

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

        $domains = $dedupedQuery
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $imports = DomainImport::where('user_id', auth()->id())
            ->where('type', 'campaign')
            ->withCount('campaignDomains')
            ->latest()
            ->limit(20)
            ->get();

        return view('campaign-domains.index', [
            'domains' => $domains,
            'imports' => $imports,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'statusCounts' => $statusCounts,
        ]);
    }

    public function store(Request $request)
    {
        $userId = auth()->id();
        $validated = $request->validate([
            'domain' => 'required|string',
            'api_url' => ['required', 'url', new SafeApiUrl()],
            'api_key' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $normalized = CampaignDomain::normalizeDomain((string) $validated['domain']);
        if (CampaignDomain::where('user_id', $userId)->where('domain_normalized', $normalized)->exists()) {
            return back()->withErrors(['domain' => 'This domain already exists in campaign domains.'])->withInput();
        }

        $validated['user_id'] = $userId;
        $validated['domain'] = $normalized;
        $validated['domain_normalized'] = $normalized;
        $validated['api_url'] = ApiUrlHelper::normalizeForStorage($validated['api_url']);
        $validated['status'] = 'inactive';

        $domain = CampaignDomain::create($validated);

        return back()->with('success', 'Campaign domain added successfully.');
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240']);

        $path = $request->file('file')->store('imports');
        $import = DomainImport::create([
            'user_id' => auth()->id(),
            'filename' => $path,
            'type' => 'campaign',
            'status' => 'pending',
        ]);

        ImportCampaignDomainsJob::dispatch($import)->onQueue('import_domains');
        return redirect()->route('campaign-domains.index')->with('success', 'Import queued. Health checks will run automatically after import completes.');
    }

    public function edit(CampaignDomain $campaignDomain)
    {
        if ($campaignDomain->user_id !== auth()->id()) {
            abort(403);
        }
        return view('campaign-domains.edit', compact('campaignDomain'));
    }

    public function update(Request $request, CampaignDomain $campaignDomain)
    {
        if ($campaignDomain->user_id !== auth()->id()) {
            abort(403);
        }
        $validated = $request->validate([
            'domain' => 'required|string',
            'api_url' => ['required', 'url', new SafeApiUrl()],
            'api_key' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);
        $normalized = CampaignDomain::normalizeDomain((string) $validated['domain']);
        $exists = CampaignDomain::where('user_id', auth()->id())
            ->where('domain_normalized', $normalized)
            ->where('id', '!=', $campaignDomain->id)
            ->exists();
        if ($exists) {
            return back()->withErrors(['domain' => 'This domain already exists.'])->withInput();
        }

        $validated['domain'] = $normalized;
        $validated['domain_normalized'] = $normalized;
        $validated['api_url'] = ApiUrlHelper::normalizeForStorage($validated['api_url']);
        $campaignDomain->update($validated);
        return redirect()->route('campaign-domains.index')->with('success', 'Campaign domain updated.');
    }

    public function destroy(CampaignDomain $campaignDomain)
    {
        if ($campaignDomain->user_id !== auth()->id()) {
            abort(403);
        }
        $campaignDomain->delete();
        return back()->with('success', 'Campaign domain deleted.');
    }

    public function destroyBulk(Request $request)
    {
        $validated = $request->validate([
            'domain_ids' => 'required|array|min:1',
            'domain_ids.*' => 'integer',
        ]);

        $ids = CampaignDomain::where('user_id', auth()->id())
            ->whereIn('id', $validated['domain_ids'])
            ->pluck('id');

        if ($ids->isEmpty()) {
            return back()->with('error', 'No matching domains found to delete. Select domains from the list and try again.');
        }

        $deleted = CampaignDomain::where('user_id', auth()->id())
            ->whereIn('id', $ids)
            ->delete();

        return back()->with('success', $deleted.' campaign domain(s) deleted.');
    }

    public function destroyImport(DomainImport $domainImport)
    {
        if ($domainImport->user_id !== auth()->id()) {
            abort(403);
        }
        if ($domainImport->filename && Storage::exists($domainImport->filename)) {
            Storage::delete($domainImport->filename);
        }
        $domainImport->delete();

        return back()->with('success', 'Import file and record deleted. Note: domains imported from this file are still in your list — use "Delete imported domains" to remove them.');
    }

    public function destroyImportDomains(DomainImport $domainImport)
    {
        if ($domainImport->user_id !== auth()->id()) {
            abort(403);
        }
        if (($domainImport->type ?? null) !== 'campaign') {
            return back()->with('error', 'This is not a campaign import record.');
        }

        $deleted = CampaignDomain::where('user_id', auth()->id())
            ->where('domain_import_id', $domainImport->id)
            ->delete();

        if ($domainImport->filename && Storage::exists($domainImport->filename)) {
            Storage::delete($domainImport->filename);
        }
        $domainImport->delete();

        if ($deleted === 0) {
            return back()->with('info', 'Import record removed. No domains were linked to this import (older imports before tracking was added). Delete domains from the table above using checkboxes.');
        }

        return back()->with('success', $deleted.' imported target domain(s) and the import record deleted.');
    }

    public function export(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $statusFilter = (string) $request->query('status', 'all');
        if (! in_array($statusFilter, ['all', 'active', 'inactive', 'error'], true)) {
            $statusFilter = 'all';
        }

        $baseQuery = CampaignDomain::where('user_id', auth()->id());
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

        $query = CampaignDomain::whereIn('id', $ids);
        if ($statusFilter === 'active') {
            $query->where('status', 'active');
        } elseif ($statusFilter === 'inactive') {
            $query->where('status', 'inactive');
        } elseif ($statusFilter === 'error') {
            $query->where('status', 'error');
        }

        $domains = $query
            ->orderBy('domain')
            ->get();

        $lines = [];
        $lines[] = ['Domain', 'API URL', 'Status', 'Last Checked', 'Last Error', 'Notes'];
        foreach ($domains as $domain) {
            $lines[] = [
                $domain->domain,
                $domain->api_url,
                $domain->status ?? 'inactive',
                optional($domain->last_checked_at)->toDateTimeString() ?? '',
                $domain->last_health_error ?? '',
                $domain->notes ?? '',
            ];
        }

        $fh = fopen('php://temp', 'r+');
        foreach ($lines as $row) {
            fputcsv($fh, $row);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        $filename = 'campaign-domains-' . now()->format('Ymd-His') . '.csv';

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function checkHealth(CampaignDomain $campaignDomain)
    {
        if ($campaignDomain->user_id !== auth()->id()) {
            abort(403);
        }

        CampaignDomainHealthCheckJob::dispatch($campaignDomain);

        return back()->with('success', 'Health check queued for ' . $campaignDomain->domain);
    }

    public function checkAllHealth(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $statusFilter = (string) $request->query('status', 'all');
        if (! in_array($statusFilter, ['all', 'active', 'inactive', 'error'], true)) {
            $statusFilter = 'all';
        }

        $baseQuery = CampaignDomain::where('user_id', auth()->id());
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

        $query = CampaignDomain::whereIn('id', $ids);
        if ($statusFilter === 'active') {
            $query->where('status', 'active');
        } elseif ($statusFilter === 'inactive') {
            $query->where('status', 'inactive');
        } elseif ($statusFilter === 'error') {
            $query->where('status', 'error');
        }

        $domains = $query->get();

        foreach ($domains as $domain) {
            CampaignDomainHealthCheckJob::dispatch($domain);
        }

        return back()->with('success', 'Health checks queued for ' . $domains->count() . ' domain(s)');
    }
}
