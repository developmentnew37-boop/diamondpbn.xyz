@php
    $manifestPath = public_path('build/manifest.json');
    $cssHref = null;
    $jsHref = null;

    if (is_readable($manifestPath)) {
        $manifest = json_decode((string) file_get_contents($manifestPath), true) ?: [];

        $cssFile = $manifest['resources/css/app.css']['file'] ?? null;
        $jsFile = $manifest['resources/js/app.js']['file'] ?? null;

        if (is_string($cssFile) && $cssFile !== '') {
            $cssHref = asset('build/' . ltrim($cssFile, '/'));
        }

        if (is_string($jsFile) && $jsFile !== '') {
            $jsHref = asset('build/' . ltrim($jsFile, '/'));
        }
    }
@endphp

@if($cssHref)
    <link rel="stylesheet" href="{{ $cssHref }}">
@endif

@if($jsHref)
    <script src="{{ $jsHref }}" defer></script>
@endif
