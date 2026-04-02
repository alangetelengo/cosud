@extends('layouts.app')

@section('page-title', 'Organigramme')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('parametres.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">Paramètres</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">Organigramme</span>
    </nav>
@endsection
@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')

@section('content')
<div class="space-y-6">
    @if(session('success'))
    <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 flex items-center gap-3">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
        <span>{{ session('success') }}</span>
    </div>
    @endif

    @if(session('error'))
    <div class="p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 flex items-center gap-3">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
        <span>{{ session('error') }}</span>
    </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-4">
        <p class="text-slate-600 dark:text-slate-400 text-sm max-w-2xl">
            Hiérarchie des structures. Chaque niveau peut exiger une <strong>fonction métier</strong> (référentiel) et éventuellement un <strong>rôle technique</strong> ; le valideur est le titulaire actuel (affectation utilisateur avec la même fonction). <a href="{{ route('parametres.fonctions.index') }}" class="text-emerald-600 hover:underline font-semibold">Fonctions métier</a>
        </p>
        <a href="{{ route('parametres.structures.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shadow-sm transition-colors no-underline">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            Nouvelle structure
        </a>
    </div>

    {{-- Barre de filtres --}}
    <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700 shadow-sm">
        <form action="{{ route('parametres.structures.index') }}" method="GET" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Recherche</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 z-10 text-slate-400 pointer-events-none">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Nom ou code de structure..."
                        class="w-full pl-12 pr-4 py-3 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700/50 dark:text-white text-sm focus:ring-2 focus:ring-emerald-500/25 focus:border-emerald-500 transition-all placeholder:text-slate-400">
                </div>
            </div>
            <div class="w-56">
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Fonction métier</label>
                <select name="fonction_id" class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700/50 dark:text-white text-sm focus:ring-2 focus:ring-emerald-500/25 focus:border-emerald-500 transition-all">
                    <option value="">Toutes</option>
                    @foreach($fonctions as $fn)
                    <option value="{{ $fn->id }}" {{ (string) request('fonction_id') === (string) $fn->id ? 'selected' : '' }}>{{ $fn->libelle }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-56">
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Titulaire actuel</label>
                <select name="titulaire_user_id" class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700/50 dark:text-white text-sm focus:ring-2 focus:ring-emerald-500/25 focus:border-emerald-500 transition-all">
                    <option value="">Tous</option>
                    @foreach($utilisateursTitulaires as $u)
                    <option value="{{ $u->id }}" {{ (string) request('titulaire_user_id') === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-56">
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Structure parente</label>
                <select name="parent_id" class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700/50 dark:text-white text-sm focus:ring-2 focus:ring-emerald-500/25 focus:border-emerald-500 transition-all">
                    <option value="">Tout l'organigramme</option>
                    @foreach($structuresForParent as $s)
                    <option value="{{ $s->id }}" {{ request('parent_id') == $s->id ? 'selected' : '' }}>{{ $s->nom }} ({{ $s->code ?? '' }})</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-5 py-3 rounded-xl bg-emerald-600 text-white font-semibold hover:bg-emerald-700 shadow-sm transition-all duration-200 text-sm flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                Filtrer
            </button>
            <a href="{{ route('parametres.structures.index') }}" class="px-5 py-3 rounded-xl border-2 border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-all text-sm inline-flex items-center gap-2 no-underline">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Rafraîchir
            </a>
        </form>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-6">
        @forelse($racines as $racine)
            @include('parametres.structures._node', ['structure' => $racine, 'byParent' => $byParent])
        @empty
        <p class="text-slate-500 dark:text-slate-400 text-center py-12">Aucune structure ne correspond aux critères de recherche.</p>
        @endforelse
    </div>

    <div class="flex gap-4">
        <a href="{{ route('parametres.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border-2 border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 no-underline">
            ← Retour aux paramètres
        </a>
    </div>
</div>
@endsection
