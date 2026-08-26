@props([
    'href',
    'variant' => 'primary',
])

@php
    $classes = match ($variant) {
        'secondary' => 'inline-flex items-center px-2.5 py-1 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-[11px] font-semibold no-underline hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors',
        'sky' => 'inline-flex items-center px-2.5 py-1 rounded-lg border border-sky-500/50 text-sky-700 dark:text-sky-300 text-[11px] font-semibold no-underline hover:bg-sky-50 dark:hover:bg-sky-900/30 transition-colors',
        default => 'inline-flex items-center px-2.5 py-1 rounded-lg border border-emerald-600/50 text-emerald-700 dark:text-emerald-300 text-[11px] font-semibold no-underline hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-colors',
    };
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
