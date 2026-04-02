@extends('layouts.app')

@section('content-container-class', 'w-full max-w-none px-3 sm:px-5 lg:px-8')

@section('title', 'Mon profil — ' . config('app.name', 'GED'))

@section('page-title', 'Mon profil')
@section('page-title-info')
    <span class="text-slate-500 dark:text-slate-400">Compte, sécurité et rattachement organisationnel</span>
@endsection

@section('content')
@php
    $user = $user ?? auth()->user();
    $nameTrim = trim((string) ($user->name ?? ''));
    $parts = $nameTrim !== '' ? preg_split('/\s+/u', $nameTrim) : [];
    if (count($parts) >= 2) {
        $initials = mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
    } elseif (count($parts) === 1) {
        $initials = mb_strtoupper(mb_substr($parts[0], 0, min(2, mb_strlen($parts[0]))));
    } else {
        $initials = '?';
    }
    $emailVerified = ! ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail) || $user->hasVerifiedEmail();
    $membreDepuis = $user->created_at ? $user->created_at->format('d/m/Y') : null;
@endphp

<div class="flex flex-col gap-6 lg:gap-10 pb-10 w-full min-h-[calc(100vh-12rem)]">
    {{-- Bandeau profil : grille équilibrée (plus de vide à droite) --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-600 via-[#0d9f6e] to-teal-800 text-white shadow-[0_20px_50px_-15px_rgba(13,148,136,0.45)] ring-1 ring-white/10">
        <div class="pointer-events-none absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=\'72\' height=\'72\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\'%3E%3Cpath d=\'M30 30l15-15M30 30L15 15M30 30l15 15M30 30l-15 15\' stroke=\'%23fff\' stroke-opacity=\'.08\' stroke-width=\'.5\'/%3E%3C/g%3E%3C/svg%3E')] opacity-90"></div>
        <div class="pointer-events-none absolute -right-24 top-1/2 h-96 w-96 -translate-y-1/2 rounded-full bg-teal-400/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -left-20 bottom-0 h-64 w-64 rounded-full bg-emerald-300/20 blur-3xl"></div>

        <div class="relative p-6 sm:p-8 lg:p-10">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-6 xl:gap-10 items-start lg:items-center">
                {{-- Identité --}}
                <div class="lg:col-span-5 flex flex-col sm:flex-row sm:items-start gap-5 sm:gap-6 min-w-0">
                    <div class="flex h-24 w-24 sm:h-28 sm:w-28 shrink-0 items-center justify-center rounded-3xl bg-white/20 text-2xl sm:text-3xl font-bold tracking-tight shadow-lg ring-2 ring-white/30 backdrop-blur-md" aria-hidden="true">
                        {{ $initials }}
                    </div>
                    <div class="min-w-0 flex-1 space-y-2">
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-emerald-100/95">Espace personnel GED</p>
                        <h2 class="text-2xl sm:text-3xl font-bold leading-tight text-white break-words">{{ $user->name }}</h2>
                        <p class="flex items-start gap-2 text-sm text-emerald-50/95">
                            <svg class="w-5 h-5 shrink-0 mt-0.5 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <span class="break-all">{{ $user->email }}</span>
                        </p>
                        @if($structurePrincipale ?? null)
                        <p class="flex items-start gap-2 pt-1 text-sm text-emerald-100/90 border-t border-white/15 mt-3">
                            <svg class="w-5 h-5 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            <span class="line-clamp-2 leading-snug">{{ $structurePrincipale->nom }}</span>
                        </p>
                        @endif
                        <p class="text-xs text-emerald-200/80 pt-1">Compte n°{{ $user->id }}</p>
                    </div>
                </div>

                {{-- Bloc central : statut + message --}}
                <div class="lg:col-span-4 lg:border-l lg:border-white/20 lg:pl-8 space-y-4">
                    <div class="flex flex-wrap gap-2">
                        @if($emailVerified)
                            <span class="inline-flex items-center gap-2 rounded-full bg-white/20 px-4 py-2 text-sm font-medium text-white backdrop-blur-sm ring-1 ring-white/25">
                                <svg class="w-5 h-5 text-emerald-100" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                E-mail vérifié
                            </span>
                        @else
                            <span class="inline-flex items-center gap-2 rounded-full bg-amber-400/30 px-4 py-2 text-sm font-medium text-white ring-1 ring-amber-200/50">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                E-mail à confirmer
                            </span>
                        @endif
                    </div>
                    <p class="text-sm leading-relaxed text-emerald-50/95">
                        Retrouvez ici vos informations d’identité, votre mot de passe et le lien avec votre structure. Les modifications sont appliquées immédiatement après enregistrement.
                    </p>
                </div>

                {{-- Colonne droite : métadonnées + raccourcis (remplit l’espace) --}}
                <div class="lg:col-span-3 flex flex-col gap-3 min-w-0">
                    @if($membreDepuis)
                    <div class="rounded-2xl bg-white/15 px-4 py-3 backdrop-blur-md ring-1 ring-white/20">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-100/90">Membre depuis</p>
                        <p class="mt-1 text-base font-semibold text-white tabular-nums">{{ $membreDepuis }}</p>
                    </div>
                    @endif
                    <div class="rounded-2xl bg-black/10 px-3 py-3 backdrop-blur-sm ring-1 ring-white/10">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-100/80 mb-2">Raccourcis</p>
                        <div class="flex flex-col gap-2">
                            <a href="{{ route('home') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white/95 px-3 py-2.5 text-sm font-semibold text-emerald-800 shadow-sm transition hover:bg-white hover:shadow-md no-underline">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                Tableau de bord
                            </a>
                            @can('documents.view')
                            <a href="{{ route('documents.index') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white/10 px-3 py-2 text-sm font-medium text-white ring-1 ring-white/25 transition hover:bg-white/20 no-underline">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                Documents
                            </a>
                            @endcan
                            @can('dossiers.view')
                            <a href="{{ route('dossiers.index') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white/10 px-3 py-2 text-sm font-medium text-white ring-1 ring-white/25 transition hover:bg-white/20 no-underline">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                Plan de classement
                            </a>
                            @endcan
                            <a href="{{ route('notifications.index') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white/10 px-3 py-2 text-sm font-medium text-white ring-1 ring-white/25 transition hover:bg-white/20 no-underline">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                Notifications
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Grille principale : 2 colonnes dès lg (meilleure utilisation largeur) --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
        <div class="lg:col-span-7 space-y-6">
            <div class="rounded-3xl border border-slate-200/90 dark:border-slate-600/80 bg-white dark:bg-slate-800 shadow-md hover:shadow-lg transition-shadow duration-300 overflow-hidden">
                <div class="px-5 sm:px-7 py-5 border-b border-slate-100 dark:border-slate-700 bg-gradient-to-r from-slate-50/90 to-white dark:from-slate-700/50 dark:to-slate-800 flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 shadow-inner">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">Identité &amp; contact</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Nom affiché et adresse e-mail utilisés pour vous connecter</p>
                    </div>
                </div>
                <div class="p-5 sm:p-7 lg:p-8">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200/90 dark:border-slate-600/80 bg-white dark:bg-slate-800 shadow-md hover:shadow-lg transition-shadow duration-300 overflow-hidden">
                <div class="px-5 sm:px-7 py-5 border-b border-slate-100 dark:border-slate-700 bg-gradient-to-r from-slate-50/90 to-white dark:from-slate-700/50 dark:to-slate-800 flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-sky-100 dark:bg-sky-900/50 text-sky-700 dark:text-sky-300 shadow-inner">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">Mot de passe</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Mettez à jour votre mot de passe régulièrement</p>
                    </div>
                </div>
                <div class="p-5 sm:p-7 lg:p-8">
                    @include('profile.partials.update-password-form')
                </div>
            </div>
        </div>

        <aside class="lg:col-span-5 space-y-6 lg:sticky lg:top-24 lg:self-start w-full">
            @if($structurePrincipale ?? null)
            <div class="rounded-3xl border border-emerald-200/90 dark:border-emerald-800/70 bg-gradient-to-b from-emerald-50/95 via-white to-white dark:from-emerald-900/30 dark:via-slate-800 dark:to-slate-800 shadow-md overflow-hidden">
                <div class="px-5 sm:px-7 py-5 border-b border-emerald-200/70 dark:border-emerald-800/50 flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">Affectation organisationnelle</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">Votre rattachement dans la GED</p>
                    </div>
                </div>
                <dl class="px-5 sm:px-7 py-6 space-y-4 text-sm">
                    <div class="rounded-2xl bg-white/90 dark:bg-slate-700/35 px-4 py-4 border border-emerald-100/90 dark:border-emerald-900/45 shadow-sm">
                        <dt class="text-xs font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-400">Libellé</dt>
                        <dd class="mt-1.5 text-slate-900 dark:text-slate-100 font-semibold leading-snug">{{ $structurePrincipale->nom }}</dd>
                    </div>
                    <div class="rounded-2xl bg-white/90 dark:bg-slate-700/35 px-4 py-4 border border-emerald-100/90 dark:border-emerald-900/45 shadow-sm">
                        <dt class="text-xs font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-400">Code</dt>
                        <dd class="mt-1.5 font-mono text-slate-800 dark:text-slate-200">{{ $structurePrincipale->code }}</dd>
                    </div>
                    @if(!empty($structureChemin))
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Chemin hiérarchique</dt>
                        <dd class="mt-2 text-slate-800 dark:text-slate-200 leading-relaxed text-sm">{{ $structureChemin }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
            @elseif(isset($user) && $user->structure_id)
            <div class="rounded-3xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/25 px-5 py-5 flex gap-4 shadow-sm">
                <span class="text-2xl shrink-0" aria-hidden="true">⚠️</span>
                <p class="text-sm text-amber-950 dark:text-amber-100 leading-relaxed">Aucune structure active trouvée pour votre rattachement. Contactez un administrateur.</p>
            </div>
            @else
            <div class="rounded-3xl border border-dashed border-slate-300 dark:border-slate-600 bg-slate-50/90 dark:bg-slate-800/60 px-5 py-8 text-center shadow-inner">
                <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">Aucune structure organisationnelle n’est renseignée pour ce compte.</p>
            </div>
            @endif

            <div class="rounded-3xl border-2 border-red-200/95 dark:border-red-900/60 bg-gradient-to-b from-red-50/95 to-white dark:from-red-950/40 dark:to-slate-800 shadow-md overflow-hidden">
                <div class="px-5 sm:px-7 py-5 border-b border-red-200/80 dark:border-red-900/50 bg-red-50/60 dark:bg-red-950/25 flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-red-100 dark:bg-red-900/55 text-red-600 dark:text-red-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-red-900 dark:text-red-200">Zone sensible</h2>
                        <p class="text-sm text-red-800/90 dark:text-red-300/95 mt-1">Suppression définitive du compte</p>
                    </div>
                </div>
                <div class="p-5 sm:px-7 sm:pb-7">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection
