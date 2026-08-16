<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@if ($title){{ $title }} · @endif Diamond PBN</title>

    <link rel="icon" href="{{ asset('logo.png') }}" type="image/png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <x-build-assets />
    <style>
        body {
            font-family: 'Outfit', ui-sans-serif, system-ui, sans-serif;
        }
    </style>
</head>

<body class="antialiased text-slate-800 min-h-screen bg-slate-100">
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-10">
        <div class="w-full max-w-[400px]">
            <div class="bg-white rounded-lg border border-slate-200/90 shadow-sm overflow-hidden">
                <div class="px-8 pt-8 pb-6 text-center border-b border-slate-100">
                    <a href="{{ url('/') }}" class="inline-block focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 rounded-md">
                        <img src="{{ asset('logo.png') }}" alt="Diamond PBN" width="160" height="64"
                            class="h-12 w-auto max-w-[180px] object-contain mx-auto" />
                    </a>
                    <p class="mt-3 text-sm font-medium text-slate-600">Diamond PBN</p>
                </div>

                <div class="px-8 py-7">
                    {{ $slot }}
                </div>
            </div>

            @hasSection('auth_footer')
                <div class="mt-4 text-center text-sm text-slate-500">
                    @yield('auth_footer')
                </div>
            @endif

            <p class="mt-6 text-center text-xs text-slate-400">
                &copy; {{ date('Y') }} Diamond PBN
            </p>
        </div>
    </div>
</body>

</html>
