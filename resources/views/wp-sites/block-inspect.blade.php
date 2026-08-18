@extends('layouts.dashboard')
@section('title', 'Block Inspect')
@section('page-title', 'Block Inspect')

@section('content')
@php
    $inspectFilter = $inspectFilter ?? 'all';
    $filterCounts = $filterCounts ?? ['all' => 0, 'on' => 0, 'off' => 0, 'unknown' => 0, 'unsupported' => 0];
    $listQuery = $listQuery ?? [];
    $preserveFields = function () use ($search, $inspectFilter) {
        $html = '';
        if (($search ?? '') !== '') {
            $html .= '<input type="hidden" name="search" value="'.e($search).'">';
        }
        if ($inspectFilter !== 'all') {
            $html .= '<input type="hidden" name="inspect" value="'.e($inspectFilter).'">';
        }

        return $html;
    };
@endphp
<div class="page-enter">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-slate-800">Block View Source / Inspect</h2>
        <p class="text-slate-500 mt-1 text-sm">Enable or disable inspect blocking on WordPress sites (plugin v1.3.0+). Light deterrent only — not real security.</p>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
        @php
            $statCards = [
                ['key' => 'all', 'label' => 'Total sites', 'value' => $stats['total'] ?? 0, 'border' => 'border-slate-200', 'text' => 'text-slate-800'],
                ['key' => 'on', 'label' => 'Blocking ON', 'value' => $filterCounts['on'] ?? 0, 'border' => 'border-emerald-200', 'text' => 'text-emerald-600'],
                ['key' => 'off', 'label' => 'Blocking OFF', 'value' => $filterCounts['off'] ?? 0, 'border' => 'border-slate-200', 'text' => 'text-slate-700'],
                ['key' => 'unknown', 'label' => 'Unknown', 'value' => $filterCounts['unknown'] ?? 0, 'border' => 'border-amber-200', 'text' => 'text-amber-600'],
                ['key' => 'unsupported', 'label' => 'Unsupported', 'value' => $filterCounts['unsupported'] ?? 0, 'border' => 'border-red-200', 'text' => 'text-red-600'],
            ];
        @endphp
        @foreach($statCards as $card)
            @php
                $cardQuery = $listQuery;
                if ($card['key'] === 'all') {
                    unset($cardQuery['inspect']);
                } else {
                    $cardQuery['inspect'] = $card['key'];
                }
                $isActive = $inspectFilter === $card['key'] || ($card['key'] === 'all' && $inspectFilter === 'all');
            @endphp
            <a href="{{ route('wp-sites.block-inspect', $cardQuery) }}"
               class="block bg-white rounded-xl border p-4 shadow-sm transition-all hover:shadow-md {{ $card['border'] }} {{ $isActive ? 'ring-2 ring-sky-500/40 ring-offset-1' : '' }}">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">{{ $card['label'] }}</p>
                <p class="text-2xl font-bold mt-1 tabular-nums {{ $card['text'] }}">{{ number_format($card['value']) }}</p>
            </a>
        @endforeach
    </div>

    <details class="mb-6 bg-white rounded-xl border border-slate-200 shadow-sm group">
        <summary class="px-5 py-4 cursor-pointer list-none flex items-center justify-between gap-3 select-none">
            <div>
                <span class="font-semibold text-slate-800">Manual domains</span>
                <span class="text-sm text-slate-500 ml-2">Paste domains one per line to block or unblock</span>
            </div>
            <svg class="w-5 h-5 text-slate-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </summary>
        <div class="px-6 py-5 border-t border-slate-100">
            <form method="POST" action="{{ route('wp-sites.block-inspect.toggle-manual') }}" id="manual-domains-form" class="space-y-4">
                @csrf
                {!! $preserveFields() !!}
                <textarea
                    name="domains"
                    rows="5"
                    placeholder="example.com&#10;anotherdomain.cyou"
                    class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-3 font-mono text-sm text-slate-800 shadow-sm placeholder:text-slate-400 transition-colors focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                >{{ old('domains') }}</textarea>
                <p class="text-xs text-slate-500">Domains must already exist in WP Sites. Unsupported or inactive sites are skipped. Jobs run on the <code class="text-xs">wp_sites</code> queue.</p>
                <div class="flex flex-wrap items-center gap-3 pt-1">
                    <button type="submit" name="block_inspect" value="1"
                        onclick="return confirm('Enable inspect blocking on the listed domains?');"
                        class="px-4 py-2.5 bg-sky-600 text-white text-sm font-medium rounded-lg hover:bg-sky-700 transition-colors">
                        Block domains
                    </button>
                    <button type="submit" name="block_inspect" value="0"
                        onclick="return confirm('Disable inspect blocking on the listed domains?');"
                        class="px-4 py-2.5 border border-slate-300 bg-white text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors">
                        Unblock domains
                    </button>
                </div>
            </form>
        </div>
    </details>

    <div class="mb-4 hidden items-center justify-between gap-3 px-4 py-3 bg-sky-50 rounded-xl border border-sky-200" id="selection-bar">
        <span id="selection-count" class="text-sm font-medium text-sky-800">0 selected</span>
        <div class="flex flex-wrap gap-2">
            <button type="submit" form="selected-toggle-form" name="block_inspect" value="1"
                onclick="return confirm('Enable inspect blocking on selected sites?');"
                class="px-3 py-1.5 text-sm font-medium rounded-lg bg-sky-600 text-white hover:bg-sky-700 transition-colors">
                Enable selected
            </button>
            <button type="submit" form="selected-toggle-form" name="block_inspect" value="0"
                onclick="return confirm('Disable inspect blocking on selected sites?');"
                class="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition-colors">
                Disable selected
            </button>
            <button type="button" id="clear-selection" class="px-3 py-1.5 text-sm text-slate-600 hover:text-slate-800">Clear</button>
        </div>
    </div>

    <form id="selected-toggle-form" method="POST" action="{{ route('wp-sites.block-inspect.toggle-selected') }}" class="hidden">
        @csrf
        {!! $preserveFields() !!}
    </form>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-4 sm:px-6 py-4 border-b border-slate-200 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="min-w-0">
                <h3 class="text-lg font-semibold text-slate-800">WP Sites ({{ number_format($sites->total()) }})</h3>
                <p class="text-sm text-slate-500 mt-0.5">
                    {{ number_format($stats['active'] ?? 0) }} connected · {{ number_format($filterCounts['on'] ?? 0) }} blocking ON
                    @if(($search ?? '') !== '')
                        · searching “{{ Str::limit($search, 40) }}”
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3 shrink-0">
                <a href="{{ route('wp-sites.index') }}" class="px-3 py-2 text-sm text-sky-600 hover:text-sky-700 border border-sky-200 rounded-lg bg-sky-50 hover:bg-sky-100 transition-colors">Manage WP Sites</a>
                <button type="submit" form="toggle-all-on-form" class="px-3 py-2 text-sm font-medium rounded-lg bg-sky-600 text-white hover:bg-sky-700 transition-colors">
                    Enable all active
                </button>
                <button type="submit" form="toggle-all-off-form" class="px-3 py-2 text-sm font-medium rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition-colors">
                    Disable all active
                </button>
            </div>
        </div>

        <div class="px-4 sm:px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
            <form method="GET" action="{{ route('wp-sites.block-inspect') }}" class="flex flex-col sm:flex-row sm:items-stretch gap-2 w-full xl:max-w-2xl">
                @if($inspectFilter !== 'all')
                    <input type="hidden" name="inspect" value="{{ $inspectFilter }}">
                @endif
                <div class="relative flex-1 min-w-0">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M5 11a6 6 0 1112 0 6 6 0 01-12 0z"/></svg>
                    </span>
                    <input
                        type="search"
                        name="search"
                        value="{{ $search ?? '' }}"
                        placeholder="Search domain or API URL..."
                        class="block w-full h-full rounded-lg border border-slate-200 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-800 shadow-sm placeholder:text-slate-400 transition-colors focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                    >
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    @if(($search ?? '') !== '')
                        <a href="{{ route('wp-sites.block-inspect', $inspectFilter !== 'all' ? ['inspect' => $inspectFilter] : []) }}" class="inline-flex items-center px-3 py-2.5 text-sm font-medium text-slate-600 hover:text-slate-800 whitespace-nowrap">Clear</a>
                    @endif
                    <button type="submit" class="inline-flex items-center justify-center px-4 py-2.5 bg-sky-600 text-white text-sm font-medium rounded-lg shadow-sm hover:bg-sky-700 transition-colors whitespace-nowrap">
                        Search
                    </button>
                </div>
            </form>
            <div class="inline-flex flex-wrap rounded-xl border border-slate-200 bg-slate-100/80 p-1 gap-1 xl:justify-end" role="tablist">
                    @php
                        $filterTabs = [
                            'all' => ['label' => 'All', 'activeClass' => 'bg-slate-700 text-white'],
                            'on' => ['label' => 'ON', 'activeClass' => 'bg-emerald-600 text-white'],
                            'off' => ['label' => 'OFF', 'activeClass' => 'bg-slate-600 text-white'],
                            'unknown' => ['label' => 'Unknown', 'activeClass' => 'bg-amber-500 text-white'],
                            'unsupported' => ['label' => 'Unsupported', 'activeClass' => 'bg-red-600 text-white'],
                        ];
                    @endphp
                    @foreach($filterTabs as $key => $tab)
                        @php
                            $tabQuery = $listQuery;
                            if ($key === 'all') {
                                unset($tabQuery['inspect']);
                            } else {
                                $tabQuery['inspect'] = $key;
                            }
                            $isActive = $inspectFilter === $key;
                        @endphp
                        <a href="{{ route('wp-sites.block-inspect', $tabQuery) }}"
                           class="px-3 py-1.5 text-sm rounded-lg transition-colors {{ $isActive ? $tab['activeClass'].' font-semibold shadow-sm' : 'text-slate-600 hover:bg-white/60 font-medium' }}"
                           @if($isActive) aria-current="page" @endif>
                            {{ $tab['label'] }}
                            <span class="tabular-nums ml-0.5 {{ $isActive ? 'opacity-90' : 'opacity-70' }}">{{ number_format($filterCounts[$key] ?? 0) }}</span>
                        </a>
                    @endforeach
            </div>
        </div>

        <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left w-10">
                                <input type="checkbox" id="select-all-sites" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" title="Select all on page">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Domain</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Connection</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Block inspect</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Last synced</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($sites as $site)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-4 py-4">
                                    @if($site->block_inspect_supported)
                                        <input type="checkbox" form="selected-toggle-form" name="wp_site_ids[]" value="{{ $site->id }}" class="site-checkbox rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-slate-800">{{ $site->domain }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php $conn = $site->status ?? 'inactive'; @endphp
                                    <span class="px-2.5 py-0.5 text-xs font-medium rounded-full {{ $conn === 'active' ? 'bg-sky-100 text-sky-700' : ($conn === 'error' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600') }}">{{ ucfirst($conn) }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if(! $site->block_inspect_supported)
                                        <span class="px-2.5 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-700">Unsupported</span>
                                    @elseif($site->block_inspect === null)
                                        <span class="px-2.5 py-0.5 text-xs font-medium rounded-full bg-amber-100 text-amber-800">Unknown</span>
                                    @elseif($site->block_inspect)
                                        <span class="px-2.5 py-0.5 text-xs font-medium rounded-full bg-emerald-100 text-emerald-700">ON</span>
                                    @else
                                        <span class="px-2.5 py-0.5 text-xs font-medium rounded-full bg-slate-100 text-slate-600">OFF</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-500">{{ $site->block_inspect_synced_at ? $site->block_inspect_synced_at->diffForHumans() : '—' }}</td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    @if($site->block_inspect_supported && $site->status === 'active')
                                        <div class="inline-flex items-center gap-2">
                                            <form method="POST" action="{{ route('wp-sites.toggle-inspect', $site) }}" class="inline">
                                                @csrf
                                                {!! $preserveFields() !!}
                                                <input type="hidden" name="block_inspect" value="1">
                                                <button type="submit" class="text-xs px-2.5 py-1 rounded-md font-medium {{ $site->block_inspect ? 'bg-sky-100 text-sky-400 cursor-default' : 'bg-sky-50 text-sky-700 hover:bg-sky-100' }}" {{ $site->block_inspect ? 'disabled' : '' }}>ON</button>
                                            </form>
                                            <form method="POST" action="{{ route('wp-sites.toggle-inspect', $site) }}" class="inline">
                                                @csrf
                                                {!! $preserveFields() !!}
                                                <input type="hidden" name="block_inspect" value="0">
                                                <button type="submit" class="text-xs px-2.5 py-1 rounded-md font-medium {{ $site->block_inspect === false ? 'bg-slate-100 text-slate-400 cursor-default' : 'bg-slate-50 text-slate-700 hover:bg-slate-100' }}" {{ $site->block_inspect === false ? 'disabled' : '' }}>OFF</button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                    <p>No WP sites match your search or filter.</p>
                                    @if(($search ?? '') !== '' || $inspectFilter !== 'all')
                                        <a href="{{ route('wp-sites.block-inspect') }}" class="inline-block mt-2 text-sky-600 hover:underline">Clear filters</a>
                                    @else
                                        <a href="{{ route('wp-sites.index') }}" class="inline-block mt-2 text-sky-600 hover:underline">Add WP sites</a>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($sites->hasPages())
                <div class="px-6 py-4 border-t border-slate-200">
                    {{ $sites->links() }}
                </div>
            @endif
        </div>

    <form id="toggle-all-on-form" method="POST" action="{{ route('wp-sites.block-inspect.toggle-all') }}" class="hidden" onsubmit="return confirm('Enable inspect blocking on all active supported WP sites?');">
        @csrf
        {!! $preserveFields() !!}
        <input type="hidden" name="block_inspect" value="1">
    </form>
    <form id="toggle-all-off-form" method="POST" action="{{ route('wp-sites.block-inspect.toggle-all') }}" class="hidden" onsubmit="return confirm('Disable inspect blocking on all active supported WP sites?');">
        @csrf
        {!! $preserveFields() !!}
        <input type="hidden" name="block_inspect" value="0">
    </form>
</div>

<script>
(function () {
    const selectAll = document.getElementById('select-all-sites');
    const checkboxes = () => Array.from(document.querySelectorAll('.site-checkbox'));
    const selectionBar = document.getElementById('selection-bar');
    const selectionCount = document.getElementById('selection-count');
    const clearBtn = document.getElementById('clear-selection');

    function updateSelectionUi() {
        const boxes = checkboxes();
        const checked = boxes.filter(cb => cb.checked);
        const n = checked.length;
        if (selectionBar) {
            selectionBar.classList.toggle('hidden', n === 0);
            selectionBar.classList.toggle('flex', n > 0);
        }
        if (selectionCount) {
            selectionCount.textContent = n + ' selected';
        }
        if (selectAll && boxes.length) {
            selectAll.indeterminate = n > 0 && n < boxes.length;
            selectAll.checked = n === boxes.length;
        }
    }

    selectAll?.addEventListener('change', function () {
        checkboxes().forEach(cb => { cb.checked = this.checked; });
        updateSelectionUi();
    });

    checkboxes().forEach(cb => cb.addEventListener('change', updateSelectionUi));

    clearBtn?.addEventListener('click', function () {
        checkboxes().forEach(cb => { cb.checked = false; });
        if (selectAll) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }
        updateSelectionUi();
    });
})();
</script>
@endsection
