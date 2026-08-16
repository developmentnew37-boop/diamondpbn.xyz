@extends('layouts.dashboard')
@section('title', $wpSite->domain . ' - ' . ($wpBatch->name ?? 'WP Batch'))
@section('page-title', $wpSite->domain)

@section('content')
<div class="page-enter">
    <div class="mb-6">
        <a href="{{ route('wp-batches.show', $wpBatch) }}" class="text-sm text-slate-500 hover:text-sky-600 mb-2 inline-block">← Back to WP Batch</a>
        <div class="flex flex-wrap items-center gap-4">
            <h2 class="text-2xl font-bold text-slate-800">{{ $wpSite->domain }}</h2>
            <span class="text-slate-500">in batch "{{ $wpBatch->name }}"</span>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <p class="text-sm text-slate-500">Chunks</p>
            <p class="text-xl font-bold text-slate-800 mt-1">{{ count($chunks) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <p class="text-sm text-slate-500">Total Links</p>
            <p class="text-xl font-bold text-slate-800 mt-1">{{ count($linksWithStatus ?? []) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <p class="text-sm text-slate-500">Success</p>
            <p class="text-xl font-bold text-emerald-600 mt-1">{{ collect($linksWithStatus ?? [])->filter(fn($l) => in_array($l['status'] ?? '', ['success','completed','posted']))->count() }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <p class="text-sm text-slate-500">Failed</p>
            <p class="text-xl font-bold text-red-600 mt-1">{{ collect($linksWithStatus ?? [])->filter(fn($l) => ($l['status'] ?? '') === 'failed')->count() }}</p>
        </div>
    </div>

    @if(count($chunks) > 0)
    <div class="mb-6 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200">
            <h3 class="text-lg font-semibold text-slate-800">Chunks</h3>
            <p class="text-sm text-slate-500 mt-0.5">Data sent to remote site per chunk</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Chunk</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Links</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Success / Failed</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach($chunks ?? [] as $chunk)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 font-medium text-slate-800">Chunk {{ $chunk->chunk_index }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ count($chunk->links_payload ?? []) }}</td>
                            <td class="px-6 py-4">
                                @php
                                    $statusClass = match($chunk->status ?? '') {
                                        'completed' => 'bg-emerald-100 text-emerald-700',
                                        'partial' => 'bg-sky-100 text-sky-700',
                                        'processing' => 'bg-amber-100 text-amber-700',
                                        default => 'bg-slate-100 text-slate-600',
                                    };
                                @endphp
                                <span class="px-2.5 py-0.5 text-xs font-medium rounded-full {{ $statusClass }}">{{ ucfirst($chunk->status ?? 'pending') }}</span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="text-emerald-600">{{ $chunk->success_count ?? 0 }}</span> / <span class="text-red-600">{{ $chunk->failed_count ?? 0 }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200">
            <h3 class="text-lg font-semibold text-slate-800">All Links ({{ count($linksWithStatus ?? []) }})</h3>
            <p class="text-sm text-slate-500 mt-0.5">URL, keyword, status per link for this site</p>
        </div>
        <div class="overflow-x-auto max-h-[28rem] overflow-y-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50 sticky top-0">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-12">#</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">URL</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Keyword</th>
                        @if(count($chunks) > 0)
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-24">Chunk</th>
                        @endif
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-24">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Remote ID / Error</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider w-24">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach($linksWithStatus ?? [] as $i => $link)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-3 text-sm text-slate-500">{{ $i + 1 }}</td>
                            <td class="px-6 py-3 text-sm text-slate-800 break-all max-w-xs">{{ $link['url'] }}</td>
                            <td class="px-6 py-3 text-sm text-slate-700">{{ $link['keyword'] }}</td>
                            @if(count($chunks) > 0)
                            <td class="px-6 py-3 text-sm text-slate-600">{{ $link['chunk_index'] ?? '—' }}</td>
                            @endif
                            <td class="px-6 py-3">
                                @php
                                    $statusClass = in_array($link['status'] ?? '', ['success','completed','posted']) ? 'text-emerald-600' : (($link['status'] ?? '') === 'failed' ? 'text-red-600' : 'text-slate-500');
                                @endphp
                                <span class="{{ $statusClass }} font-medium">{{ ucfirst($link['status'] ?? 'pending') }}</span>
                            </td>
                            <td class="px-6 py-3 text-sm">
                                @if($link['remote_post_id'] ?? null)
                                    <span class="text-slate-600 font-mono text-xs">{{ Str::limit($link['remote_post_id'], 20) }}</span>
                                @elseif($link['error'] ?? null)
                                    <span class="text-red-600" title="{{ $link['error'] }}">{{ Str::limit($link['error'], 40) }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-right">
                                @if(($batchLinks ?? [])[$i] ?? null)
                                <form method="POST" action="{{ route('wp-batches.links.destroy', [$wpBatch, ($batchLinks[$i])]) }}" class="inline" onsubmit="return confirm('Delete this link from the batch and remove it from all remote sites?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Delete" class="p-2 rounded-lg text-slate-500 hover:bg-red-50 hover:text-red-600 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
