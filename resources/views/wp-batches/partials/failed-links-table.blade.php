@if(!empty($failedLinksTruncated))
    <p class="text-sm text-slate-600 mb-4">Showing the first {{ number_format(count($failedLinks ?? [])) }} failures. Use per-site <strong>View</strong> or export for full details.</p>
@endif
<table class="min-w-full divide-y divide-slate-200 text-sm">
    <thead>
        <tr>
            <th class="text-left py-2 font-medium text-slate-600">Site</th>
            <th class="text-left py-2 font-medium text-slate-600">URL / Keyword</th>
            <th class="text-left py-2 font-medium text-slate-600">Error</th>
            <th class="text-right py-2 font-medium text-slate-600">Actions</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
        @forelse($failedLinks ?? [] as $item)
            <tr>
                <td class="py-2 text-slate-700">{{ $item->wpSite->domain ?? 'N/A' }}</td>
                <td class="py-2 text-slate-600">{{ Str::limit($item->url ?? '-', 40) }} → {{ Str::limit($item->keyword ?? '-', 20) }}</td>
                <td class="py-2 text-red-600">{{ $item->error_message ?? '-' }}</td>
                <td class="py-2 text-right">
                    @if(Route::has('wp-batches.replace-failed-link') && ! in_array($item->chunk_status ?? '', ['pending', 'processing'], true) && ! empty($item->chunk_id))
                    <button type="button" title="Replace URL/keyword, then Retry failed"
                        data-chunk-id="{{ $item->chunk_id }}"
                        data-link-index="{{ $item->link_index }}"
                        data-url="{{ $item->url }}"
                        data-keyword="{{ $item->keyword }}"
                        onclick="openReplaceFailedModal(this)"
                        class="px-2 py-1 text-xs font-medium rounded-lg border border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100 transition-colors">
                        Replace
                    </button>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="py-6 text-center text-slate-500">No failed links to show.</td>
            </tr>
        @endforelse
    </tbody>
</table>
