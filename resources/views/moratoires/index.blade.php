@extends('layouts.app')

@use('App\Support\ReturnUrl')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Moratoires / paiements progressifs')
@section('page-title-info', $moratoires->total().' plan(s)')

@section('btn-create')
    @can('create', App\Models\Moratoire::class)
    <a href="{{ route('moratoires.create', ReturnUrl::forRoute()) }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 shadow-sm transition-all no-underline">
        Nouveau moratoire
    </a>
    @endcan
@endsection

@section('content')
@include('partials.flash-session', ['class' => 'mb-4'])

@php
    $peutVoirPlans = $peutVoirPlans ?? auth()->user()?->can('create', App\Models\Moratoire::class);
    $panneauInitial = ($peutVoirPlans && ($filtresPlans['q'] !== '' || $filtresPlans['statut'] !== ''))
        ? 'plans'
        : 'dettes';
@endphp

<div
    class="space-y-4"
    x-data="{ panneau: @js($panneauInitial) }"
>
    <div class="flex flex-wrap gap-2" role="tablist" aria-label="Sections moratoires">
        <button
            type="button"
            role="tab"
            @click="panneau = 'dettes'"
            :aria-selected="panneau === 'dettes'"
            class="group inline-flex flex-1 sm:flex-none items-center justify-center gap-2 min-h-[2.75rem] px-4 py-2.5 rounded-xl border-2 border-amber-700 text-sm font-bold transition-colors bg-amber-50 text-amber-950 hover:bg-amber-100 aria-selected:bg-amber-700 aria-selected:text-white aria-selected:hover:bg-amber-800 dark:bg-amber-950/40 dark:text-amber-50 dark:border-amber-500 dark:aria-selected:bg-amber-600 dark:aria-selected:text-white"
        >
            Dettes fournisseurs
            <span class="inline-flex items-center justify-center min-w-[1.5rem] px-1.5 py-0.5 rounded-md text-[11px] font-bold bg-amber-200 text-amber-950 group-aria-selected:bg-white/25 group-aria-selected:text-white">({{ $dettes->count() }})</span>
        </button>
        @if($peutVoirPlans)
        <button
            type="button"
            role="tab"
            @click="panneau = 'plans'"
            :aria-selected="panneau === 'plans'"
            class="group inline-flex flex-1 sm:flex-none items-center justify-center gap-2 min-h-[2.75rem] px-4 py-2.5 rounded-xl border-2 border-emerald-700 text-sm font-bold transition-colors bg-emerald-50 text-emerald-950 hover:bg-emerald-100 aria-selected:bg-emerald-600 aria-selected:text-white aria-selected:border-emerald-600 aria-selected:hover:bg-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-50 dark:border-emerald-500 dark:aria-selected:bg-emerald-600 dark:aria-selected:text-white"
        >
            Plans de paiement progressif
            <span class="inline-flex items-center justify-center min-w-[1.5rem] px-1.5 py-0.5 rounded-md text-[11px] font-bold bg-emerald-200 text-emerald-950 group-aria-selected:bg-white/25 group-aria-selected:text-white">({{ $moratoires->total() }})</span>
        </button>
        @endif
    </div>

    <div x-show="panneau === 'dettes'" x-cloak role="tabpanel" class="rounded-xl border border-amber-200 dark:border-amber-800 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-amber-100 dark:border-amber-900/40 bg-amber-50/80 dark:bg-amber-900/20 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-amber-900 dark:text-amber-100 uppercase tracking-wide">Dettes fournisseurs</h2>
                <p class="text-xs text-amber-800/90 dark:text-amber-200/80 mt-0.5">Factures − paiements (chèques déchargés / échéances renseignées) — {{ $dettes->count() }} ligne(s)</p>
            </div>
            <a href="{{ route('moratoires.print-dettes', array_filter(['dette_q' => $filtresDettes['q'] ?: null, 'dette_solde' => $filtresDettes['solde']])) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-amber-700/40 text-amber-900 dark:text-amber-100 text-xs font-semibold no-underline hover:bg-amber-100/80 dark:hover:bg-amber-900/40 transition-colors">
                Imprimer
            </a>
        </div>
        <form method="get" action="{{ route('moratoires.index') }}" class="px-4 py-3 border-b border-amber-100 dark:border-amber-900/40 bg-white dark:bg-slate-800 flex flex-wrap items-end gap-3">
            @if($filtresPlans['q'] !== '')
                <input type="hidden" name="plan_q" value="{{ $filtresPlans['q'] }}">
            @endif
            @if($filtresPlans['statut'] !== '')
                <input type="hidden" name="plan_statut" value="{{ $filtresPlans['statut'] }}">
            @endif
            <div class="min-w-[12rem] flex-1">
                <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-300 mb-1">Fournisseur</label>
                <input type="text" name="dette_q" value="{{ $filtresDettes['q'] }}" placeholder="Rechercher…"
                       class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-xs dark:bg-slate-900">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-300 mb-1">Solde</label>
                <select name="dette_solde" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-xs dark:bg-slate-900">
                    <option value="oui" @selected($filtresDettes['solde'] === 'oui')>En cours (&gt; 0)</option>
                    <option value="non" @selected($filtresDettes['solde'] === 'non')>Soldées (= 0)</option>
                    <option value="tous" @selected($filtresDettes['solde'] === 'tous')>Toutes</option>
                </select>
            </div>
            <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-amber-700 text-white text-xs font-semibold hover:bg-amber-800 transition-colors">
                Filtrer
            </button>
            @if($filtresDettes['q'] !== '' || $filtresDettes['solde'] !== 'oui')
            <a href="{{ route('moratoires.index', array_filter(['plan_q' => $filtresPlans['q'] ?: null, 'plan_statut' => $filtresPlans['statut'] ?: null])) }}"
               class="inline-flex items-center px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 text-xs font-semibold text-slate-600 dark:text-slate-300 no-underline hover:bg-slate-50 dark:hover:bg-slate-700">
                Réinitialiser
            </a>
            @endif
        </form>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-100 dark:bg-slate-900/60 text-slate-700 dark:text-slate-200">
                    <tr>
                        <th class="px-3 py-2 text-left font-bold">Fournisseur</th>
                        <th class="px-3 py-2 text-right font-bold whitespace-nowrap">Nb fact.</th>
                        <th class="px-3 py-2 text-right font-bold whitespace-nowrap">Facturé</th>
                        <th class="px-3 py-2 text-right font-bold whitespace-nowrap">Payé</th>
                        <th class="px-3 py-2 text-right font-bold whitespace-nowrap">Dette</th>
                        <th class="px-3 py-2 text-left font-bold whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($dettes as $dette)
                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/20 {{ $loop->even ? 'bg-emerald-50/30 dark:bg-emerald-900/5' : '' }}">
                        <td class="px-3 py-2 font-semibold text-slate-800 dark:text-slate-100">{{ $dette['fournisseur_libelle'] }}</td>
                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">
                            <a href="{{ route('moratoires.dettes.detail', ReturnUrl::forRoute(['fournisseur' => $dette['fournisseur_libelle']])) }}"
                               class="font-bold text-sky-700 dark:text-sky-300 underline decoration-sky-400/60 hover:decoration-sky-600"
                               title="Voir le détail des factures">
                                {{ $dette['nb_factures'] }}
                            </a>
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap text-slate-700 dark:text-slate-200">{{ number_format($dette['montant_facture'], 0, ',', ' ') }}</td>
                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap text-slate-700 dark:text-slate-200">{{ number_format($dette['montant_paye'], 0, ',', ' ') }}</td>
                        <td class="px-3 py-2 text-right tabular-nums font-bold whitespace-nowrap text-slate-900 dark:text-slate-100">{{ number_format($dette['dette'], 0, ',', ' ') }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <div class="inline-flex flex-wrap items-center gap-1.5">
                            <x-table-action :href="route('moratoires.dettes.detail', ReturnUrl::forRoute(['fournisseur' => $dette['fournisseur_libelle']]))" variant="sky">
                                Détail
                            </x-table-action>
                            @if($peutVoirPlans && $dette['moratoire_actif_id'])
                                <x-table-action :href="route('moratoires.show', ReturnUrl::forRoute($dette['moratoire_actif_id']))">Voir plan</x-table-action>
                            @elseif($dette['dette'] > 0)
                                @can('create', App\Models\Moratoire::class)
                                <x-table-action :href="route('moratoires.create', ReturnUrl::forRoute(['fournisseur' => $dette['fournisseur_libelle']]))">Créer plan</x-table-action>
                                @endcan
                            @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">Aucune dette ne correspond aux filtres.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($peutVoirPlans)
    <div x-show="panneau === 'plans'" x-cloak role="tabpanel" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-700/30 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wide">Plans de paiement progressif</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $moratoires->total() }} plan(s)</p>
            </div>
            <a href="{{ route('moratoires.print-plans', array_filter(['plan_q' => $filtresPlans['q'] ?: null, 'plan_statut' => $filtresPlans['statut'] ?: null])) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-emerald-600/50 text-emerald-700 dark:text-emerald-300 text-xs font-semibold no-underline hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-colors">
                Imprimer
            </a>
        </div>
        <form method="get" action="{{ route('moratoires.index') }}" class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex flex-wrap items-end gap-3">
            @if($filtresDettes['q'] !== '')
                <input type="hidden" name="dette_q" value="{{ $filtresDettes['q'] }}">
            @endif
            @if($filtresDettes['solde'] !== 'oui')
                <input type="hidden" name="dette_solde" value="{{ $filtresDettes['solde'] }}">
            @endif
            <div class="min-w-[12rem] flex-1">
                <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-300 mb-1">Fournisseur</label>
                <input type="text" name="plan_q" value="{{ $filtresPlans['q'] }}" placeholder="Rechercher…"
                       class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-xs dark:bg-slate-900">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-300 mb-1">Statut</label>
                <select name="plan_statut" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-xs dark:bg-slate-900">
                    <option value="" @selected($filtresPlans['statut'] === '')>Tous</option>
                    <option value="actif" @selected($filtresPlans['statut'] === 'actif')>Actif</option>
                    <option value="solde" @selected($filtresPlans['statut'] === 'solde')>Soldé</option>
                    <option value="annule" @selected($filtresPlans['statut'] === 'annule')>Annulé</option>
                </select>
            </div>
            <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 transition-colors">
                Filtrer
            </button>
            @if($filtresPlans['q'] !== '' || $filtresPlans['statut'] !== '')
            <a href="{{ route('moratoires.index', array_filter(['dette_q' => $filtresDettes['q'] ?: null, 'dette_solde' => $filtresDettes['solde'] !== 'oui' ? $filtresDettes['solde'] : null])) }}"
               class="inline-flex items-center px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 text-xs font-semibold text-slate-600 dark:text-slate-300 no-underline hover:bg-slate-50 dark:hover:bg-slate-700">
                Réinitialiser
            </a>
            @endif
        </form>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-100 dark:bg-slate-900/60 text-slate-700 dark:text-slate-200">
                    <tr>
                        <th class="px-3 py-2 text-left font-bold">Fournisseur</th>
                        <th class="px-3 py-2 text-right font-bold whitespace-nowrap">Dette initiale</th>
                        <th class="px-3 py-2 text-right font-bold whitespace-nowrap">Échéance</th>
                        <th class="px-3 py-2 text-center font-bold whitespace-nowrap">Lignes</th>
                        <th class="px-3 py-2 text-left font-bold">Statut</th>
                        <th class="px-3 py-2 text-left font-bold">Saisie par</th>
                        <th class="px-3 py-2 text-left font-bold whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($moratoires as $m)
                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/20 {{ $loop->even ? 'bg-emerald-50/30 dark:bg-emerald-900/5' : '' }}">
                        <td class="px-3 py-2 font-semibold text-slate-800 dark:text-slate-100">{{ $m->fournisseur_libelle }}</td>
                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap text-slate-700 dark:text-slate-200">{{ number_format((float) $m->montant_dette_initial, 0, ',', ' ') }}</td>
                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap text-slate-700 dark:text-slate-200">{{ number_format((float) $m->montant_echeance_defaut, 0, ',', ' ') }}</td>
                        <td class="px-3 py-2 text-center text-slate-700 dark:text-slate-200">{{ $m->echeances_count }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold {{ $m->statut === 'solde' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' : ($m->statut === 'annule' ? 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' : 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200') }}">
                                {{ $m->libelleStatut() }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $m->createur?->name ?? '—' }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <div class="inline-flex flex-wrap items-center gap-1.5">
                                <x-table-action :href="route('moratoires.show', ReturnUrl::forRoute($m))">Ouvrir</x-table-action>
                                <x-table-action :href="route('moratoires.print', $m)" variant="secondary">PDF</x-table-action>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">Aucun plan ne correspond aux filtres.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($moratoires->hasPages())
        <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700">{{ $moratoires->links() }}</div>
        @endif
    </div>
    @endif
</div>
@endsection
