{{--
    Bandeau session success / error (style paramètres utilisateurs).
    Usage : @include('partials.flash-session')
            @include('partials.flash-session', ['class' => 'mb-5 space-y-3'])
--}}
@php
    $wrapperClass = trim(($class ?? '').' space-y-3');
@endphp

@if(session('success') || session('error'))
<div class="{{ $wrapperClass }}">
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-transition
         class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200/80 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 flex items-center gap-4 shadow-sm"
         role="status">
        <span class="flex-shrink-0 w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400 font-bold" aria-hidden="true">✓</span>
        <span class="flex-1 font-medium text-sm sm:text-base">{{ session('success') }}</span>
        <button type="button" @click="show = false"
                class="flex-shrink-0 w-8 h-8 rounded-lg hover:bg-emerald-200/50 dark:hover:bg-emerald-800/30 flex items-center justify-center text-lg font-bold transition-colors"
                title="Fermer" aria-label="Fermer">×</button>
    </div>
    @endif

    @if(session('error'))
    <div x-data="{ show: true }" x-show="show" x-transition
         class="p-4 rounded-2xl bg-red-50 dark:bg-red-900/20 border border-red-200/80 dark:border-red-800 text-red-800 dark:text-red-200 flex items-center gap-4 shadow-sm"
         role="alert">
        <span class="flex-shrink-0 w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/50 flex items-center justify-center text-red-600 dark:text-red-400 font-bold" aria-hidden="true">!</span>
        <span class="flex-1 font-medium text-sm sm:text-base">{{ session('error') }}</span>
        <button type="button" @click="show = false"
                class="flex-shrink-0 w-8 h-8 rounded-lg hover:bg-red-200/50 dark:hover:bg-red-800/30 flex items-center justify-center text-lg font-bold transition-colors"
                title="Fermer" aria-label="Fermer">×</button>
    </div>
    @endif
</div>
@endif
