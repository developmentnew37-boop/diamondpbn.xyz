@extends('layouts.dashboard')
@section('title', 'WP Batches')
@section('page-title', 'WP Batches')

@section('content')
<div class="page-enter">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">WP Batches</h2>
            <p class="text-slate-500 mt-1">
                Named groups of link-posting operations to WordPress sites
                @if(($wpBatches->total() ?? 0) > 0)
                    · {{ number_format($wpBatches->total()) }} total
                @endif
            </p>
        </div>
        <div class="flex items-center gap-3">
            <form method="GET" action="{{ route('wp-batches.index') }}" class="flex items-center gap-2">
                <input type="text" name="search" value="{{ old('search', $search ?? '') }}" placeholder="Search by batch name or link (URL/keyword)..." class="w-48 sm:w-72 rounded-lg border-slate-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 text-sm">
                <button type="submit" class="px-3 py-2 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 text-sm font-medium">Search</button>
                @if(!empty($search ?? ''))
                    <a href="{{ route('wp-batches.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Clear</a>
                @endif
            </form>
            <a href="{{ route('wp-batches.create') }}" class="inline-flex items-center px-4 py-2 bg-sky-600 text-white rounded-lg text-sm font-medium hover:bg-sky-700 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            Create WP Batch
            </a>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Links</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Sites</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Progress</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Created</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($wpBatches ?? [] as $wpBatch)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <a href="{{ route('wp-batches.show', $wpBatch) }}" class="font-medium text-sky-600 hover:text-sky-700">{{ $wpBatch->name }}</a>
                                @if($wpBatch->description)
                                    <p class="text-xs text-slate-500 mt-0.5">{{ Str::limit($wpBatch->description, 40) }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $statusClasses = [
                                        'pending' => 'bg-slate-100 text-slate-600',
                                        'processing' => 'bg-amber-100 text-amber-700',
                                        'deleting' => 'bg-sky-100 text-sky-700',
                                        'completed' => 'bg-emerald-100 text-emerald-700',
                                        'failed' => 'bg-red-100 text-red-700',
                                        'partial' => 'bg-sky-100 text-sky-700',
                                        'delete_failed' => 'bg-red-100 text-red-700',
                                        'semi_deleted' => 'bg-violet-100 text-violet-700',
                                    ];
                                @endphp
                                <span class="px-2.5 py-0.5 text-xs font-medium rounded-full {{ $statusClasses[$wpBatch->status] ?? 'bg-slate-100 text-slate-600' }}">{{ ucwords(str_replace('_', ' ', $wpBatch->status)) }}</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $wpBatch->total_links ?? 0 }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $wpBatch->total_domains ?? 0 }}</td>
                            <td class="px-6 py-4">
                                @php
                                    $progress = $wpBatch->displayProgress($wpBatch->chunks_success ?? null, $wpBatch->chunks_failed ?? null);
                                    $progressBarColor = match ($wpBatch->status) {
                                        'completed' => 'bg-emerald-500',
                                        'failed', 'delete_failed' => 'bg-red-500',
                                        'deleting', 'semi_deleted' => 'bg-slate-500',
                                        default => 'bg-sky-600',
                                    };
                                @endphp
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden max-w-[100px]">
                                        <div class="h-full {{ $progressBarColor }} rounded-full transition-all duration-500" style="width: {{ $progress['percent'] }}%"></div>
                                    </div>
                                    <span class="text-xs text-slate-500">{{ number_format($progress['processed']) }}/{{ number_format($progress['total']) }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500">{{ $wpBatch->created_at?->format('M d, Y') ?? '-' }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('wp-batches.show', $wpBatch) }}" title="View" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-sky-600 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                <form method="POST" action="{{ route('wp-batches.destroy', $wpBatch) }}" class="inline" onsubmit="return confirm('Delete this WP batch? All links and posting history will be removed.');">
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
                            <td colspan="7" class="px-6 py-12 text-center text-slate-500">
                                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                <p class="mt-2">No WP batches yet. <a href="{{ route('wp-batches.create') }}" class="text-sky-600 hover:underline">Create your first WP batch</a></p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($wpBatches->hasPages())
            <div class="px-6 py-4 border-t border-slate-200">
                {{ $wpBatches->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
