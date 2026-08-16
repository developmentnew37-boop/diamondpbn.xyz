@php
    $navBase = 'flex items-center py-2.5 rounded-r-lg text-slate-300 transition-all duration-200 border-l-[3px] border-transparent ';
    $navActive = 'nav-item-active bg-slate-800/90 text-white ';
    $navHover = 'nav-item-hover ';
    $navSectionDivider = 'my-3 mx-2 border-t border-slate-700/60';
@endphp
<aside class="hidden lg:flex lg:flex-col lg:fixed lg:inset-y-0 left-0 z-30 bg-slate-900 border-r border-slate-700/80 sidebar-transition overflow-hidden"
       :class="sidebarCollapsed ? 'lg:w-20' : 'lg:w-64'">
    <div class="flex items-center h-16 px-4 border-b border-slate-800 shrink-0" :class="sidebarCollapsed ? 'justify-center' : ''">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 group min-w-0" :class="sidebarCollapsed ? 'justify-center' : ''">
            <div class="w-9 h-9 rounded-lg bg-orange-500 flex items-center justify-center group-hover:bg-orange-400 transition-all duration-300 shrink-0 group-hover:scale-105">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
            </div>
            <span class="font-semibold text-white text-lg truncate" x-show="!sidebarCollapsed">Link Manager</span>
        </a>
    </div>
    <nav class="sidebar-nav-scroll flex-1 overflow-y-auto overflow-x-hidden py-3 space-y-0.5" :class="sidebarCollapsed ? 'px-2' : 'px-2'">
        <a href="{{ route('dashboard') }}" class="{{ $navBase }} {{ $navHover }} {{ request()->routeIs('dashboard') ? $navActive : '' }}" :class="sidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-3'">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
            </svg>
            <span class="truncate" x-show="!sidebarCollapsed">Dashboard</span>
        </a>

        {{-- PBN Sites --}}
        <x-sidebar-section label="PBN Sites" x-show="!sidebarCollapsed" />
        <div class="{{ $navSectionDivider }}" x-show="sidebarCollapsed"></div>
        <a href="{{ route('domains.index') }}" class="{{ $navBase }} {{ $navHover }} {{ request()->routeIs('domains.*') ? $navActive : '' }}" :class="sidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-3'">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
            </svg>
            <span class="truncate" x-show="!sidebarCollapsed">Domains</span>
        </a>
        <a href="{{ route('batches.index') }}" class="{{ $navBase }} {{ $navHover }} {{ request()->routeIs('batches.index') ? $navActive : '' }}" :class="sidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-3'">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
            <span class="truncate" x-show="!sidebarCollapsed">Batches</span>
        </a>
        <a href="{{ route('batches.create') }}" class="{{ $navBase }} {{ $navHover }} {{ request()->routeIs('batches.create') ? $navActive : '' }}" :class="sidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-3'">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            <span class="truncate" x-show="!sidebarCollapsed">Create Batch</span>
        </a>

        {{-- Campaigns --}}
        <x-sidebar-section label="Campaigns" x-show="!sidebarCollapsed" />
        <div class="{{ $navSectionDivider }}" x-show="sidebarCollapsed"></div>
        <a href="{{ route('campaign-domains.index') }}" class="{{ $navBase }} {{ $navHover }} {{ request()->routeIs('campaign-domains.*') ? $navActive : '' }}" :class="sidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-3'">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="truncate" x-show="!sidebarCollapsed">Target Domains</span>
        </a>
        <a href="{{ route('campaigns.index') }}" class="{{ $navBase }} {{ $navHover }} {{ request()->routeIs('campaigns.index') ? $navActive : '' }}" :class="sidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-3'">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
            </svg>
            <span class="truncate" x-show="!sidebarCollapsed">Campaigns</span>
        </a>
        <a href="{{ route('campaigns.create') }}" class="{{ $navBase }} {{ $navHover }} {{ request()->routeIs('campaigns.create') ? $navActive : '' }}" :class="sidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-3'">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="truncate" x-show="!sidebarCollapsed">Create Campaign</span>
        </a>
        <a href="{{ route('hidden-links.index') }}" class="{{ $navBase }} {{ $navHover }} {{ request()->routeIs('hidden-links.*') ? $navActive : '' }}" :class="sidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-3'">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
            <span class="truncate" x-show="!sidebarCollapsed">Hidden Links</span>
        </a>

        {{-- WordPress --}}
        <x-sidebar-section label="WordPress" x-show="!sidebarCollapsed" />
        <div class="{{ $navSectionDivider }}" x-show="sidebarCollapsed"></div>
        <a href="{{ route('wp-sites.index') }}" class="{{ $navBase }} {{ $navHover }} {{ request()->routeIs('wp-sites.*') ? $navActive : '' }}" :class="sidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-3'">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
            </svg>
            <span class="truncate" x-show="!sidebarCollapsed">WP Sites</span>
        </a>
        <a href="{{ route('wp-batches.index') }}" class="{{ $navBase }} {{ $navHover }} {{ request()->routeIs('wp-batches.*') && ! request()->routeIs('wp-batches.create') ? $navActive : '' }}" :class="sidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-3'">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
            <span class="truncate" x-show="!sidebarCollapsed">WP Batches</span>
        </a>
        <a href="{{ route('wp-batches.create') }}" class="{{ $navBase }} {{ $navHover }} {{ request()->routeIs('wp-batches.create') ? $navActive : '' }}" :class="sidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-3'">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            <span class="truncate" x-show="!sidebarCollapsed">Create WP Batch</span>
        </a>

        {{-- Account --}}
        <x-sidebar-section label="Account" x-show="!sidebarCollapsed" />
        <div class="{{ $navSectionDivider }}" x-show="sidebarCollapsed"></div>
        <a href="{{ route('profile.edit') }}" class="{{ $navBase }} {{ $navHover }} {{ request()->routeIs('profile.*') ? $navActive : '' }}" :class="sidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-3'">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            <span class="truncate" x-show="!sidebarCollapsed">Profile</span>
        </a>
        <a href="{{ route('reports.index') }}" class="{{ $navBase }} {{ $navHover }} {{ request()->routeIs('reports.*') ? $navActive : '' }}" :class="sidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-3'">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span class="truncate" x-show="!sidebarCollapsed">Reports</span>
        </a>
        <a href="{{ route('settings.index') }}" class="{{ $navBase }} {{ $navHover }} {{ request()->routeIs('settings.*') ? $navActive : '' }}" :class="sidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-3'">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span class="truncate" x-show="!sidebarCollapsed">Settings</span>
        </a>
    </nav>
    {{-- Sidebar toggle (desktop) --}}
    <div class="shrink-0 border-t border-slate-800 p-2 flex justify-center">
        <button type="button" @click="sidebarCollapsed = !sidebarCollapsed" class="p-2.5 rounded-lg text-slate-400 hover:text-orange-400 hover:bg-slate-800 transition-all duration-200" title="Toggle sidebar">
            <svg class="w-5 h-5 transition-transform duration-300" :class="{ 'rotate-180': sidebarCollapsed }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
            </svg>
        </button>
    </div>
</aside>
{{-- Mobile sidebar overlay --}}
<div x-show="sidebarOpen" x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" @click="sidebarOpen = false" class="fixed inset-0 z-40 bg-slate-900/60 lg:hidden" x-cloak></div>
{{-- Mobile sidebar --}}
<aside x-show="sidebarOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 border-r border-slate-800 lg:hidden" x-cloak>
    <div class="flex items-center h-16 px-6 border-b border-slate-800">
        <span class="font-semibold text-white">Link Manager</span>
    </div>
    <nav class="sidebar-nav-scroll py-3 px-3 space-y-0.5 overflow-y-auto max-h-[calc(100vh-4rem)]">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg text-slate-300 hover:bg-slate-800/80 hover:text-white transition-all">Dashboard</a>

        <x-sidebar-section label="PBN Sites" />
        <a href="{{ route('domains.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg text-slate-300 hover:bg-slate-800/80 hover:text-white transition-all">Domains</a>
        <a href="{{ route('batches.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg text-slate-300 hover:bg-slate-800/80 hover:text-white transition-all">Batches</a>
        <a href="{{ route('batches.create') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg text-slate-300 hover:bg-slate-800/80 hover:text-white transition-all">Create Batch</a>

        <x-sidebar-section label="Campaigns" />
        <a href="{{ route('campaign-domains.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg text-slate-300 hover:bg-slate-800/80 hover:text-white transition-all">Target Domains</a>
        <a href="{{ route('campaigns.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg text-slate-300 hover:bg-slate-800/80 hover:text-white transition-all">Campaigns</a>
        <a href="{{ route('campaigns.create') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg text-slate-300 hover:bg-slate-800/80 hover:text-white transition-all">Create Campaign</a>
        <a href="{{ route('hidden-links.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg text-slate-300 hover:bg-slate-800/80 hover:text-white transition-all">Hidden Links</a>

        <x-sidebar-section label="WordPress" />
        <a href="{{ route('wp-sites.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg text-slate-300 hover:bg-slate-800/80 hover:text-white transition-all">WP Sites</a>
        <a href="{{ route('wp-batches.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg text-slate-300 hover:bg-slate-800/80 hover:text-white transition-all">WP Batches</a>
        <a href="{{ route('wp-batches.create') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg text-slate-300 hover:bg-slate-800/80 hover:text-white transition-all">Create WP Batch</a>

        <x-sidebar-section label="Account" />
        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg text-slate-300 hover:bg-slate-800/80 hover:text-white transition-all">Profile</a>
        <a href="{{ route('reports.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg text-slate-300 hover:bg-slate-800/80 hover:text-white transition-all">Reports</a>
        <a href="{{ route('settings.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg text-slate-300 hover:bg-slate-800/80 hover:text-white transition-all">Settings</a>
    </nav>
</aside>
