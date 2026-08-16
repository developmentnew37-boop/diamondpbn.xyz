@extends('layouts.dashboard')
@section('title', 'Profile')
@section('page-title', 'Profile')

@section('content')
@php
    $initials = collect(explode(' ', trim($user->name ?? 'U')))
        ->filter()
        ->take(2)
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->join('') ?: 'U';
@endphp
@php
    $activeTab = 'profile';
    if (session('status') === 'password-updated' || $errors->updatePassword->isNotEmpty()) {
        $activeTab = 'password';
    }
    if ($errors->userDeletion->isNotEmpty()) {
        $activeTab = 'danger';
    }
@endphp
<div class="page-enter w-full max-w-6xl mx-auto" x-data="{ tab: '{{ $activeTab }}' }">
    {{-- Hero --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-orange-500 via-orange-600 to-amber-600 px-6 py-7 md:px-10 md:py-9 mb-8 shadow-lg shadow-orange-500/20 animate-in animate-in-1">
        <div class="absolute -right-10 -top-10 h-48 w-48 rounded-full bg-white/10 blur-2xl pointer-events-none"></div>
        <div class="absolute -bottom-16 left-1/4 h-56 w-56 rounded-full bg-amber-300/15 blur-3xl pointer-events-none"></div>
        <div class="relative flex flex-col lg:flex-row lg:items-center gap-6 lg:gap-8">
            <div class="flex items-center gap-5 min-w-0 flex-1">
                <div class="flex h-[4.5rem] w-[4.5rem] md:h-20 md:w-20 shrink-0 items-center justify-center rounded-2xl bg-white/20 text-2xl font-bold text-white ring-4 ring-white/25 backdrop-blur-sm shadow-inner">
                    {{ $initials }}
                </div>
                <div class="min-w-0">
                    <p class="text-orange-100/90 text-sm font-medium uppercase tracking-wider mb-1">Account</p>
                    <h2 class="text-2xl md:text-3xl font-bold text-white tracking-tight truncate">{{ $user->name }}</h2>
                    <p class="text-orange-50/90 mt-1 truncate text-sm md:text-base">{{ $user->email }}</p>
                    <div class="flex flex-wrap items-center gap-2 mt-3">
                        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-400/30 text-amber-50 border border-amber-200/30">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-200"></span>
                                Email unverified
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-400/25 text-emerald-50 border border-emerald-200/30">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Verified
                            </span>
                        @endif
                        @if ($user->created_at)
                            <span class="text-xs text-orange-100/75">Member since {{ $user->created_at->format('M Y') }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-3 lg:shrink-0">
                <a href="{{ route('settings.index') }}" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-white text-orange-700 text-sm font-semibold hover:bg-orange-50 shadow-md transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    App Settings
                </a>
                <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-white/15 text-white text-sm font-medium hover:bg-white/25 border border-white/25 backdrop-blur-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_300px] gap-6 xl:gap-8 items-start">
        {{-- Main settings column --}}
        <div class="min-w-0 animate-in animate-in-2">
            {{-- Horizontal tabs --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-1.5 mb-6 flex flex-wrap gap-1">
                <button type="button" @click="tab = 'profile'"
                    :class="tab === 'profile' ? 'bg-orange-500 text-white shadow-sm shadow-orange-500/30' : 'text-slate-600 hover:bg-slate-50'"
                    class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold transition-all min-w-[120px]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Profile
                </button>
                <button type="button" @click="tab = 'password'"
                    :class="tab === 'password' ? 'bg-orange-500 text-white shadow-sm shadow-orange-500/30' : 'text-slate-600 hover:bg-slate-50'"
                    class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold transition-all min-w-[120px]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    Password
                </button>
                <button type="button" @click="tab = 'danger'"
                    :class="tab === 'danger' ? 'bg-red-500 text-white shadow-sm shadow-red-500/30' : 'text-slate-600 hover:bg-slate-50'"
                    class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold transition-all min-w-[120px]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Delete
                </button>
            </div>

            {{-- Profile tab --}}
            <div x-show="tab === 'profile'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="bg-white rounded-2xl border border-orange-100 shadow-md overflow-hidden">
                    <div class="px-6 md:px-8 py-5 border-b border-orange-50/80">
                        <h3 class="text-lg font-semibold text-slate-800">{{ __('Profile Information') }}</h3>
                        <p class="text-sm text-slate-500 mt-1">{{ __("Update your account's profile information and email address.") }}</p>
                    </div>
                    <div class="px-6 md:px-8 py-6 md:py-8">
                        <form id="send-verification" method="post" action="{{ route('verification.send') }}">@csrf</form>
                        <form method="post" action="{{ route('profile.update') }}" class="space-y-6 max-w-2xl">
                            @csrf
                            @method('patch')
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="md:col-span-1">
                                    <label for="name" class="block text-sm font-medium text-slate-700 mb-2">{{ __('Name') }}</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        </span>
                                        <input id="name" name="name" type="text" class="w-full pl-10 py-2.5 rounded-xl border-slate-200 bg-slate-50/50 focus:bg-white focus:border-orange-500 focus:ring-orange-500 transition-colors" value="{{ old('name', $user->name) }}" required autocomplete="name" />
                                    </div>
                                    @error('name')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div class="md:col-span-1">
                                    <label for="email" class="block text-sm font-medium text-slate-700 mb-2">{{ __('Email') }}</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                        </span>
                                        <input id="email" name="email" type="email" class="w-full pl-10 py-2.5 rounded-xl border-slate-200 bg-slate-50/50 focus:bg-white focus:border-orange-500 focus:ring-orange-500 transition-colors" value="{{ old('email', $user->email) }}" required autocomplete="username" />
                                    </div>
                                    @error('email')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                                <div class="p-4 rounded-xl bg-amber-50 border border-amber-200/80 text-sm text-amber-900 max-w-2xl">
                                    {{ __('Your email address is unverified.') }}
                                    <button type="submit" form="send-verification" class="font-semibold text-orange-600 hover:text-orange-700 underline underline-offset-2">{{ __('Resend verification email') }}</button>
                                    @if (session('status') === 'verification-link-sent')
                                        <p class="mt-2 text-emerald-700 font-medium">{{ __('A new verification link has been sent.') }}</p>
                                    @endif
                                </div>
                            @endif
                            <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-slate-100">
                                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-orange-500 text-white rounded-xl font-semibold hover:bg-orange-600 shadow-sm shadow-orange-500/25 transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    {{ __('Save changes') }}
                                </button>
                                @if (session('status') === 'profile-updated')
                                    <span x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 3000)" class="inline-flex items-center gap-1.5 text-sm text-emerald-700 font-medium">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        {{ __('Saved successfully') }}
                                    </span>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Password tab --}}
            <div x-show="tab === 'password'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-md overflow-hidden">
                    <div class="px-6 md:px-8 py-5 border-b border-slate-100">
                        <h3 class="text-lg font-semibold text-slate-800">{{ __('Update Password') }}</h3>
                        <p class="text-sm text-slate-500 mt-1">{{ __('Use a long, random password to keep your account secure.') }}</p>
                    </div>
                    <div class="px-6 md:px-8 py-6 md:py-8">
                        <form method="post" action="{{ route('password.update') }}" class="space-y-6 max-w-2xl">
                            @csrf
                            @method('put')
                            <div>
                                <label for="update_password_current_password" class="block text-sm font-medium text-slate-700 mb-2">{{ __('Current Password') }}</label>
                                <input id="update_password_current_password" name="current_password" type="password" class="w-full py-2.5 rounded-xl border-slate-200 bg-slate-50/50 focus:bg-white focus:border-orange-500 focus:ring-orange-500" autocomplete="current-password" placeholder="Enter current password" />
                                @error('current_password', 'updatePassword')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="update_password_password" class="block text-sm font-medium text-slate-700 mb-2">{{ __('New Password') }}</label>
                                    <input id="update_password_password" name="password" type="password" class="w-full py-2.5 rounded-xl border-slate-200 bg-slate-50/50 focus:bg-white focus:border-orange-500 focus:ring-orange-500" autocomplete="new-password" placeholder="Enter new password" />
                                    @error('password', 'updatePassword')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="update_password_password_confirmation" class="block text-sm font-medium text-slate-700 mb-2">{{ __('Confirm Password') }}</label>
                                    <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="w-full py-2.5 rounded-xl border-slate-200 bg-slate-50/50 focus:bg-white focus:border-orange-500 focus:ring-orange-500" autocomplete="new-password" placeholder="Confirm new password" />
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-slate-100">
                                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-slate-800 text-white rounded-xl font-semibold hover:bg-slate-900 shadow-sm transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    {{ __('Update password') }}
                                </button>
                                @if (session('status') === 'password-updated')
                                    <span x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 3000)" class="inline-flex items-center gap-1.5 text-sm text-emerald-700 font-medium">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        {{ __('Password updated') }}
                                    </span>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Delete tab --}}
            <div x-show="tab === 'danger'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="rounded-2xl border-2 border-red-200 bg-gradient-to-br from-red-50/60 via-white to-white shadow-md overflow-hidden">
                    <div class="px-6 md:px-8 py-6 md:py-8">
                        <div class="flex flex-col md:flex-row md:items-start gap-5 max-w-3xl">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-lg font-semibold text-red-900">{{ __('Delete Account') }}</h3>
                                <p class="text-sm text-red-800/80 mt-2 leading-relaxed">{{ __('Permanently remove your account and all associated data. All domains, batches, and campaigns will be lost. This action cannot be undone.') }}</p>
                                <ul class="mt-4 space-y-2 text-sm text-slate-600">
                                    <li class="flex items-start gap-2"><span class="text-red-400 mt-0.5">•</span> Export reports before deleting if you need history</li>
                                    <li class="flex items-start gap-2"><span class="text-red-400 mt-0.5">•</span> Remote links on PBN sites are not auto-removed</li>
                                    <li class="flex items-start gap-2"><span class="text-red-400 mt-0.5">•</span> You will be signed out immediately</li>
                                </ul>
                                <button type="button" onclick="document.getElementById('confirm-delete-modal').classList.remove('hidden')" class="mt-6 inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 text-white rounded-xl font-semibold hover:bg-red-700 shadow-sm shadow-red-500/25 transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    {{ __('Delete my account') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right sidebar --}}
        <aside class="xl:sticky xl:top-6 space-y-5 animate-in animate-in-3">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-4">Account overview</h4>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3 py-2 border-b border-slate-50">
                        <dt class="text-slate-500">Display name</dt>
                        <dd class="font-medium text-slate-800 text-right truncate max-w-[140px]">{{ $user->name }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 py-2 border-b border-slate-50">
                        <dt class="text-slate-500">Email</dt>
                        <dd class="font-medium text-slate-800 text-right truncate max-w-[140px]" title="{{ $user->email }}">{{ $user->email }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 py-2">
                        <dt class="text-slate-500">Status</dt>
                        <dd>
                            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                                <span class="text-amber-600 font-medium">Unverified</span>
                            @else
                                <span class="text-emerald-600 font-medium">Active</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="bg-gradient-to-br from-orange-50 to-amber-50/50 rounded-2xl border border-orange-100 p-5">
                <h4 class="text-sm font-semibold text-slate-800 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Security tips
                </h4>
                <ul class="space-y-2.5 text-sm text-slate-600 leading-relaxed">
                    <li>Use a unique password of 12+ characters</li>
                    <li>Keep your API keys private on each domain</li>
                    <li>Review app settings for timeout &amp; queue delays</li>
                </ul>
                <a href="{{ route('settings.index') }}" class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-orange-600 hover:text-orange-700">
                    Open settings
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-3">Quick links</h4>
                <nav class="space-y-1">
                    <a href="{{ route('batches.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-slate-600 hover:bg-slate-50 hover:text-orange-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        My batches
                    </a>
                    <a href="{{ route('domains.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-slate-600 hover:bg-slate-50 hover:text-orange-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                        My domains
                    </a>
                    <a href="{{ route('reports.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-slate-600 hover:bg-slate-50 hover:text-orange-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Reports
                    </a>
                </nav>
            </div>
        </aside>
    </div>
</div>

{{-- Delete modal --}}
<div id="confirm-delete-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm" onclick="document.getElementById('confirm-delete-modal').classList.add('hidden')"></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200 overflow-hidden">
            <div class="px-6 pt-6 pb-4 bg-gradient-to-br from-red-50 to-white border-b border-red-100">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-100 text-red-600 mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <h2 class="text-xl font-bold text-slate-900">{{ __('Delete your account?') }}</h2>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed">{{ __('Enter your password to confirm. All your data will be permanently deleted.') }}</p>
            </div>
            <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
                @csrf
                @method('delete')
                <div>
                    <label for="delete_password" class="block text-sm font-medium text-slate-700 mb-2">{{ __('Password') }}</label>
                    <input id="delete_password" name="password" type="password" class="w-full py-2.5 rounded-xl border-slate-200 focus:border-red-500 focus:ring-red-500" placeholder="{{ __('Your password') }}" required autofocus />
                    @error('password', 'userDeletion')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                    <button type="button" onclick="document.getElementById('confirm-delete-modal').classList.add('hidden')" class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 font-medium hover:bg-slate-50 transition-colors">{{ __('Cancel') }}</button>
                    <button type="submit" class="px-4 py-2.5 rounded-xl bg-red-600 text-white font-semibold hover:bg-red-700 shadow-sm transition-colors">{{ __('Yes, delete my account') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@if($errors->userDeletion->isNotEmpty())
    <script>document.getElementById('confirm-delete-modal').classList.remove('hidden');</script>
@endif
@endsection
