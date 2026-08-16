@extends('layouts.dashboard')
@section('title', 'Edit Target Domain')
@section('page-title', 'Edit Target Domain')

@section('content')
<div class="page-enter max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('campaign-domains.index') }}" class="inline-flex items-center text-sm text-slate-600 hover:text-slate-800">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Target Domains
        </a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-xl font-bold text-slate-800 mb-6">Edit Target Domain</h2>

        <form method="POST" action="{{ route('campaign-domains.update', $campaignDomain) }}">
            @csrf
            @method('PATCH')

            <div class="space-y-5">
                <div>
                    <label for="domain" class="block text-sm font-medium text-slate-700 mb-1">Domain</label>
                    <input
                        type="text"
                        id="domain"
                        name="domain"
                        value="{{ old('domain', $campaignDomain->domain) }}"
                        required
                        placeholder="example.com"
                        class="w-full rounded-lg border-slate-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 @error('domain') border-red-500 @enderror"
                    >
                    @error('domain')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="api_url" class="block text-sm font-medium text-slate-700 mb-1">API URL</label>
                    <input
                        type="url"
                        id="api_url"
                        name="api_url"
                        value="{{ old('api_url', $campaignDomain->api_url) }}"
                        required
                        placeholder="https://example.com/api"
                        class="w-full rounded-lg border-slate-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 @error('api_url') border-red-500 @enderror"
                    >
                    @error('api_url')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="api_key" class="block text-sm font-medium text-slate-700 mb-1">API Key (optional)</label>
                    <input
                        type="text"
                        id="api_key"
                        name="api_key"
                        value="{{ old('api_key', $campaignDomain->api_key) }}"
                        placeholder="Your API key"
                        class="w-full rounded-lg border-slate-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 @error('api_key') border-red-500 @enderror"
                    >
                    @error('api_key')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="notes" class="block text-sm font-medium text-slate-700 mb-1">Notes (optional)</label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="3"
                        placeholder="Add any notes about this domain..."
                        class="w-full rounded-lg border-slate-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 @error('notes') border-red-500 @enderror"
                    >{{ old('notes', $campaignDomain->notes) }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <a href="{{ route('campaign-domains.index') }}" class="flex-1 px-4 py-2 border border-slate-300 rounded-lg text-center text-slate-700 hover:bg-slate-50 transition-colors">
                    Cancel
                </a>
                <button type="submit" class="flex-1 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                    Update Domain
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
