<div class="inline-flex rounded-xl border border-slate-200 bg-slate-100/80 p-1 gap-1 mb-4" role="tablist">
    <a href="{{ route($route, array_filter(['search' => ($search ?? '') !== '' ? $search : null])) }}"
       class="px-3 py-1.5 text-sm font-medium rounded-lg {{ ($view ?? 'all') === 'all' ? ($activeClass ?? 'bg-emerald-600 text-white') : 'text-slate-600 hover:bg-white' }}">
        All
    </a>
    <a href="{{ route($route, array_filter(['view' => 'deleting', 'search' => ($search ?? '') !== '' ? $search : null])) }}"
       class="px-3 py-1.5 text-sm font-medium rounded-lg {{ ($view ?? 'all') === 'deleting' ? 'bg-sky-600 text-white' : 'text-slate-600 hover:bg-white' }}">
        Deleting
        @if(($deletingCount ?? 0) > 0)
            <span class="ml-1">{{ number_format($deletingCount) }}</span>
        @endif
    </a>
</div>
