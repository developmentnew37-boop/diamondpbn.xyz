@extends('layouts.dashboard')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="page-enter">
    <div class="mb-8">
        <h2 class="text-2xl md:text-3xl font-bold text-slate-800 tracking-tight">Overview</h2>
        <p class="text-slate-600 mt-1 text-sm md:text-base">Manage your PBN sites and link batches at a glance</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
        <div class="card-hover animate-in animate-in-1 bg-white/90 backdrop-blur rounded-2xl border border-orange-100 p-6 shadow-md hover:border-orange-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 uppercase tracking-wide">Total Domains</p>
                    <p class="text-3xl font-bold text-slate-800 mt-2">{{ $stats['domains'] ?? 0 }}</p>
                </div>
                <div class="card-icon w-14 h-14 rounded-2xl bg-orange-100 flex items-center justify-center shadow-inner">
                    <svg class="w-7 h-7 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                    </svg>
                </div>
            </div>
        </div>
        <div class="card-hover animate-in animate-in-2 bg-white/90 backdrop-blur rounded-2xl border border-orange-100 p-6 shadow-md hover:border-orange-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 uppercase tracking-wide">Total Batches</p>
                    <p class="text-3xl font-bold text-slate-800 mt-2">{{ $stats['batches'] ?? 0 }}</p>
                </div>
                <div class="card-icon w-14 h-14 rounded-2xl bg-amber-100 flex items-center justify-center shadow-inner">
                    <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
            </div>
        </div>
        <div class="card-hover animate-in animate-in-3 bg-white/90 backdrop-blur rounded-2xl border border-orange-100 p-6 shadow-md hover:border-orange-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 uppercase tracking-wide">Links Posted</p>
                    <p class="text-3xl font-bold text-slate-800 mt-2">{{ $stats['links_posted'] ?? 0 }}</p>
                </div>
                <div class="card-icon w-14 h-14 rounded-2xl bg-orange-200/80 flex items-center justify-center shadow-inner">
                    <svg class="w-7 h-7 text-orange-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                </div>
            </div>
        </div>
        <div class="card-hover animate-in animate-in-4 bg-white/90 backdrop-blur rounded-2xl border border-orange-100 p-6 shadow-md hover:border-orange-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 uppercase tracking-wide">Active Batches</p>
                    <p class="text-3xl font-bold text-slate-800 mt-2">{{ $stats['active_batches'] ?? 0 }}</p>
                </div>
                <div class="card-icon w-14 h-14 rounded-2xl bg-rose-100 flex items-center justify-center shadow-inner">
                    <svg class="w-7 h-7 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-8 mb-6">
        <h3 class="text-xl font-bold text-slate-800">Campaign Statistics</h3>
        <p class="text-slate-600 mt-1 text-sm">Track your campaign performance</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
        <div class="card-hover animate-in animate-in-5 bg-white/90 backdrop-blur rounded-2xl border border-purple-100 p-6 shadow-md hover:border-purple-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 uppercase tracking-wide">Total Campaigns</p>
                    <p class="text-3xl font-bold text-slate-800 mt-2">{{ $stats['campaigns'] ?? 0 }}</p>
                </div>
                <div class="card-icon w-14 h-14 rounded-2xl bg-purple-100 flex items-center justify-center shadow-inner">
                    <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                </div>
            </div>
        </div>
        <div class="card-hover animate-in animate-in-6 bg-white/90 backdrop-blur rounded-2xl border border-purple-100 p-6 shadow-md hover:border-purple-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 uppercase tracking-wide">Target Domains</p>
                    <p class="text-3xl font-bold text-slate-800 mt-2">{{ $stats['campaign_domains'] ?? 0 }}</p>
                </div>
                <div class="card-icon w-14 h-14 rounded-2xl bg-indigo-100 flex items-center justify-center shadow-inner">
                    <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>
        <div class="card-hover animate-in animate-in-7 bg-white/90 backdrop-blur rounded-2xl border border-purple-100 p-6 shadow-md hover:border-purple-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 uppercase tracking-wide">Campaign Links</p>
                    <p class="text-3xl font-bold text-slate-800 mt-2">{{ $stats['campaign_links_posted'] ?? 0 }}</p>
                </div>
                <div class="card-icon w-14 h-14 rounded-2xl bg-purple-200/80 flex items-center justify-center shadow-inner">
                    <svg class="w-7 h-7 text-purple-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                </div>
            </div>
        </div>
        <div class="card-hover animate-in animate-in-8 bg-white/90 backdrop-blur rounded-2xl border border-purple-100 p-6 shadow-md hover:border-purple-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 uppercase tracking-wide">Active Campaigns</p>
                    <p class="text-3xl font-bold text-slate-800 mt-2">{{ $stats['active_campaigns'] ?? 0 }}</p>
                </div>
                <div class="card-icon w-14 h-14 rounded-2xl bg-violet-100 flex items-center justify-center shadow-inner">
                    <svg class="w-7 h-7 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">
        <div class="animate-in animate-in-9 bg-white/90 backdrop-blur rounded-2xl border border-orange-100 p-6 shadow-md">
            <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2">
                <span class="w-1 h-6 rounded-full bg-orange-500"></span>
                Recent Batches
            </h3>
            <div class="space-y-3">
                @forelse($recentBatches ?? [] as $batch)
                    <a href="{{ route('batches.show', $batch) }}" class="block p-4 rounded-xl border border-orange-50 hover:border-orange-200 hover:bg-orange-50/70 transition-all duration-300 group">
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-slate-800 group-hover:text-orange-700 transition-colors">{{ $batch->name }}</span>
                            <span class="px-3 py-1 text-xs font-medium rounded-full {{ $batch->status === 'completed' ? 'bg-orange-100 text-orange-700' : ($batch->status === 'processing' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600') }}">{{ ucfirst($batch->status) }}</span>
                        </div>
                        @php
                            $batchTotalPosts = ($batch->total_links ?? 0) * ($batch->total_domains ?? 0);
                        @endphp
                        <p class="text-sm text-slate-500 mt-1">{{ $batch->success_count ?? 0 }} / {{ $batchTotalPosts }} links posted</p>
                    </a>
                @empty
                    <div class="text-center py-10 text-slate-500 rounded-xl bg-orange-50/50 border border-orange-100">
                        <p>No batches yet.</p>
                        <a href="{{ route('batches.create') }}" class="inline-block mt-2 text-orange-600 font-medium hover:text-orange-700 hover:underline">Create your first batch</a>
                    </div>
                @endforelse
            </div>
        </div>
        <div class="animate-in animate-in-10 bg-white/90 backdrop-blur rounded-2xl border border-purple-100 p-6 shadow-md">
            <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2">
                <span class="w-1 h-6 rounded-full bg-purple-500"></span>
                Recent Campaigns
            </h3>
            <div class="space-y-3">
                @forelse($recentCampaigns ?? [] as $campaign)
                    <a href="{{ route('campaigns.show', $campaign) }}" class="block p-4 rounded-xl border border-purple-50 hover:border-purple-200 hover:bg-purple-50/70 transition-all duration-300 group">
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-slate-800 group-hover:text-purple-700 transition-colors">{{ $campaign->name }}</span>
                            <span class="px-3 py-1 text-xs font-medium rounded-full {{ $campaign->status === 'completed' ? 'bg-emerald-100 text-emerald-700' : ($campaign->status === 'processing' ? 'bg-amber-100 text-amber-700' : ($campaign->status === 'partial' ? 'bg-orange-100 text-orange-700' : 'bg-slate-100 text-slate-600')) }}">{{ ucfirst($campaign->status) }}</span>
                        </div>
                        <p class="text-sm text-slate-500 mt-1">{{ $campaign->success_count ?? 0 }} / {{ $campaign->total_distributed_links ?? 0 }} links distributed</p>
                    </a>
                @empty
                    <div class="text-center py-10 text-slate-500 rounded-xl bg-purple-50/50 border border-purple-100">
                        <p>No campaigns yet.</p>
                        <a href="{{ route('campaigns.create') }}" class="inline-block mt-2 text-purple-600 font-medium hover:text-purple-700 hover:underline">Create your first campaign</a>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        <div class="animate-in animate-in-11 bg-white/90 backdrop-blur rounded-2xl border border-orange-100 p-6 shadow-md">
            <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2">
                <span class="w-1 h-6 rounded-full bg-orange-500"></span>
                Batch Quick Actions
            </h3>
            <div class="space-y-3">
                <a href="{{ route('batches.create') }}" class="flex items-center gap-4 p-4 rounded-xl border border-orange-100 hover:border-orange-200 hover:bg-orange-50/70 transition-all duration-200 group">
                    <div class="w-12 h-12 rounded-xl bg-orange-100 flex items-center justify-center group-hover:bg-orange-200 group-hover:scale-105 transition-all duration-200">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="font-medium text-slate-800 group-hover:text-orange-700">Create New Batch</p>
                        <p class="text-sm text-slate-500">Post links across your PBN sites</p>
                    </div>
                    <svg class="w-5 h-5 text-orange-400 group-hover:text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
                <a href="{{ route('domains.index') }}" class="flex items-center gap-4 p-4 rounded-xl border border-orange-100 hover:border-orange-200 hover:bg-orange-50/70 transition-all duration-200 group">
                    <div class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center group-hover:bg-amber-200 group-hover:scale-105 transition-all duration-200">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="font-medium text-slate-800 group-hover:text-orange-700">Manage Domains</p>
                        <p class="text-sm text-slate-500">Add or bulk import via Excel (.xlsx)</p>
                    </div>
                    <svg class="w-5 h-5 text-orange-400 group-hover:text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>
        <div class="animate-in animate-in-12 bg-white/90 backdrop-blur rounded-2xl border border-purple-100 p-6 shadow-md">
            <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2">
                <span class="w-1 h-6 rounded-full bg-purple-500"></span>
                Campaign Quick Actions
            </h3>
            <div class="space-y-3">
                <a href="{{ route('campaigns.create') }}" class="flex items-center gap-4 p-4 rounded-xl border border-purple-100 hover:border-purple-200 hover:bg-purple-50/70 transition-all duration-200 group">
                    <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center group-hover:bg-purple-200 group-hover:scale-105 transition-all duration-200">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="font-medium text-slate-800 group-hover:text-purple-700">Create New Campaign</p>
                        <p class="text-sm text-slate-500">Distribute links to target domains</p>
                    </div>
                    <svg class="w-5 h-5 text-purple-400 group-hover:text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
                <a href="{{ route('campaign-domains.index') }}" class="flex items-center gap-4 p-4 rounded-xl border border-purple-100 hover:border-purple-200 hover:bg-purple-50/70 transition-all duration-200 group">
                    <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center group-hover:bg-indigo-200 group-hover:scale-105 transition-all duration-200">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="font-medium text-slate-800 group-hover:text-purple-700">Manage Target Domains</p>
                        <p class="text-sm text-slate-500">Add or import campaign domains</p>
                    </div>
                    <svg class="w-5 h-5 text-purple-400 group-hover:text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
