@extends('layouts.dashboard')
@section('title', 'Create Campaign')
@section('page-title', 'Create Campaign')

@section('content')
    @php
        $initialStep = 1;
        if ($errors->has('domain_ids')) $initialStep = 2;
        elseif ($errors->has('links') || $errors->has('links_bulk') || $errors->has('links.*') || $errors->has('links_per_domain')) $initialStep = 3;
    @endphp
    <div class="page-enter max-w-8xl" x-data="{ step: {{ $initialStep }} }">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-slate-800">New Campaign</h2>
            <p class="text-slate-500 mt-1">Distribute limited links across multiple target domains</p>
        </div>
        <div class="mb-6 overflow-x-auto">
            <div class="flex flex-nowrap items-center gap-2 sm:gap-0 min-w-max">
                <div class="flex items-center">
                    <div :class="step >= 1 ? 'bg-purple-600 text-white' : 'bg-slate-200 text-slate-500'"
                        class="w-10 h-10 rounded-full flex items-center justify-center font-semibold transition-colors">1
                    </div>
                    <span class="ml-2 text-sm font-medium" :class="step >= 1 ? 'text-slate-800' : 'text-slate-500'">Campaign Info</span>
                </div>
                <div class="flex-1 min-w-[20px] h-0.5 mx-2 sm:mx-4 bg-slate-200">
                    <div class="h-full bg-purple-600 transition-all duration-300"
                        :style="'width: ' + (step >= 2 ? 100 : (step >= 1 ? 50 : 0)) + '%'"></div>
                </div>
                <div class="flex items-center">
                    <div :class="step >= 2 ? 'bg-purple-600 text-white' : 'bg-slate-200 text-slate-500'"
                        class="w-10 h-10 rounded-full flex items-center justify-center font-semibold transition-colors">2
                    </div>
                    <span class="ml-2 text-sm font-medium" :class="step >= 2 ? 'text-slate-800' : 'text-slate-500'">Select Domains</span>
                </div>
                <div class="flex-1 min-w-[20px] h-0.5 mx-2 sm:mx-4 bg-slate-200">
                    <div class="h-full bg-purple-600 transition-all duration-300"
                        :style="'width: ' + (step >= 3 ? 100 : (step >= 2 ? 50 : 0)) + '%'"></div>
                </div>
                <div class="flex items-center">
                    <div :class="step >= 3 ? 'bg-purple-600 text-white' : 'bg-slate-200 text-slate-500'"
                        class="w-10 h-10 rounded-full flex items-center justify-center font-semibold transition-colors">3
                    </div>
                    <span class="ml-2 text-sm font-medium" :class="step >= 3 ? 'text-slate-800' : 'text-slate-500'">Links & Distribution</span>
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
            <form method="POST" action="{{ route('campaigns.store') }}" id="campaign-form">
                @csrf
                <div x-show="step === 1" x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 transform translate-y-2"
                    x-transition:enter-end="opacity-100 transform translate-y-0">
                    <h3 class="text-lg font-semibold text-slate-800 mb-4">Campaign Information</h3>
                    <div class="space-y-4 w-full max-w-8xl">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Campaign Name</label>
                            <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Q1 2026 Distribution" required
                                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 {{ $errors->has('name') ? 'border-red-500' : '' }}">
                            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Description (optional)</label>
                            <textarea name="description" rows="3" placeholder="Optional notes..."
                                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </div>
                <div x-show="step === 2" x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 transform translate-y-2"
                    x-transition:enter-end="opacity-100 transform translate-y-0" x-cloak
                    x-data="{ domainMode: 'list' }">
                    <h3 class="text-lg font-semibold text-slate-800 mb-2">Select Target Domains</h3>
                    <p class="text-slate-500 text-sm mb-4">Choose which target domains to distribute links to. Only <strong>connected</strong> domains can be selected here.</p>
                    @php
                        $targetDomainStats = $targetDomainStats ?? ['active' => $domains->count(), 'inactive' => 0, 'error' => 0];
                    @endphp
                    <div class="flex flex-col lg:flex-row gap-3 mb-4 w-full">
                        <div class="flex-1 rounded-lg border border-purple-200 bg-purple-50/50 px-4 py-3 text-sm">
                            <span class="text-slate-600">Connected (selectable):</span>
                            <strong class="text-purple-700 ml-1">{{ number_format($targetDomainStats['active'] ?? 0) }}</strong>
                        </div>
                        <div class="flex-1 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                            <span class="text-slate-600">Not connected:</span>
                            <strong class="text-slate-800 ml-1">{{ number_format($targetDomainStats['inactive'] ?? 0) }}</strong>
                        </div>
                        <div class="flex-1 rounded-lg border border-red-200 bg-red-50/50 px-4 py-3 text-sm">
                            <span class="text-slate-600">Error:</span>
                            <strong class="text-red-700 ml-1">{{ number_format($targetDomainStats['error'] ?? 0) }}</strong>
                            @if(($targetDomainStats['inactive'] ?? 0) + ($targetDomainStats['error'] ?? 0) > 0)
                                <a href="{{ route('campaign-domains.index') }}" class="block text-xs text-purple-600 hover:underline mt-1">Fix on Target Domains →</a>
                            @endif
                        </div>
                    </div>
                    @error('domain_ids')<p class="text-sm text-red-600 mb-2">{{ $message }}</p>@enderror

                    @if($domains->isEmpty())
                        <div class="p-6 text-center bg-slate-50 rounded-lg border border-slate-200">
                            <p class="text-slate-600">No active target domains found. <a href="{{ route('campaign-domains.index') }}" class="text-purple-600 hover:underline">Add target domains first</a>.</p>
                        </div>
                    @else
                        <div class="flex items-center gap-2 mb-4 border-b border-slate-200">
                            <button type="button"
                                class="px-3 py-2 text-sm font-medium border-b-2"
                                :class="domainMode === 'list' ? 'border-purple-600 text-purple-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
                                @click="domainMode = 'list'">
                                Select from list
                            </button>
                            <button type="button"
                                class="px-3 py-2 text-sm font-medium border-b-2"
                                :class="domainMode === 'bulk' ? 'border-purple-600 text-purple-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
                                @click="domainMode = 'bulk'">
                                Paste domains
                            </button>
                        </div>

                        <div x-show="domainMode === 'list'" x-cloak>
                            <div class="mb-4 flex items-center gap-3">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="select-all-cb" class="rounded border-slate-300 text-purple-600 focus:ring-purple-500">
                                    <span class="text-sm font-medium text-slate-700">Select All ({{ $domains->count() }})</span>
                                </label>
                            </div>
                        </div>

                        <div x-show="domainMode === 'bulk'" x-cloak>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Paste domain names (one per line)</label>
                                <textarea id="bulk-domain-input" rows="6" placeholder="example.com&#10;anotherdomain.com"
                                    class="w-full rounded-lg border-slate-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 text-sm font-mono"></textarea>
                                <p class="text-xs text-slate-500 mt-1">Each line must match an <strong>active</strong> target domain in your account. Invalid domains will be rejected.</p>
                                <p id="bulk-domain-error" class="hidden text-sm text-red-600 mt-2 p-3 bg-red-50 border border-red-200 rounded-lg"></p>
                                <p id="bulk-domain-success" class="hidden text-sm text-emerald-700 mt-2"></p>
                                <button type="button" id="bulk-domain-apply"
                                    class="mt-2 inline-flex items-center px-4 py-2 bg-purple-600 text-white text-sm rounded-lg hover:bg-purple-700">
                                    Apply pasted domains
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 max-h-96 overflow-y-auto border border-slate-200 rounded-lg p-3">
                            @foreach($domains as $domain)
                                <label class="flex items-start gap-3 p-3 rounded-lg border border-slate-200 hover:bg-slate-50 cursor-pointer transition-colors">
                                    <input type="checkbox" name="domain_ids[]" value="{{ $domain->id }}" data-domain="{{ $domain->domain }}"
                                        {{ in_array($domain->id, old('domain_ids', [])) ? 'checked' : '' }}
                                        class="mt-0.5 rounded border-slate-300 text-purple-600 focus:ring-purple-500 campaign-domain-cb">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-slate-800 truncate">{{ $domain->domain }}</p>
                                        <p class="text-xs text-slate-500 truncate">{{ Str::limit($domain->api_url, 30) }}</p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div x-show="step === 3" x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 transform translate-y-2"
                    x-transition:enter-end="opacity-100 transform translate-y-0" x-cloak>
                    <h3 class="text-lg font-semibold text-slate-800 mb-2">Links & Distribution</h3>
                    <p class="text-slate-500 text-sm mb-4">Add your links and specify how many links each domain should receive.</p>

                    <div class="mb-6 p-4 bg-purple-50 border border-purple-200 rounded-lg">
                        <h4 class="text-sm font-semibold text-purple-900 mb-2">How Distribution Works</h4>
                        <p class="text-sm text-purple-800">If you have 25 links and want each domain to get 5 links, the system will loop through your links to distribute them evenly. If there's a remainder, it will be distributed from the top.</p>
                        <p class="text-sm text-purple-800 mt-2"><strong>Example:</strong> 3 domains × 3 links each = 9 total. With 10 links provided, the first domain gets 4 links (3 + 1 remainder).</p>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Links Per Domain <span class="text-red-500">*</span></label>
                        <input type="number" name="links_per_domain" value="{{ old('links_per_domain', 5) }}" min="1" max="1000" required
                            class="w-full max-w-xs rounded-lg border-slate-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 {{ $errors->has('links_per_domain') ? 'border-red-500' : '' }}">
                        <p class="text-xs text-slate-500 mt-1">How many links each domain should receive</p>
                        @error('links_per_domain')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">URLs (one per line) <span class="text-red-500">*</span></label>
                            <textarea id="links_bulk" name="links_bulk" rows="8" placeholder="https://example.com/page1&#10;https://example.com/page2&#10;https://example.com/page3"
                                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono text-sm">{{ old('links_bulk') }}</textarea>
                            <p class="text-xs text-slate-500 mt-1">Paste your target URLs, one per line. <span id="links-count" class="font-medium">0 lines</span></p>
                            @error('links_bulk')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Keywords (one per line) <span class="text-red-500">*</span></label>
                            <textarea id="keywords_bulk" name="keywords_bulk" rows="8" placeholder="anchor text 1&#10;anchor text 2&#10;anchor text 3"
                                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono text-sm">{{ old('keywords_bulk') }}</textarea>
                            <p class="text-xs text-slate-500 mt-1">Paste your anchor texts, one per line. <span id="keywords-count" class="font-medium">0 lines</span></p>
                            @error('keywords_bulk')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div id="bulk-mismatch" class="hidden p-3 bg-amber-50 border border-amber-200 rounded-lg">
                            <p class="text-sm text-amber-800">⚠️ URLs and keywords must have the same number of lines.</p>
                        </div>
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
                            <button type="button" @click="if (!window.validateCampaignStep(step)) return; step++"
                                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                                Next
                            </button>
                        </template>
                        <template x-if="step === 3">
                            <button type="submit" id="submit-btn"
                                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                                Create & Distribute Links
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
            const form = document.getElementById('campaign-form');
            const linksBulk = document.getElementById('links_bulk');
            const keywordsBulk = document.getElementById('keywords_bulk');
            const linksCountEl = document.getElementById('links-count');
            const keywordsCountEl = document.getElementById('keywords-count');
            const mismatchEl = document.getElementById('bulk-mismatch');

            const selectAllCb = document.getElementById('select-all-cb');
            const domainCheckboxes = () => form ? [...form.querySelectorAll('input[name="domain_ids[]"]')] : [];
            if (selectAllCb) {
                selectAllCb.addEventListener('change', function() {
                    domainCheckboxes().forEach(cb => cb.checked = this.checked);
                });
                domainCheckboxes().forEach(cb => cb.addEventListener('change', function() {
                    const cbs = domainCheckboxes();
                    selectAllCb.checked = cbs.length > 0 && cbs.every(cb => cb.checked);
                    selectAllCb.indeterminate = cbs.some(cb => cb.checked) && !selectAllCb.checked;
                }));
            }

            const bulkDomainInput = document.getElementById('bulk-domain-input');
            const bulkDomainApply = document.getElementById('bulk-domain-apply');
            const bulkDomainError = document.getElementById('bulk-domain-error');
            const bulkDomainSuccess = document.getElementById('bulk-domain-success');

            function normalizeDomain(str) {
                if (!str) return '';
                let s = str.trim().toLowerCase();
                s = s.replace(/^https?:\/\//, '');
                s = s.replace(/^www\./, '');
                s = s.replace(/\/+$/, '');
                return s;
            }

            function buildDomainMap() {
                const map = {};
                domainCheckboxes().forEach(cb => {
                    const norm = normalizeDomain(cb.getAttribute('data-domain') || '');
                    if (norm) {
                        map[norm] = cb;
                    }
                });
                return map;
            }

            function showBulkError(message) {
                if (bulkDomainError) {
                    bulkDomainError.textContent = message;
                    bulkDomainError.classList.remove('hidden');
                }
                if (bulkDomainSuccess) {
                    bulkDomainSuccess.classList.add('hidden');
                }
            }

            function hideBulkError() {
                if (bulkDomainError) {
                    bulkDomainError.textContent = '';
                    bulkDomainError.classList.add('hidden');
                }
            }

            function applyBulkDomains(strict) {
                if (!bulkDomainInput) return true;

                const raw = (bulkDomainInput.value || '').trim();
                if (!raw) {
                    if (strict) {
                        showBulkError('Paste at least one domain name (one per line).');
                        return false;
                    }
                    hideBulkError();
                    return true;
                }

                const lines = raw.split(/\r?\n/).map(l => normalizeDomain(l)).filter(Boolean);
                if (lines.length === 0) {
                    showBulkError('Paste at least one valid domain name.');
                    return false;
                }

                const map = buildDomainMap();
                const missing = [];
                const seen = new Set();

                lines.forEach(line => {
                    if (!map[line] && !seen.has(line)) {
                        missing.push(line);
                    }
                    seen.add(line);
                });

                if (missing.length > 0) {
                    const preview = missing.slice(0, 8).join(', ');
                    const suffix = missing.length > 8 ? ' +' + (missing.length - 8) + ' more' : '';
                    showBulkError('These domains are not in your active target domains list: ' + preview + suffix);
                    return false;
                }

                domainCheckboxes().forEach(cb => { cb.checked = false; });
                const uniqueLines = [...new Set(lines)];
                uniqueLines.forEach(line => {
                    const cb = map[line];
                    if (cb) cb.checked = true;
                });

                if (selectAllCb) {
                    const cbs = domainCheckboxes();
                    selectAllCb.checked = cbs.length > 0 && cbs.every(cb => cb.checked);
                    selectAllCb.indeterminate = false;
                }

                hideBulkError();
                if (bulkDomainSuccess) {
                    bulkDomainSuccess.textContent = uniqueLines.length + ' domain(s) selected.';
                    bulkDomainSuccess.classList.remove('hidden');
                }
                return true;
            }

            if (bulkDomainApply) {
                bulkDomainApply.addEventListener('click', function() {
                    applyBulkDomains(true);
                });
            }

            if (bulkDomainInput) {
                bulkDomainInput.addEventListener('input', function() {
                    hideBulkError();
                    if (bulkDomainSuccess) bulkDomainSuccess.classList.add('hidden');
                });
            }

            window.validateCampaignStep = function(step) {
                if (step !== 2) return true;

                const bulkHasContent = bulkDomainInput && (bulkDomainInput.value || '').trim() !== '';
                if (bulkHasContent) {
                    if (!applyBulkDomains(true)) {
                        return false;
                    }
                }

                const checked = domainCheckboxes().filter(cb => cb.checked);
                if (checked.length === 0) {
                    alert('Select at least one target domain, or paste valid domain names and click Apply.');
                    return false;
                }
                return true;
            };

            window.goToCampaignStep = function(targetStep) {
                const root = document.querySelector('.page-enter');
                if (root && typeof Alpine !== 'undefined' && Alpine.$data) {
                    try { Alpine.$data(root).step = targetStep; } catch (_) {}
                }
            };

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
                if (!window.validateCampaignStep(2)) {
                    e.preventDefault();
                    window.goToCampaignStep(2);
                    return;
                }

                const linksFromBulk = parseLines(linksBulk);
                const keywordsFromBulk = parseLines(keywordsBulk);

                if (linksFromBulk.length > 0 || keywordsFromBulk.length > 0) {
                    if (linksFromBulk.length !== keywordsFromBulk.length) {
                        e.preventDefault();
                        alert('Links and keywords must have the same number of lines. You have ' + linksFromBulk.length + ' links and ' + keywordsFromBulk.length + ' keywords.');
                        return;
                    }
                }

                if (linksFromBulk.length === 0) {
                    e.preventDefault();
                    alert('Please add at least one link.');
                    return;
                }
            });

            updateCounts();
        })();
    </script>
@endsection
