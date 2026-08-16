@extends('layouts.dashboard')
@section('title', 'Edit Domain')
@section('page-title', 'Edit Domain')

@section('content')
<div class="page-enter max-w-xl">
    <div class="mb-6">
        <a href="{{ route('domains.index') }}" class="text-sm text-slate-500 hover:text-emerald-600 mb-2 inline-block">← Back to Domains</a>
        <h2 class="text-2xl font-bold text-slate-800">Edit Domain</h2>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <form method="POST" action="{{ route('domains.update', $domain) }}">
            @csrf
            @method('PATCH')
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Domain</label>
                    <input type="text" name="domain" value="{{ old('domain', $domain->domain) }}" required placeholder="example.com" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    @error('domain')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">API URL</label>
                    <input type="url" name="api_url" value="{{ old('api_url', $domain->api_url) }}" required placeholder="http://example.com/api" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    <p class="text-xs text-slate-500 mt-1">Use http:// for sites without SSL.</p>
                    @error('api_url')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">API Key (optional)</label>
                    <input type="text" name="api_key" value="{{ old('api_key', $domain->api_key) }}" placeholder="Your API key" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                @if($domain->last_health_error)
                <div class="rounded-lg bg-red-50 border border-red-200 p-3">
                    <p class="text-sm font-medium text-red-800">Last health check error</p>
                    <p class="text-sm text-red-700 mt-1 break-words">{{ $domain->last_health_error }}</p>
                </div>
                @endif
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Notes (optional)</label>
                    <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('notes', $domain->notes) }}</textarea>
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <a href="{{ route('domains.index') }}" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">Save</button>
            </div>
        </form>
    </div>
</div>
@endsection
