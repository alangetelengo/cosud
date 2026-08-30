@extends('layouts.app')

@use('App\Support\ReturnUrl')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Détail dette — '.$synthese['fournisseur_libelle'])
@section('page-title-info', $synthese['nb_factures'].' facture(s) · Dette '.number_format($synthese['dette'], 0, ',', ' ').' FCFA')

@section('btn-create')
    <a href="{{ $retourUrl }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border-2 border-emerald-600 text-emerald-800 dark:text-emerald-200 text-sm font-semibold hover:bg-emerald-50 dark:hover:bg-emerald-900/30 shadow-sm transition-all no-underline">
        ← Retour aux dettes
    </a>
@endsection

@section('content')
@include('partials.flash-session', ['class' => 'mb-4'])

<div class="space-y-4">
    <div class="rounded-xl border border-sky-200 dark:border-sky-800 bg-sky-50/70 dark:bg-sky-900/20 px-4 py-3 text-sm text-sky-900 dark:text-sky-100">
        <p class="font-semibold">{{ $synthese['fournisseur_libelle'] }}</p>
        <p class="text-xs mt-1 leading-snug">
            Facturé {{ number_format($synthese['montant_facture'], 0, ',', ' ') }} FCFA
            · Payé {{ number_format($synthese['montant_paye'], 0, ',', ' ') }} FCFA
            · Dette <strong>{{ number_format($synthese['dette'], 0, ',', ' ') }} FCFA</strong>
            · {{ $synthese['nb_factures'] }} facture(s) saisies (Taty / circuit / régularisation)
        </p>
        <div class="mt-2 flex flex-wrap gap-2">
            @can('create', App\Models\Moratoire::class)
                @if($synthese['moratoire_actif_id'])
                    <x-table-action :href="route('moratoires.show', $synthese['moratoire_actif_id'])">Voir le plan actif</x-table-action>
                @elseif($synthese['dette'] > 0)
                    <x-table-action :href="route('moratoires.create', ['fournisseur' => $synthese['fournisseur_libelle']])">
                        Créer un plan de paiement
                    </x-table-action>
                @endif
            @endcan
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-700/30">
            <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wide">Factures détaillées</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Vérifiez chaque montant et les pièces jointes</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-100 dark:bg-slate-900/60 text-slate-700 dark:text-slate-200">
                    <tr>
                        <th class="px-3 py-2 text-left font-bold whitespace-nowrap">N°</th>
                        <th class="px-3 py-2 text-left font-bold">Objet</th>
                        <th class="px-3 py-2 text-right font-bold whitespace-nowrap">Montant</th>
                        <th class="px-3 py-2 text-left font-bold">Statut</th>
                        <th class="px-3 py-2 text-left font-bold">Saisi par</th>
                        <th class="px-3 py-2 text-left font-bold">Pièces</th>
                        <th class="px-3 py-2 text-left font-bold whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($factures as $c)
                    @php
                        $montant = $c->montant_facture !== null
                            ? (float) $c->montant_facture
                            : (float) ($c->suiviPaiement?->montant ?? 0);
                    @endphp
                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/20 {{ $loop->even ? 'bg-emerald-50/30 dark:bg-emerald-900/5' : '' }}">
                        <td class="px-3 py-2 font-semibold whitespace-nowrap text-slate-800 dark:text-slate-100">{{ $c->numeroRegistreComplet() }}</td>
                        <td class="px-3 py-2 max-w-[240px] leading-snug text-slate-700 dark:text-slate-200">{{ $c->objet }}</td>
                        <td class="px-3 py-2 text-right tabular-nums font-semibold whitespace-nowrap text-slate-900 dark:text-slate-100">
                            {{ number_format($montant, 0, ',', ' ') }}
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            @if($c->estRegularisation())
                                <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200">
                                    Régul. {{ \App\Services\FactureRegularisationService::libelleStatutPaiement($c->regularisation_paiement) }}
                                </span>
                            @else
                                <span class="text-slate-600 dark:text-slate-300">{{ $c->statutCourrier?->libelle ?? '—' }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $c->createur?->name ?? '—' }}</td>
                        <td class="px-3 py-2">
                            @if($c->documents->isEmpty())
                                <span class="text-slate-400">Aucune</span>
                            @else
                                <ul class="space-y-0.5">
                                    @foreach($c->documents as $doc)
                                        <li>
                                            <a href="{{ route('documents.fiche', ReturnUrl::forRoute($doc)) }}"
                                               class="text-emerald-700 dark:text-emerald-300 font-medium no-underline hover:underline truncate inline-block max-w-[180px]"
                                               title="{{ $doc->nom_original }}">
                                                {{ $doc->nom_original }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <x-table-action :href="route('courriers.show', $c)">Ouvrir</x-table-action>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">Aucune facture pour ce fournisseur.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
