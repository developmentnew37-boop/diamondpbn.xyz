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

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <p class="text-sm text-slate-500">Categories</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ number_format($categories->count()) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <p class="text-sm text-slate-500">Uncategorized sites</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ number_format($uncategorizedCount) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-1">Categories</h3>
            <p class="text-sm text-slate-500 mb-4">Create, rename, or delete categories used on WP sites and WP batch create.</p>
            <form method="POST" action="{{ route('wp-sites.categories.store') }}" class="flex flex-col sm:flex-row gap-2 mb-4">
                @csrf
                <input type="text" name="name" maxlength="80" required placeholder="New category name" value="{{ old('name') }}" class="flex-1 rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                <button type="submit" class="px-4 py-2 bg-sky-600 text-white text-sm rounded-lg hover:bg-sky-700">Add</button>
            </form>
            @error('name')<p class="text-red-500 text-xs mb-3">{{ $message }}</p>@enderror
            @error('name_normalized')<p class="text-red-500 text-xs mb-3">{{ $message }}</p>@enderror

            <div class="divide-y divide-slate-100 border border-slate-200 rounded-lg overflow-hidden">
                @forelse($categories as $category)
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 p-3">
                        <form method="POST" action="{{ route('wp-sites.categories.update', $category) }}" class="flex-1 flex gap-2">
                            @csrf
                            @method('PATCH')
                            <input type="text" name="name" maxlength="80" required value="{{ $category->name }}" class="flex-1 rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                            <button type="submit" class="px-3 py-1.5 text-sm font-medium rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200">Rename</button>
                        </form>
                        <span class="text-xs text-slate-500 sm:w-20 sm:text-right">{{ number_format($category->wp_sites_count) }} sites</span>
                        <form method="POST" action="{{ route('wp-sites.categories.destroy', $category) }}" onsubmit="return confirm('Delete this category? Sites in it will become uncategorized.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-3 py-1.5 text-sm font-medium rounded-lg bg-red-50 text-red-700 hover:bg-red-100">Delete</button>
                        </form>
                    </div>
                @empty
                    <p class="p-4 text-sm text-slate-500">No categories yet.</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-1">Set category by paste</h3>
            <p class="text-sm text-slate-500 mb-4">Choose a category, paste domains (one per line), and apply. Unknown domains are skipped.</p>
            <form method="POST" action="{{ route('wp-sites.bulk-category-paste') }}" class="space-y-3">
                @csrf
                <select name="wp_site_category_id" class="w-full rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                    <option value="">Clear category</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <textarea name="domains" rows="10" required placeholder="example.com&#10;anotherdomain.com" class="w-full rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500 font-mono"></textarea>
                <button type="submit" class="px-4 py-2 bg-slate-800 text-white text-sm rounded-lg hover:bg-slate-900">Apply to matching sites</button>
            </form>
        </div>
    </div>
</div>
@endsection
