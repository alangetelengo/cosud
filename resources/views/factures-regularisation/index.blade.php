@extends('layouts.app')

@use('App\Support\ReturnUrl')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Régularisation factures')
@section('page-title-info', 'Hors circuit — impayées / programmées / payées')

@section('btn-create')
    @can('factures-regularisation.create')
    <a href="{{ route('factures-regularisation.create', ReturnUrl::forRoute()) }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 shadow-sm transition-all no-underline">
        Nouvelle régularisation
    </a>
    @endcan
@endsection

@section('content')
@include('partials.flash-session', ['class' => 'mb-4'])

@php
    $fournisseursMoratoireActif = $fournisseursMoratoireActif ?? [];
    $normaliserFournisseur = app(\App\Services\FournisseurDetteService::class);
@endphp

<div class="space-y-4">
    <div class="rounded-xl border border-sky-200 dark:border-sky-800 bg-sky-50/70 dark:bg-sky-900/20 px-4 py-3 text-sm text-sky-900 dark:text-sky-100">
        <p class="font-semibold">Rattrapage hors circuit</p>
        <p class="text-xs mt-1 leading-snug text-sky-800/90 dark:text-sky-200/90">
            Mme Taty enregistre les factures <strong>impayées</strong>, <strong>programmées</strong> ou les <strong>contrats mensuels</strong>.
            Mme Eleni enregistre le paiement effectif des factures programmées et les échéances de moratoire.
        </p>
    </div>

    <form method="get" action="{{ route('factures-regularisation.index') }}" class="flex flex-wrap items-end gap-3 p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
        <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Paiement</label>
            <select name="paiement" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-900 min-w-[160px]">
                <option value="">Tous</option>
                <option value="impayee" @selected(request('paiement') === 'impayee')>Impayées</option>
                <option value="programmee" @selected(request('paiement') === 'programmee')>Programmées</option>
                <option value="contrat_mensuel" @selected(request('paiement') === 'contrat_mensuel')>Contrats mensuels</option>
                <option value="payee" @selected(request('paiement') === 'payee')>Payées</option>
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
                        <th class="px-3 py-2 text-left font-bold">Mode</th>
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
                            @if($c->estRegularisationContratMensuel() && $c->regularisation_montant_mensuel)
                                <p class="text-[10px] font-normal text-slate-500 dark:text-slate-400">
                                    {{ number_format((float) $c->regularisation_montant_mensuel, 0, ',', ' ') }} × {{ $c->regularisation_nb_mois_impayes }} mois
                                </p>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            @if($c->estRegularisationPayee())
                                <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">Payée</span>
                            @elseif($c->estRegularisationProgrammee())
                                <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-200">Programmée</span>
                            @elseif($c->estRegularisationContratMensuel())
                                <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold bg-violet-100 text-violet-900 dark:bg-violet-900/40 dark:text-violet-200">Contrat mensuel</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200">Impayée</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                            {{ \App\Services\FactureRegularisationService::libelleModePaiement($c->regularisation_mode_paiement) }}
                        </td>
                        <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $c->createur?->name ?? '—' }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            @php
                                $cleFournisseur = $normaliserFournisseur->normaliserLibelle($c->expediteur_libelle);
                                $sousMoratoire = isset($fournisseursMoratoireActif[$cleFournisseur]);
                            @endphp
                            <div class="flex flex-wrap items-center gap-2">
                                <x-table-action :href="route('courriers.show', ReturnUrl::forRoute($c))">Ouvrir</x-table-action>
                                @can('factures-regularisation.create')
                                    @if($c->regularisationModifiable() && ! $sousMoratoire)
                                        <x-table-action :href="route('factures-regularisation.edit', ReturnUrl::forRoute($c))" variant="sky">
                                            Modifier
                                        </x-table-action>
                                        <form method="post" action="{{ route('factures-regularisation.destroy', $c) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button"
                                                    onclick="flashAlert('Supprimer définitivement cette régularisation ? Vous pourrez la resaisir ensuite.', this.closest('form'), {icon:'🗑️', danger:true, confirmText:'Supprimer'})"
                                                    class="inline-flex items-center px-2.5 py-1 rounded-lg border border-red-500/50 text-red-700 dark:text-red-300 text-[11px] font-semibold hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors">
                                                Supprimer
                                            </button>
                                        </form>
                                    @elseif($sousMoratoire && $c->regularisationModifiable())
                                        <span class="text-[11px] text-slate-500 dark:text-slate-400">Sous moratoire</span>
                                    @endif
                                @endcan
                                @can('factures-regularisation.payer')
                                    @if($c->estRegularisationProgrammee() && ! $sousMoratoire)
                                        <x-table-action :href="route('factures-regularisation.payer', ReturnUrl::forRoute($c))">
                                            Enregistrer le paiement
                                        </x-table-action>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">Aucune facture en régularisation.</td>
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
