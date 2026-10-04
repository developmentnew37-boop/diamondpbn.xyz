@extends('layouts.dashboard')
@section('title', 'WP Site Categories')
@section('page-title', 'WP Site Categories')

@section('content')
@php
    $categories = $categories ?? collect();
    $uncategorizedCount = $uncategorizedCount ?? 0;
@endphp
<div class="page-enter">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">WP Site Categories</h2>
            <p class="text-slate-500 mt-1">Shared list for every account. Deleting a category unassigns sites; it does not delete them.</p>
        </div>
        <a href="{{ route('wp-sites.index') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 transition-colors">
            Back to WP Sites
        </a>
    </div>

    <div class="flex flex-col sm:flex-row gap-4 mb-6">
        <div class="flex-1 min-w-0 bg-white rounded-xl border border-sky-200 p-4 shadow-sm">
            <p class="text-sm text-slate-500">Categories</p>
            <p class="text-2xl font-bold text-sky-600 mt-1">{{ number_format($categories->count()) }}</p>
            <p class="text-xs text-slate-500 mt-1">Shared across all accounts</p>
        </div>
        <a href="{{ route('wp-sites.index', ['category' => 'uncategorized']) }}" class="flex-1 min-w-0 bg-white rounded-xl border border-slate-200 p-4 shadow-sm hover:border-slate-300 transition-colors">
            <p class="text-sm text-slate-500">Uncategorized sites</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ number_format($uncategorizedCount) }}</p>
            <p class="text-xs text-sky-600 mt-1">View on WP Sites</p>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-4 sm:px-6 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-slate-800">Categories</h3>
                    <p class="text-sm text-slate-500">Used on WP sites and WP batch create</p>
                </div>
                <form method="POST" action="{{ route('wp-sites.categories.store') }}" class="flex items-center gap-2">
                    @csrf
                    <input type="text" name="name" maxlength="80" required placeholder="New category" value="{{ old('name') }}" class="w-40 sm:w-48 rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                    <button type="submit" class="inline-flex items-center justify-center px-3 py-2 bg-sky-600 text-white text-sm font-medium rounded-lg hover:bg-sky-700 whitespace-nowrap">Add</button>
                </form>
            </div>
            @if($errors->has('name') || $errors->has('name_normalized'))
                <div class="px-4 sm:px-6 py-2 bg-red-50 border-b border-red-100">
                    @error('name')<p class="text-red-600 text-xs">{{ $message }}</p>@enderror
                    @error('name_normalized')<p class="text-red-600 text-xs">{{ $message }}</p>@enderror
                </div>
            @endif
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Sites</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($categories as $category)
                            <tr class="hover:bg-slate-50/50" x-data="{ editing: false }">
                                <td class="px-6 py-4">
                                    <span x-show="!editing" class="font-medium text-slate-800">{{ $category->name }}</span>
                                    <form method="POST" action="{{ route('wp-sites.categories.update', $category) }}" x-show="editing" x-cloak class="flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <input type="text" name="name" maxlength="80" required value="{{ $category->name }}" class="w-48 rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                                        <button type="submit" class="px-2.5 py-1.5 text-xs font-medium rounded-lg bg-sky-600 text-white hover:bg-sky-700">Save</button>
                                        <button type="button" @click="editing = false" class="px-2.5 py-1.5 text-xs font-medium rounded-lg text-slate-600 hover:bg-slate-100">Cancel</button>
                                    </form>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="{{ route('wp-sites.index', ['category' => $category->id]) }}" class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded-full bg-violet-100 text-violet-700 hover:bg-violet-200">
                                        {{ number_format($category->wp_sites_count) }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="inline-flex items-center justify-end gap-1">
                                        <button type="button" @click="editing = true" title="Rename" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-sky-600 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <form method="POST" action="{{ route('wp-sites.categories.destroy', $category) }}" class="inline" onsubmit="return confirm('Delete this category? Sites in it will become uncategorized.');">
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
                                <td colspan="3" class="px-6 py-12 text-center text-slate-500">
                                    No categories yet. Add a category above to group WP sites.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 lg:sticky lg:top-4">
            <h3 class="text-base font-semibold text-slate-800">Assign by paste</h3>
            <p class="text-sm text-slate-500 mt-1 mb-4">One domain per line. Unknown domains are skipped.</p>
            <form method="POST" action="{{ route('wp-sites.bulk-category-paste') }}" class="space-y-3">
                @csrf
                <div>
                    <label for="paste-category" class="block text-xs font-medium text-slate-500 mb-1">Category</label>
                    <select id="paste-category" name="wp_site_category_id" class="w-full rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Clear category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="paste-domains" class="block text-xs font-medium text-slate-500 mb-1">Domains</label>
                    <textarea id="paste-domains" name="domains" rows="6" required placeholder="example.com&#10;anotherdomain.com" class="w-full rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500 font-mono"></textarea>
                </div>
                <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-sky-600 text-white text-sm font-medium rounded-lg hover:bg-sky-700">
                    Apply to matching sites
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
