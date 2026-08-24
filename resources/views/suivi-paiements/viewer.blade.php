@extends('layouts.app')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'État récapitulatif de suivi des dépenses — '.$annee)
@section('page-title-info', 'Aperçu PDF')

@section('content')
<div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('suivi-paiements.index', $queryRetour) }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-sm font-semibold no-underline hover:bg-slate-50 dark:hover:bg-slate-700">
            ← Retour à la liste
        </a>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            {{ $titre ?? 'État récapitulatif de suivi des dépenses' }}
            @if($categorieLibelle) — {{ $categorieLibelle }} @endif
        </p>
    </div>

    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden shadow-sm">
        <iframe
            src="data:application/pdf;base64,{{ base64_encode($content) }}"
            class="block w-full border-0"
            style="height: calc(100vh - 220px); min-height: 640px;"
            title="État récapitulatif de suivi des dépenses PDF"
        ></iframe>
    </div>
</div>
@endsection
