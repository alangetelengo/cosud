@extends('layouts.app')

@section('page-title', 'Plan de classement')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('parametres.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">Paramètres</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">Plan de classement</span>
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

    <div class="flex flex-wrap items-center justify-between gap-4">
        <p class="text-slate-600 dark:text-slate-400 text-sm max-w-2xl">
            Arborescence des dossiers affichée aux utilisateurs dans le plan de classement. Créez des racines ou des sous-dossiers, rattachez une structure et un type pour la cohérence avec les règles métier.
        </p>
        <a href="{{ route('parametres.plan-classement.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shadow-sm transition-colors no-underline">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            Nouveau dossier (racine)
        </a>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-6">
        @if($racines->isEmpty())
        <p class="text-slate-500 dark:text-slate-400 text-center py-12">Aucun dossier. Utilisez le bouton ci-dessus ou exécutez les seeders.</p>
        @else
        <ul class="space-y-2">
            @foreach($racines as $node)
                @include('parametres.plan-classement._tree-node', ['node' => $node])
            @endforeach
        </ul>
        @endif
    </div>

    <div class="flex gap-4">
        <a href="{{ route('parametres.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border-2 border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 no-underline">
            ← Retour aux paramètres
        </a>
    </div>
</div>
@endsection
