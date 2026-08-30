@extends('layouts.error')

@section('title', 'Accès refusé — '.config('app.name', 'COSUD'))

@section('content')
    <div class="min-h-screen flex flex-col items-center justify-center p-6 sm:p-8">
        {{-- Fond décoratif (même esprit que les tableaux de bord) --}}
        <div class="pointer-events-none fixed inset-0 overflow-hidden">
            <div class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-emerald-500/10 blur-3xl dark:bg-emerald-400/10"></div>
            <div class="absolute -bottom-32 -left-32 h-96 w-96 rounded-full bg-teal-600/10 blur-3xl dark:bg-teal-500/10"></div>
        </div>

        <div class="relative w-full max-w-lg">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-800 dark:shadow-slate-900/50 overflow-hidden">
                <div class="bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-700 px-6 py-8 sm:px-8 text-center">
                    <p class="text-sm font-semibold uppercase tracking-wider text-white/90">Erreur</p>
                    <p class="mt-2 text-5xl font-bold tabular-nums text-white drop-shadow-sm">403</p>
                    <h1 class="mt-3 text-xl font-bold text-white sm:text-2xl">Accès refusé</h1>
                </div>
                <div class="px-6 py-8 sm:px-8">
                    <p class="text-center text-slate-600 dark:text-slate-300 leading-relaxed">
                        Vous n’avez pas l’autorisation d’effectuer cette action. Si vous pensez qu’il s’agit d’une erreur, contactez un administrateur.
                    </p>
                    <div class="mt-8 flex flex-col sm:flex-row gap-3 sm:justify-center">
                        @auth
                            <button type="button" onclick="if(window.history.length > 1) { window.history.back(); } else { window.location.href='{{ route('home') }}'; }" class="inline-flex justify-center items-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white shadow-md transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-slate-800 no-underline">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                Retour
                            </button>
                        @else
                            <a href="{{ url('/') }}" class="inline-flex justify-center items-center gap-2 rounded-xl border-2 border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 no-underline">
                                Accueil
                            </a>
                            <a href="{{ route('login') }}" class="inline-flex justify-center items-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white shadow-md transition hover:bg-emerald-700 no-underline">
                                Connexion
                            </a>
                        @endauth
                    </div>
                </div>
            </div>
            <p class="mt-6 text-center text-xs text-slate-500 dark:text-slate-400">
                {{ config('app.name', 'COSUD') }} — Courrier et Suivi des Dépenses
            </p>
        </div>
    </div>
@endsection
