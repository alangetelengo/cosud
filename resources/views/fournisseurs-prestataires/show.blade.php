@extends('layouts.app')
@use('App\Support\ReturnUrl')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', $fiche->nom)
@section('page-title-info', $fiche->libelleType().($fiche->actif ? '' : ' — inactif'))

@section('btn-create')
    <div class="flex flex-wrap gap-2">
        @can('update', $fiche)
        <a href="{{ route('fournisseurs-prestataires.edit', ReturnUrl::forRoute($fiche)) }}"
           class="inline-flex items-center px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold no-underline hover:bg-emerald-700">Modifier</a>
        @endcan
        <a href="{{ $retourUrl }}" class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-600 text-sm font-semibold no-underline text-slate-700 dark:text-slate-200">Retour</a>
    </div>
@endsection

@section('content')
@include('partials.flash-session', ['class' => 'mb-4'])

<div class="space-y-4" x-data="{ onglet: 'identite' }">
    <div class="grid sm:grid-cols-4 gap-3">
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 shadow-sm">
            <p class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Factures</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-slate-800 dark:text-slate-100">{{ $synthese['nb_factures'] ?? $factures->count() }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 shadow-sm">
            <p class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Facturé</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-slate-800 dark:text-slate-100">{{ number_format((float) ($synthese['montant_facture'] ?? 0), 0, ',', ' ') }} <span class="text-xs font-semibold text-slate-500">FCFA</span></p>
        </div>
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 shadow-sm">
            <p class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Payé</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format((float) ($synthese['montant_paye'] ?? 0), 0, ',', ' ') }} <span class="text-xs font-semibold">FCFA</span></p>
        </div>
        <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50/80 dark:bg-amber-950/30 p-4 shadow-sm">
            <p class="text-[11px] uppercase tracking-wide text-amber-700/80 dark:text-amber-300 font-semibold">Dette</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-amber-900 dark:text-amber-100">{{ number_format((float) ($synthese['dette'] ?? 0), 0, ',', ' ') }} <span class="text-xs font-semibold">FCFA</span></p>
        </div>
    </div>

    <div class="inline-flex flex-wrap p-1 rounded-xl bg-slate-100 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 gap-1">
        <button type="button" @click="onglet = 'identite'" :class="onglet === 'identite' ? 'bg-white dark:bg-slate-700 shadow-sm text-emerald-700 dark:text-emerald-300' : 'text-slate-600 dark:text-slate-300'" class="px-4 py-2 rounded-lg text-sm font-semibold">Identité</button>
        <button type="button" @click="onglet = 'factures'" :class="onglet === 'factures' ? 'bg-white dark:bg-slate-700 shadow-sm text-emerald-700 dark:text-emerald-300' : 'text-slate-600 dark:text-slate-300'" class="px-4 py-2 rounded-lg text-sm font-semibold">Factures ({{ $factures->count() }})</button>
        <button type="button" @click="onglet = 'moratoires'" :class="onglet === 'moratoires' ? 'bg-white dark:bg-slate-700 shadow-sm text-emerald-700 dark:text-emerald-300' : 'text-slate-600 dark:text-slate-300'" class="px-4 py-2 rounded-lg text-sm font-semibold">Moratoires ({{ $moratoires->count() }})</button>
    </div>

    <div x-show="onglet === 'identite'" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm p-5">
        <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div>
                <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Type</dt>
                <dd class="mt-0.5 font-medium">{{ $fiche->libelleType() }}</dd>
            </div>
            <div>
                <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Type de contrats</dt>
                <dd class="mt-0.5">{{ $fiche->type_contrat ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Contrat</dt>
                <dd class="mt-0.5 font-semibold {{ $fiche->a_contrat ? 'text-emerald-700' : 'text-rose-700' }}">{{ $fiche->libelleContratCourt() }}</dd>
                @if($fiche->aScanContrat())
                    <ul class="mt-1 space-y-0.5 text-xs font-normal">
                        @foreach($fiche->piecesContrat() as $i => $piece)
                            <li>
                                <a href="{{ route('fournisseurs-prestataires.pieces.show', [$fiche, 'contrat', $i]) }}" target="_blank" class="text-emerald-700 dark:text-emerald-300 no-underline hover:underline">{{ $piece['nom'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div>
                <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Dossier fiscal</dt>
                <dd class="mt-0.5 font-semibold {{ $fiche->a_dossier_fiscal ? 'text-emerald-700' : 'text-rose-700' }}">{{ $fiche->libelleDossierFiscalCourt() }}</dd>
                @if($fiche->aScanFiscal())
                    <ul class="mt-1 space-y-0.5 text-xs font-normal">
                        @foreach($fiche->piecesFiscal() as $i => $piece)
                            <li>
                                <a href="{{ route('fournisseurs-prestataires.pieces.show', [$fiche, 'fiscal', $i]) }}" target="_blank" class="text-sky-700 dark:text-sky-300 no-underline hover:underline">{{ $piece['nom'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div>
                <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">E-mail</dt>
                <dd class="mt-0.5">{{ $fiche->email ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Téléphone</dt>
                <dd class="mt-0.5">{{ $fiche->telephone ?: '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Observation</dt>
                <dd class="mt-0.5">{{ $fiche->observation ?: '—' }}</dd>
            </div>
            @if($fiche->dossier)
            <div class="sm:col-span-2">
                <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Dossier COSUD</dt>
                <dd class="mt-0.5">
                    <a href="{{ route('dossiers.show', $fiche->dossier) }}" class="text-emerald-600 font-semibold no-underline hover:underline">{{ $fiche->dossier->nom }}</a>
                </dd>
            </div>
            @endif
        </dl>

        @can('delete', $fiche)
        @if($fiche->actif)
        <form method="post" action="{{ route('fournisseurs-prestataires.destroy', $fiche) }}" class="mt-5 pt-4 border-t border-slate-100 dark:border-slate-700">
            @csrf
            @method('DELETE')
            <button type="button"
                    onclick="flashAlert('Désactiver cette fiche ? Elle restera consultable dans l’historique.', this.closest('form'), {icon:'🚫', danger:true, confirmText:'Désactiver'})"
                    class="px-4 py-2 rounded-lg border border-red-300 text-red-700 text-sm font-semibold hover:bg-red-50">
                Désactiver la fiche
            </button>
        </form>
        @endif
        @endcan
    </div>

    <div x-show="onglet === 'factures'" x-cloak class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wider text-slate-500 border-b bg-slate-50/80 dark:bg-slate-900/40">
                        <th class="px-4 py-3 font-semibold">N°</th>
                        <th class="px-4 py-3 font-semibold">Objet</th>
                        <th class="px-4 py-3 font-semibold">Montant</th>
                        <th class="px-4 py-3 font-semibold">Statut</th>
                        <th class="px-4 py-3 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($factures as $facture)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs">{{ $facture->numeroRegistreComplet() }}</td>
                        <td class="px-4 py-3 max-w-md"><span class="line-clamp-2">{{ $facture->objet }}</span></td>
                        <td class="px-4 py-3 tabular-nums">{{ $facture->montant_facture !== null ? number_format((float) $facture->montant_facture, 0, ',', ' ').' FCFA' : '—' }}</td>
                        <td class="px-4 py-3">{{ $facture->statutCourrier?->libelle ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('courriers.show', $facture) }}" class="text-sky-600 text-xs font-semibold no-underline hover:underline">Ouvrir</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-slate-500">Aucune facture rattachée pour le moment.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="onglet === 'moratoires'" x-cloak class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wider text-slate-500 border-b bg-slate-50/80 dark:bg-slate-900/40">
                        <th class="px-4 py-3 font-semibold">Dette initiale</th>
                        <th class="px-4 py-3 font-semibold">Échéances</th>
                        <th class="px-4 py-3 font-semibold">Statut</th>
                        <th class="px-4 py-3 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($moratoires as $moratoire)
                    <tr>
                        <td class="px-4 py-3 tabular-nums font-semibold">{{ number_format((float) $moratoire->montant_dette_initial, 0, ',', ' ') }} FCFA</td>
                        <td class="px-4 py-3">{{ $moratoire->echeances_count }}</td>
                        <td class="px-4 py-3">{{ $moratoire->libelleStatut() }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('moratoires.show', $moratoire) }}" class="text-sky-600 text-xs font-semibold no-underline hover:underline">Ouvrir</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-slate-500">Aucun plan de paiement progressif.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(($synthese['dette'] ?? 0) > 0 && empty($synthese['moratoire_actif_id']) && auth()->user()?->can('create', App\Models\Moratoire::class))
        <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700 bg-amber-50/50 dark:bg-amber-950/20">
            <a href="{{ route('moratoires.create', ['fournisseur' => $fiche->nom]) }}"
               class="inline-flex text-sm font-semibold text-amber-900 dark:text-amber-100 no-underline hover:underline">
                Créer un moratoire pour cette dette →
            </a>
        </div>
        @endif
    </div>
</div>
@endsection
