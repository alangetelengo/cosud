@extends('layouts.app')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Suivi de dépense')
@section('page-title-info', 'Année '.$annee.' — '.$lignes->count().' ligne(s)')

@section('btn-create')
    <a href="{{ route('suivi-paiements.export', request()->query()) }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border-2 border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 shadow-sm transition-all no-underline">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        Exporter Excel (CSV)
    </a>
@endsection

@section('content')
@include('partials.flash-session', ['class' => 'mb-4'])

<div class="space-y-4">
    @if($peutSaisirRemiseDg)
    <div class="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50/60 dark:bg-emerald-900/15 p-4"
         x-data="{ ouvert: {{ ($errors->any() && ! $errors->has('date_debut')) ? 'true' : 'true' }} }">
        <button type="button" @click="ouvert = !ouvert" class="w-full flex items-center justify-between text-left">
            <h2 class="text-sm font-bold text-emerald-900 dark:text-emerald-100">Enregistrer une dépense</h2>
            <span class="text-emerald-700 dark:text-emerald-300 text-sm font-semibold" x-text="ouvert ? 'Fermer' : 'Ouvrir'"></span>
        </button>
        <form x-show="ouvert" x-cloak method="post" action="{{ route('suivi-paiements.remise-dg') }}" enctype="multipart/form-data" class="mt-4 grid sm:grid-cols-2 gap-3">
            @csrf
            <div>
                <label class="block text-xs font-semibold mb-1">Catégorie <span class="text-red-500">*</span></label>
                <select name="categorie_depense_id" required class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
                    <option value="">— Choisir —</option>
                    @foreach($categoriesSaisie as $cat)
                        <option value="{{ $cat->id }}" @selected((string) old('categorie_depense_id') === (string) $cat->id)>{{ $cat->libelle }}</option>
                    @endforeach
                </select>
                @error('categorie_depense_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Date <span class="text-red-500">*</span></label>
                <input type="date" name="date_suivi" value="{{ old('date_suivi', now()->toDateString()) }}" required class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
                @error('date_suivi')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold mb-1">Intitulé <span class="text-red-500">*</span></label>
                <input type="text" name="intitule" value="{{ old('intitule') }}" required class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
                @error('intitule')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                @include('partials.input-montant-fcfa', [
                    'name' => 'montant',
                    'label' => 'Montant (FCFA)',
                    'required' => true,
                    'placeholder' => 'Ex. : 185 000',
                    'value' => old('montant'),
                ])
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Bénéficiaire</label>
                <input type="text" name="beneficiaire_libelle" value="{{ old('beneficiaire_libelle') }}" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Ref pièce</label>
                <input type="text" name="numero_piece" value="{{ old('numero_piece') }}" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Instruction / note DG</label>
                <textarea name="instruction_dg" rows="2" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">{{ old('instruction_dg') }}</textarea>
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Observation</label>
                <textarea name="observation" rows="2" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">{{ old('observation') }}</textarea>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold mb-1">Justificatifs (scan)</label>
                <label class="flex flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-slate-200 dark:border-slate-600 bg-white/70 dark:bg-slate-900/40 px-3 py-4 cursor-pointer hover:border-emerald-400 transition-colors">
                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">PDF / JPG / PNG — max. 10 Mo, plusieurs fichiers</span>
                    <input type="file" name="justificatifs[]" accept=".pdf,.jpg,.jpeg,.png" multiple class="sr-only" id="justificatifs-scan"
                           onchange="(function(input){var n=input.files?.length||0;document.getElementById('justificatifs-scan-name').textContent=n===0?'Aucun fichier choisi':(n===1?input.files[0].name:(n+' fichiers sélectionnés'));})(this)">
                </label>
                <p id="justificatifs-scan-name" class="mt-1 text-xs text-slate-500">Aucun fichier choisi</p>
                <p class="mt-1 text-[11px] text-slate-500">Les justificatifs sont déposés en attente : classez ensuite la dépense dans un dossier prestataire / bénéficiaire (comme Mme Taty).</p>
                @error('justificatifs')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                @error('justificatifs.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="inline-flex items-center px-4 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
                    Enregistrer la dépense
                </button>
            </div>
        </form>
    </div>
    @endif

    <div class="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50/70 dark:bg-emerald-900/20 p-4">
        <form method="get" action="{{ route('suivi-paiements.export-hebdomadaire') }}" class="flex flex-wrap items-end gap-3">
            <p class="w-full text-sm font-bold text-emerald-900 dark:text-emerald-100 mb-0">Rapport hebdomadaire</p>
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Du</label>
                <input type="date" name="date_debut_hebdo" value="{{ $dateDebutHebdo }}" required class="rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Au</label>
                <input type="date" name="date_fin_hebdo" value="{{ $dateFinHebdo }}" required class="rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
            </div>
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-semibold shadow-sm hover:bg-emerald-700 border border-emerald-700">
                Exporter le rapport CSV
            </button>
        </form>
    </div>

    <form method="get" action="{{ route('suivi-paiements.index') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
        <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Année</label>
            <select name="annee" class="rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
                @foreach($annees as $a)
                    <option value="{{ $a }}" @selected((int) $annee === (int) $a)>{{ $a }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Catégorie</label>
            <select name="categorie_depense_id" class="rounded-lg border px-3 py-2 text-sm dark:bg-slate-900 min-w-[12rem]">
                <option value="">Toutes</option>
                @foreach($categoriesFiltre as $cat)
                    <option value="{{ $cat->id }}" @selected((string) $filtres['categorie_depense_id'] === (string) $cat->id)>{{ $cat->libelle }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Du</label>
            <input type="date" name="date_debut" value="{{ $filtres['date_debut'] }}" class="rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Au</label>
            <input type="date" name="date_fin" value="{{ $filtres['date_fin'] }}" class="rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
        </div>
        <div class="flex-1 min-w-[12rem]">
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Recherche</label>
            <input type="search" name="q" value="{{ $filtres['q'] }}" placeholder="Intitulé, bénéficiaire, n° pièce…" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
        </div>
        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-800 dark:bg-emerald-600 text-white text-sm font-semibold">Filtrer</button>
        <a href="{{ route('suivi-paiements.print', request()->query()) }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border-2 border-emerald-600 text-emerald-800 dark:text-emerald-200 text-sm font-semibold hover:bg-emerald-50 dark:hover:bg-emerald-900/30 no-underline">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-4 0v4m0-4H8v4"/></svg>
            Imprimer
        </a>
    </form>

    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden shadow-sm">
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">Liste des dépenses</h2>
                <p class="text-xs text-slate-500">Circuit chèque et saisies manuelles — une seule liste.</p>
            </div>
            <p class="text-sm font-bold text-emerald-700 dark:text-emerald-300 tabular-nums">
                Total : {{ number_format($totalMontant, 0, ',', ' ') }} FCFA
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-emerald-700 text-white sticky top-0 z-10">
                    <tr>
                        <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Ref pièce</th>
                        <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Date</th>
                        <th class="px-3 py-2.5 text-left font-semibold">Catégorie</th>
                        <th class="px-3 py-2.5 text-left font-semibold">Intitulé</th>
                        <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap">Montant</th>
                        <th class="px-3 py-2.5 text-left font-semibold">Bénéficiaire</th>
                        <th class="px-3 py-2.5 text-left font-semibold">Origine</th>
                        <th class="px-3 py-2.5 text-left font-semibold">Courrier</th>
                        <th class="px-3 py-2.5 text-left font-semibold">Classement</th>
                        <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($lignes as $ligne)
                    @php
                        $dossierLigne = $ligne->dossierEffectif();
                        $estClasse = $ligne->estClasseMetier();
                    @endphp
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40">
                        <td class="px-3 py-2 whitespace-nowrap font-mono text-xs">{{ $ligne->numero_piece ?: '—' }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $ligne->date_suivi?->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200">
                                {{ $ligne->categorieDepense?->libelle ?? '—' }}
                            </span>
                        </td>
                        <td class="px-3 py-2 max-w-xs truncate" title="{{ $ligne->intitule }}">{{ $ligne->intitule }}</td>
                        <td class="px-3 py-2 text-right tabular-nums font-semibold whitespace-nowrap">{{ number_format((float) $ligne->montant, 0, ',', ' ') }}</td>
                        <td class="px-3 py-2">{{ $ligne->beneficiaire_libelle ?: ($ligne->fournisseur_libelle ?? '—') }}</td>
                        <td class="px-3 py-2 text-xs text-slate-500">
                            {{ $ligne->origine === \App\Models\SuiviPaiement::ORIGINE_CIRCUIT_CHEQUE ? 'Circuit' : 'Saisie' }}
                        </td>
                        <td class="px-3 py-2">
                            @if($ligne->courrier)
                                @can('view', $ligne->courrier)
                                    <a href="{{ route('courriers.show', $ligne->courrier) }}" class="text-emerald-700 dark:text-emerald-400 hover:underline no-underline text-xs font-semibold">Voir</a>
                                @else
                                    —
                                @endcan
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            @if($estClasse && $dossierLigne)
                                @can('view', $dossierLigne)
                                    <a href="{{ route('dossiers.show', $dossierLigne) }}" class="text-emerald-700 dark:text-emerald-300 font-semibold no-underline hover:underline text-xs" title="{{ $dossierLigne->chemin_complet ?? $dossierLigne->nom }}">Classée</a>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-100 text-emerald-800">Classée</span>
                                @endcan
                            @elseif($ligne->estClassementReserveFacturesPrestataires())
                                <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200" title="Classement réservé à la responsable dossiers prestataires">À classer (Taty)</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200">À classer</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-xs">
                            @can('classerDossier', $ligne)
                                <a href="{{ route('suivi-paiements.classer', $ligne) }}" class="text-emerald-600 font-semibold no-underline hover:underline">{{ $estClasse ? 'Reclasser' : 'Classer' }}</a>
                            @endcan
                            @if($dossierLigne)
                                @can('view', $dossierLigne)
                                    @can('classerDossier', $ligne)<span class="text-slate-300 mx-1">·</span>@endcan
                                    <a href="{{ route('dossiers.show', $dossierLigne) }}" class="text-sky-600 font-semibold no-underline hover:underline">Dossier</a>
                                @endcan
                            @elseif($ligne->courrier && $ligne->estClassementReserveFacturesPrestataires())
                                @can('view', $ligne->courrier)
                                    <a href="{{ route('courriers.show', ['courrier' => $ligne->courrier, 'classer' => 1]) }}" class="text-slate-500 font-semibold no-underline hover:underline" title="Classement via le courrier (responsable dossiers prestataires)">Voir courrier</a>
                                @endcan
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-10 text-center text-slate-500">Aucune dépense pour ces filtres.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
