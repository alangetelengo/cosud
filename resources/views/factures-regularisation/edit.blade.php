@extends('layouts.app')

@section('page-title', 'Modifier la régularisation')
@section('page-title-info', 'Correction hors circuit — Taty')

@section('content')
@include('partials.flash-session', ['class' => 'mb-4'])

@php
    $paiementDefaut = old('paiement', $courrier->regularisation_paiement ?: 'impayee');
    $modeDefaut = old('mode_paiement', $courrier->regularisation_mode_paiement ?: 'cheque');
@endphp

<div class="max-w-2xl" x-data="{
    paiement: @js($paiementDefaut),
    mode: @js($modeDefaut),
    montantMensuel: @js(old('montant_mensuel_contrat', $courrier->regularisation_montant_mensuel)),
    nbMois: @js(old('nb_mois_impayes', $courrier->regularisation_nb_mois_impayes)),
    detteCalculee() {
        const m = parseFloat(String(this.montantMensuel).replace(/\\s/g, '').replace(',', '.')) || 0;
        const n = parseInt(String(this.nbMois), 10) || 0;
        if (m <= 0 || n <= 0) return '—';
        return new Intl.NumberFormat('fr-FR').format(m * n) + ' FCFA';
    }
}">
    <form method="post" action="{{ route('factures-regularisation.update', $courrier) }}" enctype="multipart/form-data"
          data-loading-text="Enregistrement..."
          class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm p-5 space-y-4">
        @csrf
        @method('PUT')

        <p class="text-sm text-slate-600 dark:text-slate-300">
            Correction de <strong>{{ $courrier->numeroRegistreComplet() }}</strong>.
            Modification possible tant que le paiement n’a pas été enregistré.
        </p>

        <div>
            <label class="block text-xs font-semibold mb-1">Prestataire / fournisseur <span class="text-red-500">*</span></label>
            <select name="fournisseur_prestataire_id" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
                <option value="">— Choisir dans le référentiel —</option>
                @foreach(($fournisseursPrestataires ?? collect()) as $fp)
                    <option value="{{ $fp->id }}" @selected((int) old('fournisseur_prestataire_id', $courrier->fournisseur_prestataire_id) === (int) $fp->id)>{{ $fp->nom }}</option>
                @endforeach
            </select>
            <p class="text-[11px] text-slate-500 mt-1">Ou saisissez un nouveau nom ci-dessous (création automatique dans le référentiel).</p>
            <input type="text" name="fournisseur_libelle" value="{{ old('fournisseur_libelle', $courrier->fournisseur_prestataire_id ? '' : $courrier->expediteur_libelle) }}"
                   class="mt-1.5 w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900" placeholder="Nouveau fournisseur / prestataire…">
            @error('fournisseur_prestataire_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            @error('fournisseur_libelle')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div x-show="paiement !== 'contrat_mensuel'" x-cloak>
            @include('partials.input-montant-fcfa', [
                'name' => 'montant_facture',
                'label' => 'Montant facture (FCFA)',
                'required' => false,
                'value' => old('montant_facture', $courrier->montant_facture),
                'placeholder' => 'Ex. : 1 500 000',
            ])
        </div>

        <div x-show="paiement === 'contrat_mensuel'" x-cloak class="space-y-3 rounded-lg border border-violet-200 dark:border-violet-800 bg-violet-50/40 dark:bg-violet-900/10 p-3">
            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold mb-1">Montant mensuel contrat (FCFA) <span class="text-red-500">*</span></label>
                    <input type="text" name="montant_mensuel_contrat" inputmode="numeric"
                           x-model="montantMensuel" value="{{ old('montant_mensuel_contrat', $courrier->regularisation_montant_mensuel) }}"
                           class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
                    @error('montant_mensuel_contrat')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Mois impayés <span class="text-red-500">*</span></label>
                    <input type="number" name="nb_mois_impayes" min="1" max="600" step="1"
                           x-model="nbMois" value="{{ old('nb_mois_impayes', $courrier->regularisation_nb_mois_impayes) }}"
                           class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
                    @error('nb_mois_impayes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="rounded-lg bg-white/80 dark:bg-slate-900/50 border border-violet-200/60 px-3 py-2">
                <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Dette initiale calculée</p>
                <p class="text-lg font-bold text-violet-900 dark:text-violet-100 tabular-nums" x-text="detteCalculee()"></p>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold mb-1">Objet</label>
            <input type="text" name="objet" value="{{ old('objet', $courrier->objet) }}" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
            @error('objet')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="grid sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold mb-1">Référence</label>
                <input type="text" name="reference" value="{{ old('reference', $courrier->reference) }}" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Date facture / contrat</label>
                <input type="date" name="date_facture" value="{{ old('date_facture', $courrier->date_courrier?->toDateString()) }}" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold mb-1">Service demandeur</label>
            <select name="service_demandeur_structure_id" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
                <option value="">— Optionnel —</option>
                @foreach($directions as $direction)
                    <option value="{{ $direction->id }}" @selected((string) old('service_demandeur_structure_id', $courrier->service_demandeur_structure_id) === (string) $direction->id)>{{ $direction->nom }}</option>
                @endforeach
            </select>
        </div>

        <fieldset class="rounded-lg border border-slate-200 dark:border-slate-600 p-3 space-y-2">
            <legend class="text-xs font-bold px-1">Type de régularisation <span class="text-red-500">*</span></legend>
            <label class="flex items-center gap-2 text-sm">
                <input type="radio" name="paiement" value="impayee" x-model="paiement" @checked($paiementDefaut === 'impayee')>
                Impayée
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="radio" name="paiement" value="programmee" x-model="paiement" @checked($paiementDefaut === 'programmee')>
                Programmée
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="radio" name="paiement" value="contrat_mensuel" x-model="paiement" @checked($paiementDefaut === 'contrat_mensuel')>
                Contrat mensuel
            </label>
            @error('paiement')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        </fieldset>

        <div x-show="paiement === 'programmee'" x-cloak class="space-y-3 rounded-lg border border-sky-200 dark:border-sky-800 bg-sky-50/40 dark:bg-sky-900/10 p-3">
            <fieldset class="space-y-2">
                <legend class="text-xs font-bold">Mode de paiement <span class="text-red-500">*</span></legend>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="mode_paiement" value="cheque" x-model="mode" @checked($modeDefaut === 'cheque')>
                    Chèque
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="mode_paiement" value="espece" x-model="mode" @checked($modeDefaut === 'espece')>
                    Espèces
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="mode_paiement" value="ov" x-model="mode" @checked($modeDefaut === 'ov')>
                    OV
                </label>
            </fieldset>
            <div>
                <label class="block text-xs font-semibold mb-1">Date de programmation <span class="text-red-500">*</span></label>
                <input type="date" name="date_programmation"
                       value="{{ old('date_programmation', $courrier->regularisation_date_programmation?->toDateString()) }}"
                       class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
                @error('date_programmation')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div x-show="mode === 'cheque' || mode === 'ov'" class="grid sm:grid-cols-2 gap-3">
                <div :class="mode === 'ov' ? 'sm:col-span-2' : ''">
                    <label class="block text-xs font-semibold mb-1">N° pièce / référence <span class="text-red-500">*</span></label>
                    <input type="text" name="numero_piece" value="{{ old('numero_piece', $courrier->regularisation_numero_piece) }}"
                           class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
                    @error('numero_piece')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div x-show="mode === 'cheque'">
                    <label class="block text-xs font-semibold mb-1">Banque</label>
                    <input type="text" name="banque" value="{{ old('banque', $courrier->regularisation_banque) }}"
                           class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
                </div>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold mb-1">Observation</label>
            <textarea name="observation" rows="2" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">{{ old('observation', $courrier->observations) }}</textarea>
        </div>

        @if($courrier->documents->isNotEmpty())
        <div class="rounded-lg border border-slate-200 dark:border-slate-600 p-3 text-xs text-slate-600 dark:text-slate-300">
            <p class="font-semibold mb-1">Scans déjà joints ({{ $courrier->documents->count() }})</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($courrier->documents as $doc)
                    <li>{{ $doc->nom_original }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <div>
            <label class="block text-xs font-semibold mb-1">
                Ajouter des scans
                @if($courrier->documents->isEmpty())<span class="text-red-500">*</span>@endif
            </label>
            <input type="file" name="fichiers[]" accept=".pdf,.jpg,.jpeg,.png" multiple
                   @if($courrier->documents->isEmpty()) required @endif
                   class="w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-3 file:py-2 file:text-white file:font-semibold">
            @error('fichiers')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" data-loading-text="Enregistrement..."
                    class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 shadow-sm transition-colors">
                Enregistrer les modifications
            </button>
            <a href="{{ $retourUrl }}"
               class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 text-sm font-semibold text-slate-700 dark:text-slate-200 no-underline">
                Annuler
            </a>
        </div>
    </form>
</div>
@endsection
