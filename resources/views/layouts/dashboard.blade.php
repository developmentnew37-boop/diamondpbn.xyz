<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) - PBN Link Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <x-build-assets />

    <style>
        [x-cloak] {
            display: none !important;
        }

        .sidebar-transition {
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .page-enter {
            animation: pageEnter 0.4s ease-out forwards;
        }

        @keyframes pageEnter {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-hover {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px -12px rgba(234, 88, 12, 0.2);
        }

        .card-hover .card-icon {
            transition: transform 0.3s ease;
        }

        .card-hover:hover .card-icon {
            transform: scale(1.08);
        }

        .nav-item-active {
            background: rgba(254, 215, 170, 0.12);
            color: #fff;
            border-left: 3px solid rgb(234, 88, 12);
        }

        .nav-item-hover:hover {
            background: rgba(254, 215, 170, 0.08);
            color: #fff;
        }

        .animate-in {
            opacity: 0;
            animation: fadeInUp 0.45s ease-out forwards;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-in-1 {
            animation-delay: 0.06s;
        }

        .animate-in-2 {
            animation-delay: 0.12s;
        }

        .animate-in-3 {
            animation-delay: 0.18s;
        }

        .animate-in-4 {
            animation-delay: 0.24s;
        }

        .animate-in-5 {
            animation-delay: 0.3s;
        }

        .animate-in-6 {
            animation-delay: 0.36s;
        }

        body {
            font-family: 'Outfit', ui-sans-serif, system-ui, sans-serif;
        }

        .sidebar-nav-scroll {
            scrollbar-width: thin;
            scrollbar-color: rgba(100, 116, 139, 0.35) transparent;
        }

        .sidebar-nav-scroll::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar-nav-scroll::-webkit-scrollbar-track {
            background: transparent;
            margin: 4px 0;
        }

        .sidebar-nav-scroll::-webkit-scrollbar-thumb {
            background: rgba(100, 116, 139, 0.3);
            border-radius: 9999px;
        }

        .sidebar-nav-scroll::-webkit-scrollbar-thumb:hover {
            background: rgba(148, 163, 184, 0.5);
        }

        .nav-section-header:first-of-type {
            margin-top: 0.5rem;
        }
    </style>
</head>

<body class="antialiased text-slate-800 min-h-screen bg-slate-50">
    <div class="min-h-screen flex flex-col lg:flex-row" x-data="{ sidebarOpen: false, sidebarCollapsed: false }">
        @include('layouts.partials.sidebar')
        <div class="flex-1 flex flex-col min-h-screen min-w-0 w-full transition-all duration-300 border-l border-slate-200/80"
            :class="sidebarCollapsed ? 'lg:ml-20' : 'lg:ml-64'">
            @include('layouts.partials.navbar')
            <main class="flex-1 p-4 md:p-6 lg:p-8 overflow-auto min-h-0">
                @if (session('success'))
                    <div class="mb-4 p-4 rounded-xl bg-orange-50 border border-orange-200 text-orange-800 shadow-sm animate-in"
                        role="alert">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200 text-red-800" role="alert">
                        {{ session('error') }}
                    </div>
                @endif
                @if (session('info'))
                    <div class="mb-4 p-4 rounded-lg bg-slate-50 border border-slate-200 text-slate-700" role="alert">
                        {{ session('info') }}
                    </div>
                @endif
                @yield('content')
            </main>
            @include('layouts.partials.footer')
        </div>
    </div>
    @yield('scripts')
    @stack('modals')
</body>

</html>
