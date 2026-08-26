@extends('layouts.app')

@section('page-title', 'Nouvelle régularisation')
@section('page-title-info', 'Facture historique hors circuit')

@section('content')
@include('partials.flash-session', ['class' => 'mb-4'])

<div class="max-w-2xl" x-data="{ paiement: @js(old('paiement', 'impayee')) }">
    <form method="post" action="{{ route('factures-regularisation.store') }}" enctype="multipart/form-data"
          data-loading-text="Enregistrement..."
          class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm p-5 space-y-4">
        @csrf

        <p class="text-sm text-slate-600 dark:text-slate-300">
            Aucune notification circuit, aucune étape AC/DG. La facture est clôturée dès l’enregistrement.
        </p>

        <div>
            <label class="block text-xs font-semibold mb-1">Fournisseur / prestataire <span class="text-red-500">*</span></label>
            <input type="text" name="fournisseur_libelle" value="{{ old('fournisseur_libelle') }}" required
                   class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900" placeholder="Ex. : ETABLISSEMENT JAY">
            @error('fournisseur_libelle')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            @include('partials.input-montant-fcfa', [
                'name' => 'montant_facture',
                'label' => 'Montant facture (FCFA)',
                'required' => true,
                'value' => old('montant_facture'),
                'placeholder' => 'Ex. : 1 500 000',
            ])
        </div>

        <div>
            <label class="block text-xs font-semibold mb-1">Objet</label>
            <input type="text" name="objet" value="{{ old('objet') }}" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900"
                   placeholder="Laissé vide → généré automatiquement">
            @error('objet')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="grid sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold mb-1">Référence facture</label>
                <input type="text" name="reference" value="{{ old('reference') }}" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Date facture</label>
                <input type="date" name="date_facture" value="{{ old('date_facture') }}" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold mb-1">Service demandeur</label>
            <select name="service_demandeur_structure_id" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
                <option value="">— Optionnel —</option>
                @foreach($directions as $direction)
                    <option value="{{ $direction->id }}" @selected((string) old('service_demandeur_structure_id') === (string) $direction->id)>{{ $direction->nom }}</option>
                @endforeach
            </select>
        </div>

        <fieldset class="rounded-lg border border-slate-200 dark:border-slate-600 p-3 space-y-2">
            <legend class="text-xs font-bold px-1">Statut paiement <span class="text-red-500">*</span></legend>
            <label class="flex items-center gap-2 text-sm">
                <input type="radio" name="paiement" value="impayee" x-model="paiement" @checked(old('paiement', 'impayee') === 'impayee')">
                Impayée — compte dans la <strong>dette</strong> fournisseur
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="radio" name="paiement" value="payee" x-model="paiement" @checked(old('paiement') === 'payee')">
                Déjà payée — facturé <strong>et</strong> payé (dette nulle pour cette ligne)
            </label>
            @error('paiement')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        </fieldset>

        <div x-show="paiement === 'payee'" x-cloak class="grid sm:grid-cols-2 gap-3 rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50/40 dark:bg-emerald-900/10 p-3">
            <div>
                <label class="block text-xs font-semibold mb-1">N° pièce / chèque <span class="text-red-500">*</span></label>
                <input type="text" name="numero_piece" value="{{ old('numero_piece') }}" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
                @error('numero_piece')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Banque</label>
                <input type="text" name="banque" value="{{ old('banque') }}" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold mb-1">Date de paiement <span class="text-red-500">*</span></label>
                <input type="date" name="date_paiement" value="{{ old('date_paiement') }}" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
                @error('date_paiement')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold mb-1">Observation</label>
            <textarea name="observation" rows="2" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">{{ old('observation') }}</textarea>
        </div>

        <div>
            <label class="block text-xs font-semibold mb-1">Scans (PDF / images) <span id="label-scans-requis" class="text-red-500 hidden">*</span></label>
            <input type="file" name="fichiers[]" accept=".pdf,.jpg,.jpeg,.png" multiple
                   class="w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-3 file:py-2 file:text-white file:font-semibold">
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Obligatoire si la facture est déjà payée (facture + preuve de paiement).</p>
            @error('fichiers')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            @error('fichiers.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" data-loading-text="Enregistrement..."
                    class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 shadow-sm transition-colors">
                Enregistrer hors circuit
            </button>
            <a href="{{ route('factures-regularisation.index') }}"
               class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 text-sm font-semibold text-slate-700 dark:text-slate-200 no-underline hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                Annuler
            </a>
        </div>
    </form>
</div>
@endsection
