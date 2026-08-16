@extends('layouts.dashboard')
@section('title', 'Hidden Links Management')
@section('page-title', 'Hidden Links Management')

@section('content')
<div class="page-enter">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-slate-800">Hidden Links Visibility</h2>
        <p class="text-slate-500 mt-1">Control the visibility of hidden links across all your target domains</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Current Status Card --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h3 class="text-lg font-semibold text-slate-800 mb-4">Current Status</h3>
                <div class="flex items-center justify-center py-8">
                    <div class="text-center">
                        @if($currentStatus)
                            <div class="w-20 h-20 mx-auto rounded-full bg-purple-100 flex items-center justify-center mb-4">
                                <svg class="w-10 h-10 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </div>
                            <p class="text-2xl font-bold text-purple-600 mb-2">Visible</p>
                            <p class="text-sm text-slate-500">Links are currently visible on frontend</p>
                        @else
                            <div class="w-20 h-20 mx-auto rounded-full bg-slate-100 flex items-center justify-center mb-4">
                                <svg class="w-10 h-10 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </div>
                            <p class="text-2xl font-bold text-slate-600 mb-2">Hidden</p>
                            <p class="text-sm text-slate-500">Links are currently hidden from frontend</p>
                        @endif
                    </div>
                </div>

                <div class="mt-6 p-4 bg-slate-50 rounded-lg">
                    <p class="text-xs text-slate-600">
                        <strong>Note:</strong> Changes will be applied to all {{ number_format($activeDomainCount ?? $domains->total()) }} active target domain(s) via queue jobs.
                    </p>
                </div>
            </div>
        </div>

        {{-- Action Cards --}}
        <div class="lg:col-span-2 space-y-4">
            {{-- Show Links Card --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-slate-800">Show Hidden Links</h3>
                                <p class="text-sm text-slate-500 mt-1">Make all hidden links visible on your target domains</p>
                            </div>
                        </div>
                        <ul class="space-y-2 mb-4">
                            <li class="flex items-center gap-2 text-sm text-slate-600">
                                <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Links will be visible to visitors
                            </li>
                            <li class="flex items-center gap-2 text-sm text-slate-600">
                                <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Applied to all {{ number_format($activeDomainCount ?? $domains->total()) }} active domains
                            </li>
                            <li class="flex items-center gap-2 text-sm text-slate-600">
                                <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Changes processed via queue
                            </li>
                        </ul>
                    </div>
                </div>
                <form method="POST" action="{{ route('hidden-links.toggle') }}" onsubmit="return confirm('Show hidden links on all target domains?');">
                    @csrf
                    <input type="hidden" name="show_hidden_links" value="1">
                    <button type="submit" class="w-full px-4 py-3 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 transition-colors {{ $currentStatus ? 'opacity-50 cursor-not-allowed' : '' }}" {{ $currentStatus ? 'disabled' : '' }}>
                        {{ $currentStatus ? 'Already Visible' : 'Show Links on All Domains' }}
                    </button>
                </form>
            </div>

            {{-- Hide Links Card --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center">
                                <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-slate-800">Hide Links</h3>
                                <p class="text-sm text-slate-500 mt-1">Hide all links from visitors on your target domains</p>
                            </div>
                        </div>
                        <ul class="space-y-2 mb-4">
                            <li class="flex items-center gap-2 text-sm text-slate-600">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Links will be hidden from visitors
                            </li>
                            <li class="flex items-center gap-2 text-sm text-slate-600">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Applied to all {{ number_format($activeDomainCount ?? $domains->total()) }} active domains
                            </li>
                            <li class="flex items-center gap-2 text-sm text-slate-600">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Changes processed via queue
                            </li>
                        </ul>
                    </div>
                </div>
                <form method="POST" action="{{ route('hidden-links.toggle') }}" onsubmit="return confirm('Hide links on all target domains?');">
                    @csrf
                    <input type="hidden" name="show_hidden_links" value="0">
                    <button type="submit" class="w-full px-4 py-3 bg-slate-600 text-white rounded-lg font-medium hover:bg-slate-700 transition-colors {{ !$currentStatus ? 'opacity-50 cursor-not-allowed' : '' }}" {{ !$currentStatus ? 'disabled' : '' }}>
                        {{ !$currentStatus ? 'Already Hidden' : 'Hide Links on All Domains' }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Target Domains List --}}
    <div class="mt-8">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-800">Target Domains ({{ number_format($domains->total()) }})</h3>
                <p class="text-sm text-slate-500 mt-1">These domains will receive the visibility toggle request</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Domain</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">API URL</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Last Checked</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($domains as $domain)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-slate-800">{{ $domain->domain }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ Str::limit($domain->api_url, 40) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2.5 py-0.5 text-xs font-medium rounded-full bg-emerald-100 text-emerald-700">Active</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-500">{{ $domain->last_checked_at ? $domain->last_checked_at->diffForHumans() : 'Never' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-slate-500">
                                    <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <p class="mt-2">No active target domains found.</p>
                                    <a href="{{ route('campaign-domains.index') }}" class="inline-block mt-2 text-purple-600 hover:underline">Add target domains</a>
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
    </div>
</div>
@endsection
