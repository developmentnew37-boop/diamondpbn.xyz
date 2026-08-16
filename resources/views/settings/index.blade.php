@extends('layouts.dashboard')
@section('title', 'Settings')
@section('page-title', 'Settings')

@section('content')
<div class="page-enter">
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-slate-800">Settings</h2>
        <p class="text-slate-500 mt-1">Profile, password, API timeout, and queue configuration</p>
    </div>
    <div class="max-w-2xl space-y-6">
        <div class="bg-white rounded-xl border border-orange-100 shadow-sm p-6">
            <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2">
                <span class="w-1 h-6 rounded-full bg-orange-500"></span>
                Profile &amp; Password
            </h3>
            <p class="text-sm text-slate-600 mb-4">Update your name, email, and change your password from your profile page.</p>
            <a href="{{ route('profile.edit') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-orange-500 text-white rounded-lg font-medium hover:bg-orange-600 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Open Profile
            </a>
        </div>
        <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
            @csrf
            @method('patch')
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h3 class="font-semibold text-slate-800 mb-4">API Configuration</h3>
            <div class="space-y-4">
                <div>
                    <label for="api_timeout_seconds" class="block text-sm font-medium text-slate-700 mb-1">API Timeout (seconds)</label>
                    <input type="number" id="api_timeout_seconds" name="api_timeout_seconds" value="{{ old('api_timeout_seconds', $api_timeout_seconds ?? 30) }}" min="10" max="600" class="w-full max-w-xs rounded-lg border-slate-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                    <p class="text-xs text-slate-500 mt-1">Used for health checks and posting links (10–600s).</p>
                    @error('api_timeout_seconds')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="delete_timeout_seconds" class="block text-sm font-medium text-slate-700 mb-1">Delete Timeout (seconds)</label>
                    <input type="number" id="delete_timeout_seconds" name="delete_timeout_seconds" value="{{ old('delete_timeout_seconds', $delete_timeout_seconds ?? 900) }}" min="60" max="1800" class="w-full max-w-xs rounded-lg border-slate-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                    <p class="text-xs text-slate-500 mt-1">Bulk delete by batch can be slow on large sites. Retries apply automatically for 508/522 errors (60–1800s).</p>
                    @error('delete_timeout_seconds')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h3 class="font-semibold text-slate-800 mb-4">Queue Configuration</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="text-slate-700">Queue Driver</span>
                    <span class="px-2 py-1 bg-slate-100 text-slate-700 rounded text-sm font-medium">Database</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="text-slate-700">Redis</span>
                    <span class="text-slate-500 text-sm">Not used (will be implemented in another project)</span>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h3 class="font-semibold text-slate-800 mb-4">Rate Limiting</h3>
            <div class="space-y-4">
                <div>
                    <label for="link_delay_seconds" class="block text-sm font-medium text-slate-700 mb-1">Delay between jobs per domain (seconds)</label>
                    <input type="number" id="link_delay_seconds" name="link_delay_seconds" value="{{ old('link_delay_seconds', $link_delay_seconds ?? 5) }}" min="0" max="300" class="w-full max-w-xs rounded-lg border-slate-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                    <p class="text-xs text-slate-500 mt-1">When creating or publishing batches, delay between dispatching each chunk to avoid hammering PBN sites (0–300s).</p>
                    @error('link_delay_seconds')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="px-4 py-2 bg-orange-500 text-white rounded-lg font-medium hover:bg-orange-600 transition-colors">Save Settings</button>
        </div>
        </form>
    </div>
</div>
@endsection
