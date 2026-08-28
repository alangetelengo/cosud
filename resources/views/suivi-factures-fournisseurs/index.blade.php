@extends('layouts.app')

@use('App\Support\ReturnUrl')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Suivi factures fournisseurs et Prestataires')
@section('page-title-info', $periodeLabel.' — '.$lignes->count().' facture(s)')

@section('btn-create')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('suivi-factures-fournisseurs.export', array_merge(request()->query(), ['periode' => 'semaine'])) }}"
           class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border-2 border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 shadow-sm transition-all no-underline">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            CSV semaine
        </a>
        <a href="{{ route('suivi-factures-fournisseurs.export', array_merge(request()->query(), ['periode' => 'mois', 'mois' => $mois])) }}"
           class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border-2 border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 shadow-sm transition-all no-underline">
            CSV mensuel
        </a>
        <a href="{{ route('suivi-factures-fournisseurs.export', array_merge(request()->query(), ['periode' => 'annee', 'annee' => $annee])) }}"
           class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border-2 border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 shadow-sm transition-all no-underline">
            CSV annuel
        </a>
        <a href="{{ route('suivi-factures-fournisseurs.print', request()->query()) }}"
           class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border-2 border-emerald-600 text-emerald-800 dark:text-emerald-200 text-sm font-semibold hover:bg-emerald-50 dark:hover:bg-emerald-900/30 shadow-sm transition-all no-underline">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-4 0v4m0-4H8v4"/></svg>
            Imprimer
        </a>
    </div>
@endsection

@section('content')
<div class="space-y-4">
    <div class="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50/70 dark:bg-emerald-900/20 px-4 py-3 text-sm text-emerald-900 dark:text-emerald-100">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="font-semibold">Espace responsable dossiers fournisseurs et prestataires</p>
                <p class="text-xs mt-1 text-emerald-800/90 dark:text-emerald-200/90 leading-snug">
                    Factures ayant reçu le <strong>Bon pour accord</strong> du DG.
                    Suivez l’état du paiement (montant facture, payé, reliquat), classez les dossiers, et exportez les rapports ou imprimez l’état.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 flex-shrink-0">
                @canany(['factures-regularisation.view', 'factures-regularisation.create'])
                <a href="{{ route('factures-regularisation.index') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border-2 border-emerald-700/70 bg-white/80 dark:bg-emerald-950/40 text-emerald-900 dark:text-emerald-100 text-xs font-semibold no-underline hover:bg-emerald-100 dark:hover:bg-emerald-900/50 shadow-sm transition-colors">
                    <span aria-hidden="true">🗂️</span>
                    Régularisation hors circuit
                </a>
                @endcanany
                @can('moratoires.view')
                <a href="{{ route('moratoires.index') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border-2 border-amber-700/60 bg-white/80 dark:bg-amber-950/30 text-amber-950 dark:text-amber-100 text-xs font-semibold no-underline hover:bg-amber-50 dark:hover:bg-amber-900/40 shadow-sm transition-colors">
                    <span aria-hidden="true">📅</span>
                    Dettes &amp; moratoires
                </a>
                @endcan
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('suivi-factures-fournisseurs.index', array_merge(request()->except(['periode', 'mois', 'annee']), ['periode' => 'tous'])) }}"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold no-underline transition-colors {{ ($periode ?? 'tous') === 'tous' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50' }}">
            Toutes
        </a>
        <a href="{{ route('suivi-factures-fournisseurs.index', array_merge(request()->except(['annee', 'mois']), ['periode' => 'semaine'])) }}"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold no-underline transition-colors {{ ($periode ?? '') === 'semaine' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50' }}">
            Semaine en cours
        </a>
        <a href="{{ route('suivi-factures-fournisseurs.index', array_merge(request()->except(['annee']), ['periode' => 'mois', 'mois' => $mois])) }}"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold no-underline transition-colors {{ ($periode ?? '') === 'mois' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50' }}">
            Mois en cours
        </a>
        <a href="{{ route('suivi-factures-fournisseurs.index', array_merge(request()->except(['mois']), ['periode' => 'annee', 'annee' => $annee])) }}"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold no-underline transition-colors {{ ($periode ?? '') === 'annee' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50' }}">
            Année
        </a>
    </div>

    <form method="get" action="{{ route('suivi-factures-fournisseurs.index') }}" class="flex flex-wrap items-end gap-3 p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
        <input type="hidden" name="periode" value="{{ $periode }}">
        @if(($periode ?? '') === 'mois')
        <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Mois BPA</label>
            <input type="month" name="mois" value="{{ $mois }}" class="rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
        </div>
        @elseif(($periode ?? '') === 'annee')
        <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Année BPA</label>
            <select name="annee" class="rounded-lg border px-3 py-2 text-sm dark:bg-slate-900 min-w-[100px]">
                @for($a = now()->year; $a >= now()->year - 3; $a--)
                    <option value="{{ $a }}" @selected((int) $a === (int) $annee)>{{ $a }}</option>
                @endfor
            </select>
        </div>
        @endif
        <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Statut paiement</label>
            <select name="statut" class="rounded-lg border px-3 py-2 text-sm dark:bg-slate-900 min-w-[180px]">
                <option value="tous" @selected(request('statut', 'tous') === 'tous')>Tous</option>
                @foreach($statuts as $code => $libelle)
                    <option value="{{ $code }}" @selected(request('statut') === $code)>{{ $libelle }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[220px]">
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Recherche</label>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Fournisseur, objet, référence…"
                   class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
        </div>
        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-800 text-white text-sm font-semibold hover:bg-slate-900">
            Filtrer
        </button>
        @if(request()->filled('q') || (request()->filled('statut') && request('statut') !== 'tous'))
        <a href="{{ route('suivi-factures-fournisseurs.index', array_filter(['periode' => $periode, 'annee' => $periode === 'annee' ? $annee : null, 'mois' => $periode === 'mois' ? $mois : null])) }}"
           class="px-3 py-2 text-sm text-slate-500 hover:text-slate-700 no-underline">Réinitialiser</a>
        @endif
    </form>

    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-700/30">
            <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wide">Factures fournisseurs et Prestataires</h2>
            <p class="text-xs text-slate-500 mt-0.5">{{ $periodeLabel }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-100 dark:bg-slate-900/60 text-slate-700 dark:text-slate-200">
                    <tr>
                        <th class="px-3 py-2 text-left font-bold whitespace-nowrap">N°</th>
                        <th class="px-3 py-2 text-left font-bold whitespace-nowrap">Date BPA</th>
                        <th class="px-3 py-2 text-left font-bold">Fournisseur</th>
                        <th class="px-3 py-2 text-left font-bold">Objet</th>
                        <th class="px-3 py-2 text-right font-bold whitespace-nowrap">Montant facture</th>
                        <th class="px-3 py-2 text-right font-bold whitespace-nowrap">Payé</th>
                        <th class="px-3 py-2 text-right font-bold whitespace-nowrap">Reliquat</th>
                        <th class="px-3 py-2 text-left font-bold">Statut paiement</th>
                        <th class="px-3 py-2 text-left font-bold">Étape</th>
                        <th class="px-3 py-2 text-left font-bold">Service demandeur</th>
                        <th class="px-3 py-2 text-left font-bold">Classement</th>
                        <th class="px-3 py-2 text-left font-bold whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($lignes as $ligne)
                        @php
                            $c = $ligne['courrier'];
                            $badge = match($ligne['statut']) {
                                'cloture' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
                                'reliquat' => 'bg-orange-100 text-orange-900 dark:bg-orange-900/40 dark:text-orange-200',
                                'controle' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
                                'decharge' => 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200',
                                default => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/20 {{ $loop->even ? 'bg-emerald-50/30 dark:bg-emerald-900/5' : '' }}">
                            <td class="px-3 py-2 font-semibold whitespace-nowrap">{{ $c->numeroRegistreComplet() }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $c->date_orientation?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $c->expediteur_libelle ?? '—' }}</td>
                            <td class="px-3 py-2 leading-snug max-w-[220px]">{{ $c->objet }}</td>
                            <td class="px-3 py-2 text-right font-semibold tabular-nums whitespace-nowrap">
                                {{ $service->formaterMontant($ligne['montant_facture']) }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap text-slate-700 dark:text-slate-200">
                                {{ $ligne['montant_paye'] > 0 ? $service->formaterMontant($ligne['montant_paye']) : '—' }}
                            </td>
                            <td class="px-3 py-2 text-right font-semibold tabular-nums whitespace-nowrap {{ $ligne['reliquat'] > 0 ? 'text-orange-700 dark:text-orange-300' : 'text-slate-500' }}">
                                {{ $service->formaterMontant($ligne['reliquat']) }}
                            </td>
                            <td class="px-3 py-2">
                                <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold {{ $badge }}">
                                    {{ $ligne['libelle_statut'] }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $c->circuitEtapeActuelle?->nom ?? 'Terminé' }}</td>
                            <td class="px-3 py-2">{{ $c->serviceDemandeurStructure?->nom ?? '—' }}</td>
                            <td class="px-3 py-2">
                                @if($c->dossier_id)
                                    <x-table-action :href="route('dossiers.show', $c->dossier_id)" :title="$c->dossier?->chemin_complet">Classée</x-table-action>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200">À classer</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <div class="inline-flex flex-wrap items-center gap-1.5">
                                    <x-table-action :href="route('courriers.show', ReturnUrl::forRoute($c))">Ouvrir</x-table-action>
                                    @if(! $c->dossier_id)
                                        <x-table-action :href="route('courriers.show', ReturnUrl::forRoute(['courrier' => $c, 'classer' => 1]))">Classer</x-table-action>
                                    @elseif($c->dossier_id)
                                        <x-table-action :href="route('dossiers.show', $c->dossier_id)" variant="sky">Dossier</x-table-action>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="px-4 py-12 text-center text-sm text-slate-500">
                                Aucune facture avec Bon pour accord pour cette période.
                                <span class="block text-xs mt-1">Les factures apparaissent ici dès que le DG a donné ses instructions / BPA.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
