@extends('layouts.dashboard')
@section('title', $campaign->name ?? 'Campaign Detail')
@section('page-title', $campaign->name ?? 'Campaign Detail')

@section('content')
<div class="page-enter">
    <div class="mb-6">
        <a href="{{ route('campaigns.index') }}" class="text-sm text-slate-500 hover:text-purple-600 mb-2 inline-block">← Back to Campaigns</a>
        <div class="flex flex-wrap items-center gap-4">
            <h2 class="text-2xl font-bold text-slate-800">{{ $campaign->name ?? 'Untitled Campaign' }}</h2>
            @php
                $statusClasses = [
                    'pending' => 'bg-slate-100 text-slate-600',
                    'processing' => 'bg-amber-100 text-amber-700',
                    'deleting' => 'bg-sky-100 text-sky-700',
                    'completed' => 'bg-emerald-100 text-emerald-700',
                    'failed' => 'bg-red-100 text-red-700',
                    'partial' => 'bg-orange-100 text-orange-700',
                    'delete_failed' => 'bg-red-100 text-red-700',
                    'semi_deleted' => 'bg-violet-100 text-violet-700',
                ];
            @endphp
            <span class="px-3 py-1 text-sm font-medium rounded-full {{ $statusClasses[$campaign->status ?? 'pending'] ?? 'bg-slate-100 text-slate-600' }}">{{ ucwords(str_replace('_', ' ', $campaign->status ?? 'pending')) }}</span>
        </div>
        @if($campaign->description ?? null)
            <p class="text-slate-500 mt-1">{{ $campaign->description }}</p>
        @endif
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 mb-8">
        <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
            <p class="text-sm text-slate-500">Source Links</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ $campaign->total_links ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
            <p class="text-sm text-slate-500">Domains</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ $campaign->total_domains ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
            <p class="text-sm text-slate-500">Per Domain</p>
            <p class="text-2xl font-bold text-purple-600 mt-1">{{ $campaign->links_per_domain ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
            <p class="text-sm text-slate-500">Success</p>
            <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $campaign->success_count ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
            <p class="text-sm text-slate-500">Failed</p>
            <p class="text-2xl font-bold text-red-600 mt-1">{{ $campaign->failed_count ?? 0 }}</p>
        </div>
    </div>
    @if(($campaign->status ?? '') === 'deleting')
        <div class="bg-sky-50 border border-sky-200 rounded-lg p-4 mb-6 flex items-center gap-3">
            <svg class="w-5 h-5 text-sky-600 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-sky-800 font-medium">Deleting from remote sites… Unresponsive domains are skipped and processing continues on live sites.</span>
        </div>
    @endif
    @if(in_array($campaign->status ?? '', ['semi_deleted', 'delete_failed'], true))
        <div class="bg-violet-50 border border-violet-200 rounded-lg p-4 mb-6">
            <p class="text-violet-900 font-medium">{{ ($campaign->status ?? '') === 'semi_deleted' ? 'Semi deleted' : 'Delete incomplete' }}</p>
            <p class="text-sm text-violet-800 mt-1">Remote deletion finished for responsive domains. This campaign now only lists domains where links could not be removed. Delete again to retry remaining sites.</p>
        </div>
    @endif
    @if(($campaign->status ?? '') === 'processing')
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6 flex items-center gap-3">
            <svg class="w-5 h-5 text-amber-600 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-amber-800 font-medium">Processing... Refresh the page to see latest progress.</span>
        </div>
    @endif
    @php
        $domainStatsList = $domainStats ?? [];
        $domainFilterCounts = [
            'all' => count($domainStatsList),
            'success' => collect($domainStatsList)->filter(fn ($r) => ($r['success'] ?? 0) > 0)->count(),
            'pending' => collect($domainStatsList)->filter(fn ($r) => ($r['pending'] ?? 0) > 0)->count(),
            'failed' => collect($domainStatsList)->filter(fn ($r) => ($r['failed'] ?? 0) > 0)->count(),
        ];
    @endphp
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-4 sm:px-6 py-5 border-b border-slate-200">
            <div class="flex flex-col gap-5">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-800">Target Domains</h3>
                        <p class="text-sm text-slate-500 mt-0.5">Remove offline or failing domains without stopping the campaign on live sites.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 lg:justify-end">
            @if($hasPendingChunks ?? false)
                <form method="POST" action="{{ route('campaigns.publish-pending', $campaign) }}" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg bg-sky-600 text-white hover:bg-sky-700 shadow-sm transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        Publish pending
                    </button>
                </form>
            @endif
            @if(($problemDomainCount ?? 0) > 0)
                <form method="POST" action="{{ route('campaigns.domains.remove-problem', $campaign) }}" class="inline" onsubmit="return confirm('Remove {{ $problemDomainCount }} problem domain(s) from this campaign? Pending/failed work on those sites will stop. Links already posted will be cleaned remotely when possible.');">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-red-200 bg-red-50 text-red-700 hover:bg-red-100 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Remove problem domains ({{ $problemDomainCount }})
                    </button>
                </form>
            @endif
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-4 border-t border-slate-100">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 flex-1">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 shrink-0">Filter by status</span>
                        <div class="inline-flex flex-wrap rounded-xl border border-slate-200 bg-slate-100/80 p-1 gap-1" role="tablist" aria-label="Filter domains by status">
                            <button type="button" data-domain-filter="all" aria-selected="true" class="domain-filter-btn px-4 py-2 text-sm font-semibold rounded-lg transition-all duration-200 bg-slate-700 text-white shadow-sm">
                                All <span class="domain-filter-count tabular-nums ml-0.5 opacity-80">{{ number_format($domainFilterCounts['all']) }}</span>
                            </button>
                            <button type="button" data-domain-filter="success" aria-selected="false" class="domain-filter-btn px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 text-slate-600 hover:text-emerald-700 hover:bg-white/60">
                                Success <span class="domain-filter-count tabular-nums ml-0.5 opacity-70">{{ number_format($domainFilterCounts['success']) }}</span>
                            </button>
                            <button type="button" data-domain-filter="pending" aria-selected="false" class="domain-filter-btn px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 text-slate-600 hover:text-slate-800 hover:bg-white/60">
                                Pending <span class="domain-filter-count tabular-nums ml-0.5 opacity-70">{{ number_format($domainFilterCounts['pending']) }}</span>
                            </button>
                            <button type="button" data-domain-filter="failed" aria-selected="false" class="domain-filter-btn px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 text-slate-600 hover:text-red-700 hover:bg-white/60">
                                Failed <span class="domain-filter-count tabular-nums ml-0.5 opacity-70">{{ number_format($domainFilterCounts['failed']) }}</span>
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto sm:max-w-xs">
                        <input type="text" id="domain-search" placeholder="Search domain..." class="w-full rounded-lg border-slate-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 text-sm">
                    </div>
                </div>
                <p id="domain-filter-count" class="text-xs text-slate-500 min-h-[1rem]"></p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Domain</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Total Links</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Success</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Failed</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Pending</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200" id="domain-stats-body">
                    @forelse($domainStatsList as $row)
                        <tr class="hover:bg-slate-50/50 transition-colors domain-stat-row"
                            data-domain="{{ strtolower($row['domain'] ?? '') }}"
                            data-success="{{ (int) ($row['success'] ?? 0) }}"
                            data-pending="{{ (int) ($row['pending'] ?? 0) }}"
                            data-failed="{{ (int) ($row['failed'] ?? 0) }}">
                            <td class="px-6 py-4">
                                <div class="font-medium text-slate-800">{{ $row['domain'] ?? 'N/A' }}</div>
                                @if(($row['domain_status'] ?? '') !== 'active')
                                    <span class="inline-block mt-1 px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-800">{{ ucfirst($row['domain_status'] ?? 'inactive') }}</span>
                                @endif
                                @if($row['is_problem'] ?? false)
                                    <span class="inline-block mt-1 ml-1 px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700">Issue</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm">{{ $row['total'] ?? 0 }}</td>
                            <td class="px-6 py-4 text-sm text-emerald-600">{{ $row['success'] ?? 0 }}</td>
                            <td class="px-6 py-4 text-sm text-red-600">{{ $row['failed'] ?? 0 }}</td>
                            <td class="px-6 py-4 text-sm text-slate-500">{{ $row['pending'] ?? 0 }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="inline-flex items-center gap-2 justify-end">
                                <a href="{{ route('campaigns.show-domain', [$campaign, $row['domain_id'] ?? 0]) }}" class="text-purple-600 hover:text-purple-700 text-sm font-medium">View</a>
                                <form method="POST" action="{{ route('campaigns.domains.destroy', [$campaign, $row['domain_id'] ?? 0]) }}" class="inline" onsubmit="return confirm('Remove this domain from the campaign? Pending posts to this site will stop.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-lg text-slate-500 hover:bg-red-50 hover:text-red-600 transition-colors" title="Remove domain from campaign">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr id="domain-stats-empty">
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                No domain statistics yet. Start posting to see results.
                            </td>
                        </tr>
                    @endforelse
                    <tr id="domain-filter-empty" class="hidden">
                        <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                            No domains match this filter.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-8 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-4 sm:px-6 py-4 border-b border-slate-200">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-slate-800">Source Links ({{ count($links ?? []) }})</h3>
                    <p class="text-sm text-slate-500 mt-0.5">Original URL and keyword pairs used for distribution</p>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto max-h-96 overflow-y-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50 sticky top-0">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-12">#</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">URL</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Keyword</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($links ?? [] as $i => $link)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-3 text-sm text-slate-500">{{ $i + 1 }}</td>
                            <td class="px-6 py-3 text-sm text-slate-800 break-all max-w-xs">{{ $link->url }}</td>
                            <td class="px-6 py-3 text-sm text-slate-700">{{ $link->keyword }}</td>
                            <td class="px-6 py-3 text-right">
                                <form method="POST" action="{{ route('campaigns.links.destroy', [$campaign, $link]) }}" class="inline" onsubmit="return confirm('Remove this link from all target domains? This action cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Delete link from all domains" class="p-2 rounded-lg text-slate-500 hover:bg-red-50 hover:text-red-600 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-slate-500">No links found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function() {
    const filterButtons = document.querySelectorAll('.domain-filter-btn');
    const domainRows = document.querySelectorAll('.domain-stat-row');
    const filterEmptyRow = document.getElementById('domain-filter-empty');
    const filterCountEl = document.getElementById('domain-filter-count');
    const domainSearch = document.getElementById('domain-search');
    let activeFilter = 'all';

    const filterBaseClass = 'domain-filter-btn px-4 py-2 text-sm rounded-lg transition-all duration-200';
    const activeClasses = {
        all: 'font-semibold bg-slate-700 text-white shadow-sm',
        success: 'font-semibold bg-emerald-600 text-white shadow-sm',
        pending: 'font-semibold bg-slate-500 text-white shadow-sm',
        failed: 'font-semibold bg-red-600 text-white shadow-sm',
    };
    const inactiveClasses = {
        all: 'font-medium text-slate-600 hover:text-slate-800 hover:bg-white/60',
        success: 'font-medium text-slate-600 hover:text-emerald-700 hover:bg-white/60',
        pending: 'font-medium text-slate-600 hover:text-slate-800 hover:bg-white/60',
        failed: 'font-medium text-slate-600 hover:text-red-700 hover:bg-white/60',
    };

    function applyFilterButtonState(btn, key, isActive) {
        btn.className = filterBaseClass + ' ' + (isActive ? activeClasses[key] : inactiveClasses[key]);
        btn.setAttribute('aria-selected', isActive ? 'true' : 'false');

        const countEl = btn.querySelector('.domain-filter-count');
        if (countEl) {
            countEl.className = 'domain-filter-count tabular-nums ml-0.5 ' + (isActive ? 'opacity-80' : 'opacity-70');
        }
    }

    function rowMatchesStatusFilter(row, filter) {
        const success = parseInt(row.getAttribute('data-success') || '0', 10);
        const pending = parseInt(row.getAttribute('data-pending') || '0', 10);
        const failed = parseInt(row.getAttribute('data-failed') || '0', 10);

        if (filter === 'success') return success > 0;
        if (filter === 'pending') return pending > 0;
        if (filter === 'failed') return failed > 0;
        return true;
    }

    function rowMatchesSearch(row, query) {
        if (!query) return true;
        const domain = row.getAttribute('data-domain') || '';
        return domain.includes(query);
    }

    function updateDomainFilter(filter) {
        activeFilter = filter;
        const query = (domainSearch?.value || '').trim().toLowerCase();
        let visible = 0;

        filterButtons.forEach(function(btn) {
            const key = btn.getAttribute('data-domain-filter');
            applyFilterButtonState(btn, key, key === filter);
        });

        domainRows.forEach(function(row) {
            const show = rowMatchesStatusFilter(row, filter) && rowMatchesSearch(row, query);
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        if (filterEmptyRow) {
            filterEmptyRow.classList.toggle('hidden', visible > 0 || domainRows.length === 0);
        }
        if (filterCountEl && domainRows.length > 0) {
            const countColors = {
                all: 'text-slate-600',
                success: 'text-emerald-600',
                pending: 'text-slate-500',
                failed: 'text-red-600',
            };
            const hasFilter = filter !== 'all' || query;
            filterCountEl.className = 'text-xs min-h-[1rem] font-medium ' + (hasFilter ? (countColors[filter] || 'text-slate-500') : 'text-slate-500');
            filterCountEl.textContent = hasFilter
                ? 'Showing ' + visible.toLocaleString() + ' of ' + domainRows.length.toLocaleString() + ' domains'
                : '';
        }
    }

    filterButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            updateDomainFilter(btn.getAttribute('data-domain-filter') || 'all');
        });
    });

    if (domainSearch) {
        domainSearch.addEventListener('input', function() {
            updateDomainFilter(activeFilter);
        });
    }

    updateDomainFilter('all');
})();
</script>
@endsection
