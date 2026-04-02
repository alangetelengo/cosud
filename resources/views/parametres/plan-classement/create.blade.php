@extends('layouts.app')

@section('page-title', 'Nouveau dossier — Plan de classement')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('parametres.index') }}" class="text-slate-500 hover:text-emerald-600 transition-colors">Paramètres</a>
        <span class="text-slate-300">/</span>
        <a href="{{ route('parametres.plan-classement.index') }}" class="text-slate-500 hover:text-emerald-600 transition-colors">Plan de classement</a>
        <span class="text-slate-300">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">Créer</span>
    </nav>
@endsection

@section('content')
<div class="max-w-2xl">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-8">
        @if(isset($parent) && $parent)
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-6 p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800">
            Sous-dossier de : <strong class="text-slate-800 dark:text-slate-200">{{ $parent->chemin_complet }}</strong>
        </p>
        @endif
        <form action="{{ route('parametres.plan-classement.store') }}" method="POST" class="space-y-6">
            @csrf
            @include('parametres.plan-classement._fields', ['dossier' => null, 'parent' => $parent ?? null, 'parents' => $parents, 'typesDossier' => $typesDossier, 'structures' => $structures])
            <div class="flex flex-wrap gap-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">Enregistrer</button>
                <a href="{{ route('parametres.plan-classement.index') }}" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl border-2 border-slate-300 dark:border-slate-600 font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 no-underline">Annuler</a>
            </div>
        </form>
    </div>
</div>
@endsection
