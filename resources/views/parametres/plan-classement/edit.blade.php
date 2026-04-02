@extends('layouts.app')

@section('page-title', 'Modifier le dossier')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('parametres.index') }}" class="text-slate-500 hover:text-emerald-600 transition-colors">Paramètres</a>
        <span class="text-slate-300">/</span>
        <a href="{{ route('parametres.plan-classement.index') }}" class="text-slate-500 hover:text-emerald-600 transition-colors">Plan de classement</a>
        <span class="text-slate-300">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">{{ $dossier->nom }}</span>
    </nav>
@endsection

@section('content')
<div class="max-w-2xl">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-8">
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Chemin : <span class="font-medium text-slate-700 dark:text-slate-300">{{ $dossier->chemin_complet }}</span></p>
        <form action="{{ route('parametres.plan-classement.update', $dossier) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')
            @include('parametres.plan-classement._fields', ['dossier' => $dossier, 'parent' => null, 'parents' => $parents, 'typesDossier' => $typesDossier, 'structures' => $structures])
            <div class="flex flex-wrap gap-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">Mettre à jour</button>
                <a href="{{ route('parametres.plan-classement.index') }}" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl border-2 border-slate-300 dark:border-slate-600 font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 no-underline">Annuler</a>
            </div>
        </form>
    </div>
</div>
@endsection
