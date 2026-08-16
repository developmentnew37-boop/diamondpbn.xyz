@extends('layouts.dashboard')
@section('title', 'Reports')
@section('page-title', 'Reports')

@section('content')
<div class="page-enter">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Reports</h2>
            <p class="text-slate-500 mt-1">Filter by batch, domain, or date. Export to CSV.</p>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-6">
        <form method="GET" action="{{ route('reports.index') }}" class="space-y-4">
            <h3 class="font-semibold text-slate-800 mb-4">Filters</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Batch</label>
                    <select name="batch_id" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">All Batches</option>
                        @foreach($batches ?? [] as $b)
                            <option value="{{ $b->id }}" {{ request('batch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Domain</label>
                    <select name="domain_id" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">All Domains</option>
                        @foreach($domains ?? [] as $d)
                            <option value="{{ $d->id }}" {{ request('domain_id') == $d->id ? 'selected' : '' }}>{{ $d->domain }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">From Date</label>
                    <input type="date" name="from_date" value="{{ request('from_date') }}" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">To Date</label>
                    <input type="date" name="to_date" value="{{ request('to_date') }}" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">Apply</button>
                    <a href="{{ route('reports.index', array_merge(request()->only(['batch_id','domain_id','from_date','to_date']), ['export' => 'csv'])) }}" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 inline-flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Export CSV
                    </a>
                </div>
            </div>
        </form>
    </div>
    @if($requireBatchFilter ?? false)
        <div class="mb-4 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm">
            You have a large number of batch chunks. Select a <strong>Batch</strong> filter and click <strong>Apply</strong> to load the report (loading all batches at once is too slow).
        </div>
    @endif
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Batch</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Domain</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">URL</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Keyword</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($rows ?? [] as $r)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-3 text-sm text-slate-800">{{ $r->batch?->name ?? '-' }}</td>
                            <td class="px-6 py-3 text-sm text-slate-700">{{ $r->domain?->domain ?? '-' }}</td>
                            <td class="px-6 py-3 text-sm text-slate-600 break-all max-w-xs">{{ Str::limit($r->link?->url ?? '-', 50) }}</td>
                            <td class="px-6 py-3 text-sm text-slate-600">{{ Str::limit($r->link?->keyword ?? '-', 30) }}</td>
                            <td class="px-6 py-3">
                                @php
                                    $sc = match($r->status ?? '') {
                                        'success' => 'bg-emerald-100 text-emerald-700',
                                        'failed' => 'bg-red-100 text-red-700',
                                        default => 'bg-slate-100 text-slate-600',
                                    };
                                @endphp
                                <span class="px-2 py-0.5 text-xs font-medium rounded {{ $sc }}">{{ ucfirst($r->status ?? '-') }}</span>
                            </td>
                            <td class="px-6 py-3 text-sm text-slate-500">{{ $r->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                @if(($chunkCount ?? 0) === 0)
                                    <p class="font-medium text-slate-700">No report data for the selected filters.</p>
                                    <p class="mt-2 text-sm max-w-lg mx-auto">Batches store report rows in <code class="text-xs bg-slate-100 px-1 rounded">batch_domain_chunks</code>. If domains were deleted from the Domains page, those chunk records are removed too and reports will be empty even though the batch still appears in the list.</p>
                                    <p class="mt-2 text-sm">Create a new batch with active domains, or open a batch detail page to confirm domain progress still exists.</p>
                                @elseif(($totalRows ?? 0) === 0)
                                    <p class="font-medium text-slate-700">Chunks exist but contain no links.</p>
                                    <p class="mt-2 text-sm">Run <code class="text-xs bg-slate-100 px-1 rounded">php artisan batches:backfill-links-count</code> on the server if you recently added the links_count column.</p>
                                @else
                                    No report data yet. Apply filters or run a batch to see results.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(isset($rows) && $rows->hasPages())
            <div class="px-6 py-4 border-t border-slate-200">
                {{ $rows->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
