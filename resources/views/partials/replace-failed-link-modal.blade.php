@props(['action'])

<div id="replace-failed-modal" class="hidden fixed inset-0 z-[60] overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/60" onclick="document.getElementById('replace-failed-modal').classList.add('hidden')"></div>
        <div class="relative bg-white rounded-xl shadow-lg max-w-lg w-full">
            <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-slate-800">Replace failed link</h3>
                <button type="button" onclick="document.getElementById('replace-failed-modal').classList.add('hidden')" class="text-slate-500 hover:text-slate-700">✕</button>
            </div>
            <form method="POST" action="{{ $action }}" class="p-6 space-y-4">
                @csrf
                <input type="hidden" name="chunk_id" id="replace-chunk-id" value="{{ old('chunk_id') }}">
                <input type="hidden" name="link_index" id="replace-link-index" value="{{ old('link_index') }}">
                @if($errors->hasAny(['url', 'keyword', 'chunk_id', 'link_index']))
                    <div class="text-sm text-red-600">{{ $errors->first() }}</div>
                @endif
                <div>
                    <label for="replace-url" class="block text-sm font-medium text-slate-700 mb-1">New URL</label>
                    <input type="text" name="url" id="replace-url" required maxlength="2048" value="{{ old('url') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:ring-sky-500" placeholder="https://">
                </div>
                <div>
                    <label for="replace-keyword" class="block text-sm font-medium text-slate-700 mb-1">Keyword</label>
                    <input type="text" name="keyword" id="replace-keyword" required value="{{ old('keyword') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:ring-sky-500">
                </div>
                <p class="text-xs text-slate-500">Saves the new URL/keyword on this failed slot only. Then use <strong>Retry failed</strong> and <strong>Publish pending</strong> to post it.</p>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('replace-failed-modal').classList.add('hidden')" class="px-3 py-2 text-sm font-medium rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-3 py-2 text-sm font-medium rounded-lg bg-amber-600 text-white hover:bg-amber-700">Save replacement</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function openReplaceFailedModal(btn) {
    document.getElementById('replace-chunk-id').value = btn.getAttribute('data-chunk-id') || '';
    document.getElementById('replace-link-index').value = btn.getAttribute('data-link-index') || '';
    document.getElementById('replace-url').value = btn.getAttribute('data-url') || '';
    document.getElementById('replace-keyword').value = btn.getAttribute('data-keyword') || '';
    document.getElementById('replace-failed-modal').classList.remove('hidden');
}
@if($errors->hasAny(['url', 'keyword', 'chunk_id', 'link_index']))
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('replace-failed-modal')?.classList.remove('hidden');
});
@endif
</script>
