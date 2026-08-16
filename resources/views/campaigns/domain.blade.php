@extends('layouts.dashboard')
@section('title', 'Target Domain Detail')
@section('page-title', 'Target Domain Detail')

@section('content')
<div class="page-enter">
    <div class="mb-6">
        <a href="{{ route('campaigns.show', $campaign) }}" class="text-sm text-slate-500 hover:text-purple-600 mb-2 inline-block">← Back to Campaign</a>
        <div class="flex flex-wrap items-center gap-4">
            <h2 class="text-2xl font-bold text-slate-800">{{ $campaign->name ?? 'Campaign' }}</h2>
            <span class="text-slate-400">→</span>
            <h3 class="text-xl font-semibold text-purple-600">{{ $campaignDomain->domain ?? 'Domain' }}</h3>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-4 sm:px-6 py-4 border-b border-slate-200">
            <h3 class="text-lg font-semibold text-slate-800">Distributed Links for this Domain</h3>
            <p class="text-sm text-slate-500 mt-0.5">All links posted to {{ $campaignDomain->domain }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-12">#</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">URL</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Keyword</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Remote ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Error</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($linksWithStatus ?? [] as $i => $item)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-3 text-sm text-slate-500">{{ $i + 1 }}</td>
                            <td class="px-6 py-3 text-sm text-slate-800 break-all max-w-xs">{{ $item['url'] ?? '-' }}</td>
                            <td class="px-6 py-3 text-sm text-slate-700">{{ $item['keyword'] ?? '-' }}</td>
                            <td class="px-6 py-3 whitespace-nowrap">
                                @php
                                    $status = $item['status'] ?? 'pending';
                                    $statusClasses = [
                                        'success' => 'bg-emerald-100 text-emerald-700',
                                        'completed' => 'bg-emerald-100 text-emerald-700',
                                        'posted' => 'bg-emerald-100 text-emerald-700',
                                        'failed' => 'bg-red-100 text-red-700',
                                        'pending' => 'bg-slate-100 text-slate-600',
                                        'processing' => 'bg-amber-100 text-amber-700',
                                    ];
                                @endphp
                                <span class="px-2.5 py-0.5 text-xs font-medium rounded-full {{ $statusClasses[$status] ?? 'bg-slate-100 text-slate-600' }}">{{ ucfirst($status) }}</span>
                            </td>
                            <td class="px-6 py-3 text-sm text-slate-600">{{ $item['remote_post_id'] ?? '-' }}</td>
                            <td class="px-6 py-3 text-sm text-red-600 max-w-xs truncate" title="{{ $item['error'] ?? '' }}">{{ $item['error'] ? Str::limit($item['error'], 50) : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                No links found for this domain.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
