@props(['label'])

<div {{ $attributes->merge(['class' => 'nav-section-header px-3 pt-3 pb-0.5']) }}>
    <span class="font-medium uppercase text-slate-500 select-none" style="font-size: 10px; letter-spacing: 0.06em;">{{ $label }}</span>
</div>
