@extends('layouts.app')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Régularisation factures')
@section('page-title-info', 'Hors circuit — stock historique payé / impayé')

@section('btn-create')
    <a href="{{ route('factures-regularisation.create') }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 shadow-sm transition-all no-underline">
        Nouvelle régularisation
    </a>
@endsection

@section('content')
@include('partials.flash-session', ['class' => 'mb-4'])

<div class="space-y-4">
    <div class="rounded-xl border border-sky-200 dark:border-sky-800 bg-sky-50/70 dark:bg-sky-900/20 px-4 py-3 text-sm text-sky-900 dark:text-sky-100">
        <p class="font-semibold">Rattrapage hors circuit</p>
        <p class="text-xs mt-1 leading-snug text-sky-800/90 dark:text-sky-200/90">
            Enregistrez les factures déjà traitées hors COSUD (payées ou encore dues) <strong>sans démarrer</strong> le circuit DG → AC → signature.
            Les montants alimentent le cumul de dette fournisseur et les moratoires.
        </p>
    </div>

    <form method="get" action="{{ route('factures-regularisation.index') }}" class="flex flex-wrap items-end gap-3 p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
        <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Paiement</label>
            <select name="paiement" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-900 min-w-[160px]">
                <option value="">Tous</option>
                <option value="impayee" @selected(request('paiement') === 'impayee')>Impayées</option>
                <option value="payee" @selected(request('paiement') === 'payee')>Déjà payées</option>
            </select>
        </div>
        <div class="flex-1 min-w-[220px]">
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Recherche</label>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Fournisseur, objet, référence…"
                   class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-900">
        </div>
        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-800 dark:bg-emerald-600 text-white text-sm font-semibold hover:bg-slate-900 dark:hover:bg-emerald-700">
            Filtrer
        </button>
        @if(request()->filled('q') || request()->filled('paiement'))
        <a href="{{ route('factures-regularisation.index') }}" class="px-3 py-2 text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 no-underline">Réinitialiser</a>
        @endif
    </form>

    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-700/30">
            <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wide">Factures en régularisation</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $lignes->total() }} enregistrement(s)</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-100 dark:bg-slate-900/60 text-slate-700 dark:text-slate-200">
                    <tr>
                        <th class="px-3 py-2 text-left font-bold whitespace-nowrap">N°</th>
                        <th class="px-3 py-2 text-left font-bold">Fournisseur</th>
                        <th class="px-3 py-2 text-left font-bold">Objet</th>
                        <th class="px-3 py-2 text-right font-bold whitespace-nowrap">Montant</th>
                        <th class="px-3 py-2 text-left font-bold">Statut</th>
                        <th class="px-3 py-2 text-left font-bold">Saisi par</th>
                        <th class="px-3 py-2 text-left font-bold whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($lignes as $c)
                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/20 {{ $loop->even ? 'bg-emerald-50/30 dark:bg-emerald-900/5' : '' }}">
                        <td class="px-3 py-2 font-semibold whitespace-nowrap text-slate-800 dark:text-slate-100">{{ $c->numeroRegistreComplet() }}</td>
                        <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ $c->expediteur_libelle }}</td>
                        <td class="px-3 py-2 max-w-[220px] leading-snug text-slate-700 dark:text-slate-200">{{ $c->objet }}</td>
                        <td class="px-3 py-2 text-right tabular-nums font-semibold whitespace-nowrap text-slate-900 dark:text-slate-100">
                            {{ $c->montant_facture !== null ? number_format((float) $c->montant_facture, 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-2">
                            @if($c->estRegularisationPayee())
                                <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">Payée</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200">Impayée</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $c->createur?->name ?? '—' }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <x-table-action :href="route('courriers.show', $c)">Ouvrir</x-table-action>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">Aucune facture en régularisation.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($lignes->hasPages())
        <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700">{{ $lignes->links() }}</div>
        @endif
    </div>
</div>
@endsection
