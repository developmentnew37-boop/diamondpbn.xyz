<?php

namespace App\Http\Controllers;

use App\Jobs\DomainHealthCheckJob;
use App\Jobs\ImportDomainsJob;
use App\Models\Domain;
use App\Models\DomainImport;
use App\Rules\SafeApiUrl;
use App\Support\ApiUrlHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DomainController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $statusFilter = (string) $request->query('status', 'all');
        if (! in_array($statusFilter, ['all', 'active', 'inactive', 'error'], true)) {
            $statusFilter = 'all';
        }

        $baseQuery = Domain::where('user_id', auth()->id());
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

        $dedupedQuery = Domain::whereIn('id', $ids);

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
            ->where(function ($q) {
                $q->whereNull('type')->orWhere('type', '!=', 'campaign');
            })
            ->withCount('domains')
            ->latest()
            ->limit(20)
            ->get();

        return view('domains.index', [
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

        $normalized = Domain::normalizeDomain((string) $validated['domain']);
        if (Domain::where('user_id', $userId)->where('domain_normalized', $normalized)->exists()) {
            return back()->withErrors(['domain' => 'This domain already exists.'])->withInput();
        }

        $validated['user_id'] = $userId;
        $validated['domain'] = $normalized;
        $validated['domain_normalized'] = $normalized;
        $validated['api_url'] = ApiUrlHelper::normalizeForStorage($validated['api_url']);
        // Start every new domain as inactive (not connected) until health check runs.
        $validated['status'] = 'inactive';

        $domain = Domain::create($validated);
        DomainHealthCheckJob::dispatch($domain);

        return back()->with('success', 'Domain added. Health check queued.');
    }

    public function edit(Domain $domain)
    {
        if ($domain->user_id !== auth()->id()) {
            abort(403);
        }
        return view('domains.edit', compact('domain'));
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240']);

        $path = $request->file('file')->store('imports');
        $import = DomainImport::create([
            'user_id' => auth()->id(),
            'filename' => $path, // full path (e.g. imports/ab/xyz.xlsx) - job needs it to find the file
            'status' => 'pending',
        ]);

        ImportDomainsJob::dispatch($import)->onQueue('import_domains'); 
        return redirect()->route('domains.index')->with('success', 'Import queued. Run the domains queue worker to process.');
    }

    public function update(Request $request, Domain $domain)
    {
        if ($domain->user_id !== auth()->id()) {
            abort(403);
        }
        $validated = $request->validate([
            'domain' => 'required|string',
            'api_url' => ['required', 'url', new SafeApiUrl()],
            'api_key' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);
        $normalized = Domain::normalizeDomain((string) $validated['domain']);
        $exists = Domain::where('user_id', auth()->id())
            ->where('domain_normalized', $normalized)
            ->where('id', '!=', $domain->id)
            ->exists();
        if ($exists) {
            return back()->withErrors(['domain' => 'This domain already exists.'])->withInput();
        }

        $validated['domain'] = $normalized;
        $validated['domain_normalized'] = $normalized;
        $validated['api_url'] = ApiUrlHelper::normalizeForStorage($validated['api_url']);
        $domain->update($validated);
        return redirect()->route('domains.index')->with('success', 'Domain updated.');
    }

    public function destroy(Domain $domain)
    {
        if ($domain->user_id !== auth()->id()) {
            abort(403);
        }
        $domain->delete();
        return back()->with('success', 'Domain and all related data deleted.');
    }

    public function recheck(Domain $domain)
    {
        if ($domain->user_id !== auth()->id()) {
            abort(403);
        }
        DomainHealthCheckJob::dispatch($domain);
        return back()->with('success', 'Health check queued for ' . $domain->domain . '. Run the queue worker, then refresh — if the check succeeds, status will show Active.');
    }

    public function checkAllHealth(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $statusFilter = (string) $request->query('status', 'all');
        if (! in_array($statusFilter, ['all', 'active', 'inactive', 'error'], true)) {
            $statusFilter = 'all';
        }

        $baseQuery = Domain::where('user_id', auth()->id());
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

        $query = Domain::whereIn('id', $ids);
        if ($statusFilter === 'active') {
            $query->where('status', 'active');
        } elseif ($statusFilter === 'inactive') {
            $query->where('status', 'inactive');
        } elseif ($statusFilter === 'error') {
            $query->where('status', 'error');
        }

        $domains = $query->get();

        if ($domains->isEmpty()) {
            return back()->with('info', 'No domains to check.');
        }

        foreach ($domains as $domain) {
            DomainHealthCheckJob::dispatch($domain);
        }

        return back()->with('success', 'Health checks queued for ' . $domains->count() . ' domain(s). Run the queue worker (domains), then refresh to see updated status.');
    }

    public function destroyBulk(Request $request)
    {
        $validated = $request->validate([
            'domain_ids' => 'required|array|min:1',
            'domain_ids.*' => 'integer',
        ]);

        $ids = Domain::where('user_id', auth()->id())
            ->whereIn('id', $validated['domain_ids'])
            ->pluck('id');

        if ($ids->isEmpty()) {
            return back()->with('error', 'No matching domains found to delete. Select domains from the list and try again.');
        }

        $deleted = Domain::where('user_id', auth()->id())
            ->whereIn('id', $ids)
            ->delete();

        return back()->with('success', $deleted.' domain(s) deleted.');
    }

    public function destroyImport(DomainImport $domainImport)
    {
        if ($domainImport->user_id !== auth()->id()) {
            abort(403);
        }
        if (($domainImport->type ?? null) === 'campaign') {
            return back()->with('error', 'This is a campaign import record. Delete it from Target Domains.');
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
        if (($domainImport->type ?? null) === 'campaign') {
            return back()->with('error', 'This is a campaign import record. Use Target Domains to delete imported domains.');
        }

        $deleted = Domain::where('user_id', auth()->id())
            ->where('domain_import_id', $domainImport->id)
            ->delete();

        if ($domainImport->filename && Storage::exists($domainImport->filename)) {
            Storage::delete($domainImport->filename);
        }
        $domainImport->delete();

        if ($deleted === 0) {
            return back()->with('info', 'Import record removed. No domains were linked to this import (older imports before tracking was added). Delete domains from the table above using checkboxes.');
        }

        return back()->with('success', $deleted.' imported domain(s) and the import record deleted.');
    }

    public function export(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $statusFilter = (string) $request->query('status', 'all');
        if (! in_array($statusFilter, ['all', 'active', 'inactive', 'error'], true)) {
            $statusFilter = 'all';
        }

        $baseQuery = Domain::where('user_id', auth()->id());
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

        $query = Domain::whereIn('id', $ids);
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

        $filename = 'domains-' . now()->format('Ymd-His') . '.csv';

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
