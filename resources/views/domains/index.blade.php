@extends('layouts.dashboard')
@section('title', 'Domains')
@section('page-title', 'Domains')

@section('content')
@php
    $statusFilter = $statusFilter ?? 'all';
    $statusCounts = $statusCounts ?? ['all' => 0, 'active' => 0, 'inactive' => 0, 'error' => 0];
    $listQuery = array_filter([
        'search' => ($search ?? '') !== '' ? $search : null,
        'status' => $statusFilter !== 'all' ? $statusFilter : null,
    ], fn ($v) => $v !== null && $v !== '');
@endphp
<div class="page-enter">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">PBN Domains</h2>
            <p class="text-slate-500 mt-1">Manage your network of websites and API endpoints</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
            <form method="GET" action="{{ route('domains.index') }}" class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto">
                @if($statusFilter !== 'all')
                    <input type="hidden" name="status" value="{{ $statusFilter }}">
                @endif
                <div class="relative">
                    <input
                        id="domain-search"
                        type="text"
                        name="search"
                        value="{{ $search ?? '' }}"
                        placeholder="Search domains or API URL..."
                        class="w-full sm:w-64 rounded-lg border-slate-300 shadow-sm pl-9 pr-3 py-2 text-sm focus:border-orange-500 focus:ring-orange-500"
                    >
                    <span class="absolute inset-y-0 left-2 flex items-center text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M5 11a6 6 0 1112 0 6 6 0 01-12 0z"/></svg>
                    </span>
                </div>
                <button type="submit" class="w-full sm:w-auto px-3 py-2 bg-slate-800 text-white text-sm rounded-lg hover:bg-slate-900 transition-colors">
                    Search
                </button>
                @if(($search ?? '') !== '')
                    <a href="{{ route('domains.index', $statusFilter !== 'all' ? ['status' => $statusFilter] : []) }}" class="text-xs text-slate-500 hover:text-slate-700">Clear</a>
                @endif
            </form>
            <form action="{{ route('domains.check-all', $listQuery) }}" method="POST" class="inline w-full sm:w-auto">
                @csrf
                <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-emerald-300 rounded-lg text-sm font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 transition-colors" title="Queue health checks for all domains (or current search results)">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Re-check All
                </button>
            </form>
            <form action="{{ route('domains.import') }}" method="POST" enctype="multipart/form-data" class="inline w-full sm:w-auto">
                @csrf
                <input type="file" name="file" accept=".csv,.txt,.xlsx,.xls" class="hidden" id="import-file" onchange="this.form.submit()">
                <button type="button" onclick="document.getElementById('import-file').click()" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Import CSV/Excel
                </button>
            </form>
            <a href="{{ route('domains.export', $listQuery) }}" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M4 12l8 8m0 0l8-8m-8 8V4"/>
                </svg>
                Export
            </a>
            <button type="button" onclick="openAddDomainModal()" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Add Domain
            </button>
        </div>
    </div>
    <form method="POST" action="{{ route('domains.bulk-destroy') }}" id="bulk-delete-form" class="hidden mb-4 flex items-center gap-3 px-4 py-3 bg-slate-50 rounded-xl border border-slate-200" onsubmit="return confirm('Delete selected domains? All related batch data will be removed.');">
        @csrf
        <span id="bulk-count" class="text-sm text-slate-600 font-medium">0 selected</span>
        <button type="submit" class="px-3 py-1.5 text-sm font-medium rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition-colors">
            Delete Selected
        </button>
        <button type="button" onclick="clearSelection()" class="px-3 py-1.5 text-sm text-slate-600 hover:text-slate-800">Clear</button>
    </form>

    <div class="flex flex-col lg:flex-row gap-4 mb-6 w-full">
        <div class="flex-1 min-w-0 w-full bg-white rounded-xl border border-emerald-200 p-4 shadow-sm">
            <p class="text-sm text-slate-500">Connected</p>
            <p class="text-2xl font-bold text-emerald-600 mt-1">{{ number_format($statusCounts['active'] ?? 0) }}</p>
            <p class="text-xs text-slate-500 mt-1">API health check passed</p>
        </div>
        <div class="flex-1 min-w-0 w-full bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <p class="text-sm text-slate-500">Not connected</p>
            <p class="text-2xl font-bold text-slate-700 mt-1">{{ number_format($statusCounts['inactive'] ?? 0) }}</p>
            <p class="text-xs text-slate-500 mt-1">Never checked or still inactive</p>
        </div>
        <div class="flex-1 min-w-0 w-full bg-white rounded-xl border border-red-200 p-4 shadow-sm">
            <p class="text-sm text-slate-500">Error</p>
            <p class="text-2xl font-bold text-red-600 mt-1">{{ number_format($statusCounts['error'] ?? 0) }}</p>
            <p class="text-xs text-slate-500 mt-1">Site unreachable or API failed</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-4 sm:px-6 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Filter by status</span>
            <div class="inline-flex flex-wrap rounded-xl border border-slate-200 bg-slate-100/80 p-1 gap-1" role="tablist">
                @php
                    $filterTabs = [
                        'all' => ['label' => 'All', 'count' => $statusCounts['all'] ?? 0, 'activeClass' => 'bg-slate-700 text-white'],
                        'active' => ['label' => 'Connected', 'count' => $statusCounts['active'] ?? 0, 'activeClass' => 'bg-emerald-600 text-white'],
                        'inactive' => ['label' => 'Not connected', 'count' => $statusCounts['inactive'] ?? 0, 'activeClass' => 'bg-slate-600 text-white'],
                        'error' => ['label' => 'Error', 'count' => $statusCounts['error'] ?? 0, 'activeClass' => 'bg-red-600 text-white'],
                    ];
                @endphp
                @foreach($filterTabs as $key => $tab)
                    @php
                        $tabQuery = $listQuery;
                        if ($key === 'all') {
                            unset($tabQuery['status']);
                        } else {
                            $tabQuery['status'] = $key;
                        }
                        $isActive = $statusFilter === $key;
                    @endphp
                    <a href="{{ route('domains.index', $tabQuery) }}"
                        class="px-3 py-2 text-sm rounded-lg transition-colors {{ $isActive ? $tab['activeClass'].' font-semibold shadow-sm' : 'text-slate-600 hover:bg-white/60 font-medium' }}"
                        @if($isActive) aria-current="page" @endif>
                        {{ $tab['label'] }}
                        <span class="tabular-nums ml-0.5 {{ $isActive ? 'opacity-90' : 'opacity-70' }}">{{ number_format($tab['count']) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-12">
                            <input type="checkbox" id="select-all" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" title="Select all">
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-16">S.No</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Domain</th>
                        <th class="hidden md:table-cell px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">API URL</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="hidden lg:table-cell px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Last Checked</th>
                        <th class="hidden lg:table-cell px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Last Error</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200" id="domains-table-body">
                    @forelse($domains ?? [] as $domain)
                        <tr class="hover:bg-slate-50/50 transition-colors domain-row" data-search="{{ strtolower($domain->domain . ' ' . ($domain->api_url ?? '') . ' ' . ($domain->status ?? '') . ' ' . ($domain->notes ?? '')) }}">
                            <td class="px-6 py-4">
                                <input type="checkbox" form="bulk-delete-form" name="domain_ids[]" value="{{ $domain->id }}" class="domain-checkbox rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">{{ ($domains->currentPage() - 1) * $domains->perPage() + $loop->iteration }}</td>
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-slate-800">{{ $domain->domain }}</td>
                            <td class="hidden md:table-cell px-6 py-4 text-sm text-slate-600">{{ Str::limit($domain->api_url ?? '-', 40) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $status = $domain->status ?? 'inactive';
                                    $label = $status === 'inactive' ? 'Not connected' : ucfirst($status);
                                @endphp
                                <span class="px-2.5 py-0.5 text-xs font-medium rounded-full {{ $status === 'active' ? 'bg-emerald-100 text-emerald-700' : ($status === 'error' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600') }}">{{ $label }}</span>
                            </td>
                            <td class="hidden lg:table-cell px-6 py-4 text-sm text-slate-500">{{ $domain->last_checked_at ? $domain->last_checked_at->diffForHumans() : '-' }}</td>
                            <td class="hidden lg:table-cell px-6 py-4 text-sm max-w-xs" title="{{ $domain->last_health_error ?? '' }}">
                                @if($domain->last_health_error)
                                    <span class="text-red-600">{{ Str::limit($domain->last_health_error, 50) }}</span>
                                @elseif(($domain->status ?? '') === 'error')
                                    <span class="text-amber-600">No details</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <form method="POST" action="{{ route('domains.recheck', $domain) }}" class="inline" title="Re-check health">
                                        @csrf
                                        <button type="submit" class="p-2 rounded-lg text-slate-500 hover:bg-emerald-50 hover:text-emerald-600 transition-colors" title="Re-check health">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        </button>
                                    </form>
                                    <a href="{{ route('domains.edit', $domain) }}" title="Edit" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-emerald-600 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    <form method="POST" action="{{ route('domains.destroy', $domain) }}" class="inline" onsubmit="return confirm('Delete this domain? All related batch links and data will be removed.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Delete" class="p-2 rounded-lg text-slate-500 hover:bg-red-50 hover:text-red-600 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-slate-500">
                                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                                </svg>
                                <p class="mt-2">No domains yet. Import from Excel or add manually.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($domains->hasPages())
            <div class="px-6 py-4 border-t border-slate-200">
                {{ $domains->links() }}
            </div>
        @endif
    </div>

    @if(isset($imports) && $imports->isNotEmpty())
        <div class="mt-8">
            <h3 class="text-lg font-semibold text-slate-800 mb-3">Import history</h3>
            <p class="text-sm text-slate-500 mb-3">Deleting an import file does <strong>not</strong> remove the domains it added. Use <strong>Delete imported domains</strong> to remove those sites from your list.</p>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">File</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Rows / Imported / Skipped</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Linked domains</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @foreach($imports as $imp)
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4 text-sm text-slate-700">{{ basename($imp->filename) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2.5 py-0.5 text-xs font-medium rounded-full {{ $imp->status === 'completed' ? 'bg-emerald-100 text-emerald-700' : ($imp->status === 'failed' ? 'bg-red-100 text-red-700' : ($imp->status === 'processing' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600')) }}">{{ ucfirst($imp->status ?? 'pending') }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600">{{ $imp->total_rows ?? 0 }} / {{ $imp->imported_count ?? 0 }} / {{ $imp->skipped_count ?? 0 }}</td>
                                    <td class="px-6 py-4 text-sm text-slate-600">{{ $imp->domains_count ?? 0 }}</td>
                                    <td class="px-6 py-4 text-sm text-slate-500">{{ $imp->created_at?->format('M d, Y H:i') ?? '-' }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="inline-flex items-center gap-1 justify-end">
                                        @if(($imp->domains_count ?? 0) > 0)
                                            <form method="POST" action="{{ route('domains.imports.destroy-domains', $imp) }}" class="inline" onsubmit="return confirm('Delete {{ $imp->domains_count }} domain(s) imported from this file? This cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="Delete imported domains" class="px-2 py-1 text-xs font-medium rounded-lg bg-red-50 text-red-700 hover:bg-red-100">Delete imported domains</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('domains.imports.destroy', $imp) }}" class="inline" onsubmit="return confirm('Delete only this import record{{ ($imp->domains_count ?? 0) > 0 ? ' (domains will stay in your list)' : '' }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="Delete import record" class="p-2 rounded-lg text-slate-500 hover:bg-red-50 hover:text-red-600 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@push('modals')
<div id="add-domain-modal" class="hidden fixed inset-0 z-[100] overflow-y-auto" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/60" onclick="document.getElementById('add-domain-modal').classList.add('hidden'); document.body.classList.remove('overflow-hidden');"></div>
    <div class="relative flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-lg max-w-md w-full p-6 z-10">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Add Domain</h3>
            <form method="POST" action="{{ route('domains.store') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Domain</label>
                        <input type="text" name="domain" required placeholder="example.com" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">API URL</label>
                        <input type="url" name="api_url" required placeholder="http://example.com/api (https also OK)" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <p class="text-xs text-slate-500 mt-1">Use <code class="text-xs bg-slate-100 px-1 rounded">http://</code> if the site has no SSL certificate.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">API Key (optional)</label>
                        <input type="text" name="api_key" placeholder="Your API key" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Notes (optional)</label>
                        <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="document.getElementById('add-domain-modal').classList.add('hidden'); document.body.classList.remove('overflow-hidden');" class="flex-1 px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
function openAddDomainModal() {
    document.getElementById('add-domain-modal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}
(function() {
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.domain-checkbox');
    const bulkForm = document.getElementById('bulk-delete-form');
    const bulkCount = document.getElementById('bulk-count');
    if (!bulkForm || !bulkCount) return;

    function updateBulkBar() {
        const checked = Array.from(checkboxes).filter(cb => cb.checked);
        bulkCount.textContent = checked.length + ' selected';
        bulkForm.classList.toggle('hidden', checked.length === 0);
        if (selectAll) {
            selectAll.checked = checked.length > 0 && checked.length === checkboxes.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < checkboxes.length;
        }
    }

    window.clearSelection = function() {
        checkboxes.forEach(cb => { cb.checked = false; });
        if (selectAll) selectAll.checked = false;
        updateBulkBar();
    };

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => { cb.checked = selectAll.checked; });
            updateBulkBar();
        });
    }
    checkboxes.forEach(cb => cb.addEventListener('change', updateBulkBar));
    updateBulkBar();
})();

(function() {
    const input = document.getElementById('domain-search');
    if (!input) return;
    const rows = Array.from(document.querySelectorAll('#domains-table-body .domain-row'));

    function filterRows() {
        const q = input.value.toLowerCase().trim();
        rows.forEach(row => {
            const haystack = row.getAttribute('data-search') || '';
            const match = q === '' || haystack.includes(q);
            row.style.display = match ? '' : 'none';
        });
    }

    input.addEventListener('input', filterRows);
    filterRows();
})();
</script>
@endsection
