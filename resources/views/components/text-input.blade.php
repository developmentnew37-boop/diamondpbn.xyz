@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-slate-300 focus:border-orange-500 focus:ring-orange-500 rounded-lg shadow-sm']) }}>
