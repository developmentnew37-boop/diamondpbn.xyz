@extends('layouts.dashboard')
@section('title', 'Create WP Batch')
@section('page-title', 'Create WP Batch')

@section('content')
    @php
        $initialStep = 1;
        if ($errors->has('wp_site_ids')) $initialStep = 2;
        elseif ($errors->has('links') || $errors->has('links_bulk') || $errors->has('links.*')) $initialStep = 3;
    @endphp
    <div class="page-enter max-w-8xl" x-data="{ step: {{ $initialStep }} }">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-slate-800">New WP Batch</h2>
            <p class="text-slate-500 mt-1">Create a named batch and post links across your WordPress sites</p>
        </div>
        <div class="mb-6 overflow-x-auto">
            <div class="flex flex-nowrap items-center gap-2 sm:gap-0 min-w-max">
                <div class="flex items-center">
                    <div :class="step >= 1 ? 'bg-sky-600 text-white' : 'bg-slate-200 text-slate-500'"
                        class="w-10 h-10 rounded-full flex items-center justify-center font-semibold transition-colors">1
                    </div>
                    <span class="ml-2 text-sm font-medium" :class="step >= 1 ? 'text-slate-800' : 'text-slate-500'">Batch
                        Info</span>
                </div>
                <div class="flex-1 min-w-[20px] h-0.5 mx-2 sm:mx-4 bg-slate-200">
                    <div class="h-full bg-sky-600 transition-all duration-300"
                        :style="'width: ' + (step >= 2 ? 100 : (step >= 1 ? 50 : 0)) + '%'"></div>
                </div>
                <div class="flex items-center">
                    <div :class="step >= 2 ? 'bg-sky-600 text-white' : 'bg-slate-200 text-slate-500'"
                        class="w-10 h-10 rounded-full flex items-center justify-center font-semibold transition-colors">2
                    </div>
                    <span class="ml-2 text-sm font-medium" :class="step >= 2 ? 'text-slate-800' : 'text-slate-500'">Select
                        WP Sites</span>
                </div>
                <div class="flex-1 min-w-[20px] h-0.5 mx-2 sm:mx-4 bg-slate-200">
                    <div class="h-full bg-sky-600 transition-all duration-300"
                        :style="'width: ' + (step >= 3 ? 100 : (step >= 2 ? 50 : 0)) + '%'"></div>
                </div>
                <div class="flex items-center">
                    <div :class="step >= 3 ? 'bg-sky-600 text-white' : 'bg-slate-200 text-slate-500'"
                        class="w-10 h-10 rounded-full flex items-center justify-center font-semibold transition-colors">3
                    </div>
                    <span class="ml-2 text-sm font-medium" :class="step >= 3 ? 'text-slate-800' : 'text-slate-500'">Add
                        Links</span>
                </div>
            </div>
        </div>
        @if ($errors->any())
            <div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 text-red-800" role="alert">
                <p class="font-medium mb-2">Please fix the following errors:</p>
                <ul class="list-disc list-inside text-sm space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 md:p-8 w-full">
            <form method="POST" action="{{ route('wp-batches.store') }}" id="wp-batch-form">
                @csrf
                <div x-show="step === 1" x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 transform translate-y-2"
                    x-transition:enter-end="opacity-100 transform translate-y-0">
                    <h3 class="text-lg font-semibold text-slate-800 mb-4">Batch Information</h3>
                    <div class="space-y-4 w-full max-w-8xl">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Batch Name</label>
                            <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Campaign Jan 2025" required
                                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 {{ $errors->has('name') ? 'border-red-500' : '' }}">
                            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Description (optional)</label>
                            <textarea name="description" rows="3" placeholder="Optional notes..."
                                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-sky-500 focus:ring-sky-500">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </div>
                <div x-show="step === 2" x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 transform translate-y-2"
                    x-transition:enter-end="opacity-100 transform translate-y-0" x-cloak
                    x-data="{ siteMode: 'list' }">
                    <h3 class="text-lg font-semibold text-slate-800 mb-2">Select Target WP Sites</h3>
                    <p class="text-slate-500 text-sm mb-4">Choose which WordPress sites to post links to. You can either select from the list
                        or paste a list of domains and we will match them to the sites in your account.</p>
                    @error('wp_site_ids')<p class="text-sm text-red-600 mb-2">{{ $message }}</p>@enderror

                    <div class="flex items-center gap-2 mb-4 border-b border-slate-200">
                        <button type="button"
                            class="px-3 py-2 text-sm font-medium border-b-2"
                            :class="siteMode === 'list' ? 'border-sky-600 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
                            @click="siteMode = 'list'">
                            Select from list
                        </button>
                        <button type="button"
                            class="px-3 py-2 text-sm font-medium border-b-2"
                            :class="siteMode === 'bulk' ? 'border-sky-600 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
                            @click="siteMode = 'bulk'">
                            Bulk paste domains
                        </button>
                    </div>

                    <div x-show="siteMode === 'list'" x-cloak>
                    <div class="flex flex-wrap items-end gap-3 mb-4 p-4 bg-slate-50 rounded-lg border border-slate-200">
                        <span class="text-sm font-medium text-slate-700">Select</span>
                        <input type="number" id="select-n-count" min="1" placeholder="e.g. 57"
                            class="w-24 rounded-lg border-slate-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 text-sm">
                        <span class="text-sm text-slate-600">sites</span>
                        <select id="select-n-mode" class="rounded-lg border-slate-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 text-sm">
                            <option value="top">from top</option>
                            <option value="bottom">from bottom</option>
                            <option value="random">random</option>
                        </select>
                        <button type="button" id="select-n-btn" class="px-3 py-1.5 bg-sky-600 text-white text-sm rounded-lg hover:bg-sky-700">
                            Apply
                        </button>
                        <span class="text-xs text-slate-500" id="site-total-hint">({{ count($wpSites ?? []) }} total)</span>
                    </div>
                    </div>

                    <div x-show="siteMode === 'bulk'" x-cloak>
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-sm font-medium text-slate-700">Bulk select by domain names</label>
                            <button type="button" id="bulk-site-select-all"
                                class="text-xs font-medium text-slate-600 hover:text-slate-900">
                                Select all sites
                            </button>
                        </div>
                        <textarea id="bulk-site-input" rows="6" placeholder="example.com&#10;anotherdomain.com"
                            class="w-full rounded-lg border-slate-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 text-sm"></textarea>
                        <p class="text-xs text-slate-500 mt-1">Paste one domain per line. We will select matching WP sites from the list below.
                            Domains not present in your account will be ignored.</p>
                        <p id="bulk-site-missing" class="hidden text-xs text-amber-600 mt-1"></p>
                        <button type="button" id="bulk-site-apply"
                            class="mt-2 inline-flex items-center px-3 py-1.5 bg-slate-800 text-white text-xs rounded-lg hover:bg-slate-900">
                            Apply pasted domains
                        </button>
                    </div>
                    <p class="text-xs text-slate-500 mb-3">After applying, you can still switch back to the list tab to review or tweak the selection.</p>
                    </div>

                    <div class="border border-slate-200 rounded-lg p-4 max-h-64 overflow-y-auto w-full">
                        <label class="flex items-center gap-2 p-2 hover:bg-slate-50 rounded cursor-pointer" id="select-all-label">
                            <input type="checkbox" id="select-all-cb" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                            Select All
                        </label>
                        <div class="mt-2 space-y-1" id="site-list">
                            @forelse($wpSites ?? [] as $wpSite)
                                <label class="flex items-center gap-2 p-2 hover:bg-slate-50 rounded cursor-pointer">
                                    <input type="checkbox" name="wp_site_ids[]" value="{{ $wpSite->id }}"
                                        {{ in_array($wpSite->id, old('wp_site_ids', [])) ? 'checked' : '' }}
                                        class="rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                                        data-domain="{{ strtolower($wpSite->domain) }}">
                                    <span class="text-sm">{{ $wpSite->domain }}</span>
                                </label>
                            @empty
                                <p class="text-slate-500 text-sm py-4">No WP sites available. <a
                                        href="{{ route('wp-sites.index') }}" class="text-sky-600 hover:underline">Import
                                        WP sites first</a>.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div x-show="step === 3" x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 transform translate-y-2"
                    x-transition:enter-end="opacity-100 transform translate-y-0" x-cloak>
                    <h3 class="text-lg font-semibold text-slate-800 mb-4">Add Links</h3>

                    <div class="space-y-4 mb-6">
                        @error('links')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        @error('links.*')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        <p class="text-slate-500 text-sm">Add URLs and keywords line by line. Line 1 keyword pairs with line 1 link, line 2 with line 2, etc. Both must have the <strong>same number of lines</strong>.</p>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Links (URLs) — one per line</label>
                                <textarea name="links_bulk" id="links_bulk" rows="12" placeholder="https://example.com/page1&#10;https://example.com/page2&#10;https://example.com/page3"
                                    class="w-full rounded-lg border-slate-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 font-mono text-sm"></textarea>
                                <p class="text-xs text-slate-500 mt-1" id="links-count">0 lines</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Keywords (anchor text) — one per line</label>
                                <textarea name="keywords_bulk" id="keywords_bulk" rows="12" placeholder="keyword one&#10;keyword two&#10;keyword three"
                                    class="w-full rounded-lg border-slate-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 font-mono text-sm"></textarea>
                                <p class="text-xs text-slate-500 mt-1" id="keywords-count">0 lines</p>
                            </div>
                        </div>
                        <p class="text-sm text-amber-600 hidden" id="bulk-mismatch">⚠️ Links and keywords must have the same number of lines.</p>
                    </div>

                    <div class="border-t border-slate-200 pt-4">
                        <p class="text-sm font-medium text-slate-700 mb-3">Or paste JSON (optional)</p>
                        <textarea name="links_json" rows="4"
                            placeholder='[{"url":"https://example.com","keyword":"anchor text","no_follow":0}]'
                            class="w-full rounded-lg border-slate-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 font-mono text-sm"></textarea>
                    </div>
                </div>
                <div
                    class="flex flex-col-reverse sm:flex-row justify-between items-stretch sm:items-center gap-4 mt-8 pt-6 border-t border-slate-200">
                    <button type="button" @click="step = Math.max(1, step - 1)" x-show="step > 1"
                        class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 w-full sm:w-auto">
                        Back
                    </button>
                    <div class="flex justify-end gap-3 w-full sm:w-auto">
                        <template x-if="step < 3">
                            <button type="button" @click="step++"
                                class="px-4 py-2 bg-sky-600 text-white rounded-lg hover:bg-sky-700">
                                Next
                            </button>
                        </template>
                        <template x-if="step === 3">
                            <button type="submit" id="submit-btn"
                                class="px-4 py-2 bg-sky-600 text-white rounded-lg hover:bg-sky-700">
                                Create & Post Links
                            </button>
                        </template>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
@section('scripts')
    <script>
        (function() {
            const form = document.getElementById('wp-batch-form');
            const linksBulk = document.getElementById('links_bulk');
            const keywordsBulk = document.getElementById('keywords_bulk');
            const linksCountEl = document.getElementById('links-count');
            const keywordsCountEl = document.getElementById('keywords-count');
            const mismatchEl = document.getElementById('bulk-mismatch');

            const selectAllCb = document.getElementById('select-all-cb');
            const siteCheckboxes = () => form ? [...form.querySelectorAll('input[name="wp_site_ids[]"]')] : [];
            if (selectAllCb) {
                selectAllCb.addEventListener('change', function() {
                    siteCheckboxes().forEach(cb => cb.checked = this.checked);
                });
            }

            const selectNCount = document.getElementById('select-n-count');
            const selectNMode = document.getElementById('select-n-mode');
            const selectNBtn = document.getElementById('select-n-btn');
            if (selectNBtn && selectNCount && selectNMode) {
                selectNBtn.addEventListener('click', function() {
                    const n = parseInt(selectNCount.value, 10);
                    const mode = selectNMode.value;
                    const cbs = siteCheckboxes();
                    if (!n || n < 1 || cbs.length === 0) return;
                    const total = cbs.length;
                    const count = Math.min(n, total);
                    siteCheckboxes().forEach(cb => cb.checked = false);
                    if (mode === 'top') {
                        cbs.slice(0, count).forEach(cb => cb.checked = true);
                    } else if (mode === 'bottom') {
                        cbs.slice(-count).forEach(cb => cb.checked = true);
                    } else {
                        const shuffled = cbs.slice().sort(() => Math.random() - 0.5);
                        shuffled.slice(0, count).forEach(cb => cb.checked = true);
                    }
                    if (selectAllCb) selectAllCb.checked = (count === total);
                });
            }

            const bulkSiteInput = document.getElementById('bulk-site-input');
            const bulkSiteApply = document.getElementById('bulk-site-apply');
            const bulkSiteMissing = document.getElementById('bulk-site-missing');
            const bulkSiteSelectAll = document.getElementById('bulk-site-select-all');

            function normalizeDomain(str) {
                if (!str) return '';
                let s = str.trim().toLowerCase();
                s = s.replace(/^https?:\/\//, '');
                s = s.replace(/^www\./, '');
                s = s.replace(/\/+$/, '');
                return s;
            }

            function buildSiteMap() {
                const map = {};
                siteCheckboxes().forEach(cb => {
                    const raw = cb.getAttribute('data-domain') || '';
                    const norm = normalizeDomain(raw);
                    if (norm) {
                        map[norm] = cb;
                    }
                });
                return map;
            }

            function applyBulkSites() {
                if (!bulkSiteInput) return;
                const lines = (bulkSiteInput.value || '').split(/\r?\n/).map(l => normalizeDomain(l)).filter(Boolean);
                if (lines.length === 0) {
                    return;
                }

                const map = buildSiteMap();
                let matched = 0;
                let missing = [];

                siteCheckboxes().forEach(cb => { cb.checked = false; });

                lines.forEach(line => {
                    const cb = map[line];
                    if (cb) {
                        cb.checked = true;
                        matched++;
                    } else {
                        missing.push(line);
                    }
                });

                if (bulkSiteMissing) {
                    if (missing.length) {
                        bulkSiteMissing.textContent = 'These domains were not found in your account and were ignored: ' + missing.slice(0, 5).join(', ') + (missing.length > 5 ? ' +' + (missing.length - 5) + ' more' : '');
                        bulkSiteMissing.classList.remove('hidden');
                    } else {
                        bulkSiteMissing.textContent = '';
                        bulkSiteMissing.classList.add('hidden');
                    }
                }
            }

            if (bulkSiteApply) {
                bulkSiteApply.addEventListener('click', applyBulkSites);
            }

            if (bulkSiteSelectAll) {
                bulkSiteSelectAll.addEventListener('click', function() {
                    siteCheckboxes().forEach(cb => { cb.checked = true; });
                    if (bulkSiteMissing) {
                        bulkSiteMissing.textContent = '';
                        bulkSiteMissing.classList.add('hidden');
                    }
                });
            }

            function parseLines(ta) {
                return (ta?.value || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean);
            }

            function updateCounts() {
                const links = parseLines(linksBulk);
                const keywords = parseLines(keywordsBulk);
                const lc = links.length;
                const kc = keywords.length;
                linksCountEl.textContent = lc + ' line' + (lc !== 1 ? 's' : '');
                keywordsCountEl.textContent = kc + ' line' + (kc !== 1 ? 's' : '');
                if (lc > 0 && kc > 0 && lc !== kc) {
                    mismatchEl.classList.remove('hidden');
                } else {
                    mismatchEl.classList.add('hidden');
                }
            }

            if (linksBulk) linksBulk.addEventListener('input', updateCounts);
            if (keywordsBulk) keywordsBulk.addEventListener('input', updateCounts);

            form?.addEventListener('submit', function(e) {
                e.preventDefault();

                const linksFromBulk = parseLines(linksBulk);
                const keywordsFromBulk = parseLines(keywordsBulk);

                let links = [];

                if (linksFromBulk.length > 0 || keywordsFromBulk.length > 0) {
                    if (linksFromBulk.length !== keywordsFromBulk.length) {
                        alert('Links and keywords must have the same number of lines. You have ' + linksFromBulk.length + ' links and ' + keywordsFromBulk.length + ' keywords.');
                        return;
                    }
                    for (let i = 0; i < linksFromBulk.length; i++) {
                        links.push({
                            url: linksFromBulk[i],
                            keyword: keywordsFromBulk[i],
                            no_follow: false
                        });
                    }
                }

                if (links.length === 0) {
                    const linksJson = form.querySelector('[name="links_json"]')?.value?.trim();
                    if (linksJson) {
                        try {
                            links = JSON.parse(linksJson);
                            if (!Array.isArray(links)) links = [];
                        } catch (_) {}
                    }
                }

                const wpSiteIds = [...form.querySelectorAll('input[name="wp_site_ids[]"]:checked')].map(cb => cb.value);

                if (links.length === 0) {
                    alert('Add at least one link. Use the manual links (URLs + keywords) or paste JSON.');
                    return;
                }
                if (wpSiteIds.length === 0) {
                    alert('Select at least one WP site.');
                    return;
                }

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'links';
                input.value = JSON.stringify(links);
                form.appendChild(input);
                form.submit();
            });
        })();
    </script>
@endsection
