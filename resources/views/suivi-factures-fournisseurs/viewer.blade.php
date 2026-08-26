@extends('layouts.app')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Suivi factures fournisseurs et Prestataires')
@section('page-title-info', 'Aperçu PDF — '.$periodeLabel)

@section('content')
<div class="space-y-3">
    <div class="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50/70 dark:bg-emerald-900/20 px-4 py-3 text-sm text-emerald-900 dark:text-emerald-100">
        <p class="font-semibold">{{ $titre }}</p>
        <p class="text-xs mt-1 text-emerald-800/90 dark:text-emerald-200/90">{{ $periodeLabel }}</p>
    </div>

    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-100/80 dark:bg-slate-900/40 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wide">Document PDF</h2>
            <div class="flex flex-wrap items-center gap-2">
                <x-table-action :href="route('suivi-factures-fournisseurs.index', $queryRetour)" variant="secondary">← Retour à la liste</x-table-action>
                <a href="data:application/pdf;base64,{{ base64_encode($content) }}"
                   download="factures-fournisseurs.pdf"
                   class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-emerald-600/50 text-emerald-700 dark:text-emerald-300 text-[11px] font-semibold no-underline hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-colors">
                    Télécharger
                </a>
            </div>
        </div>
        <div class="p-3 sm:p-4">
            <div class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 overflow-hidden shadow-inner">
                <iframe
                    src="data:application/pdf;base64,{{ base64_encode($content) }}#toolbar=1&navpanes=0&scrollbar=1&view=FitH"
                    class="block w-full border-0 bg-slate-200 dark:bg-slate-950"
                    style="height: calc(100vh - 280px); min-height: 640px;"
                    title="Factures fournisseurs et Prestataires PDF"
                ></iframe>
            </div>
        </div>
    </div>
</div>
@endsection
