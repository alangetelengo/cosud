@extends('layouts.app')

@section('page-title', 'Paiement effectif')
@section('page-title-info', 'Régularisation programmée — Eleni')

@section('content')
@include('partials.flash-session', ['class' => 'mb-4'])

@php
    use App\Services\FactureRegularisationService;
    $mode = $courrier->regularisation_mode_paiement;
    $refsRequises = in_array($mode, [FactureRegularisationService::MODE_CHEQUE, FactureRegularisationService::MODE_OV], true);
@endphp

<div class="max-w-2xl space-y-4">
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm p-5">
        <dl class="grid sm:grid-cols-2 gap-3 text-sm">
            <div>
                <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">N°</dt>
                <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ $courrier->numeroRegistreComplet() }}</dd>
            </div>
            <div>
                <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Fournisseur</dt>
                <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ $courrier->expediteur_libelle }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Objet</dt>
                <dd class="text-slate-700 dark:text-slate-200">{{ $courrier->objet }}</dd>
            </div>
            <div>
                <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Montant</dt>
                <dd class="font-semibold tabular-nums">{{ number_format((float) $courrier->montant_facture, 0, ',', ' ') }} FCFA</dd>
            </div>
            <div>
                <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Mode programmé</dt>
                <dd>{{ FactureRegularisationService::libelleModePaiement($mode) }}</dd>
            </div>
            @if($courrier->regularisation_date_programmation)
            <div>
                <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Date programmation</dt>
                <dd>{{ $courrier->regularisation_date_programmation->format('d/m/Y') }}</dd>
            </div>
            @endif
        </dl>
    </div>

    <form method="post" action="{{ route('factures-regularisation.payer.store', $courrier) }}" enctype="multipart/form-data"
          data-loading-text="Enregistrement..."
          class="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-white dark:bg-slate-800 shadow-sm p-5 space-y-4">
        @csrf

        <p class="text-sm text-slate-600 dark:text-slate-300">
            Confirmez le paiement réel. Une ligne sera créée dans le <strong>suivi des dépenses</strong> et la facture sortira de la dette.
        </p>

        <div>
            <label class="block text-xs font-semibold mb-1">Date de paiement effectif <span class="text-red-500">*</span></label>
            <input type="date" name="date_paiement" value="{{ old('date_paiement', now()->toDateString()) }}" required
                   class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
            @error('date_paiement')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        @if($refsRequises)
        <div class="grid sm:grid-cols-2 gap-3">
            <div class="{{ $mode === FactureRegularisationService::MODE_OV ? 'sm:col-span-2' : '' }}">
                <label class="block text-xs font-semibold mb-1">
                    {{ $mode === FactureRegularisationService::MODE_OV ? 'Référence OV' : 'N° pièce / chèque' }}
                    <span class="text-red-500">*</span>
                </label>
                <input type="text" name="numero_piece"
                       value="{{ old('numero_piece', $courrier->regularisation_numero_piece) }}"
                       class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900" required>
                @error('numero_piece')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @if($mode === FactureRegularisationService::MODE_CHEQUE)
            <div>
                <label class="block text-xs font-semibold mb-1">Banque</label>
                <input type="text" name="banque" value="{{ old('banque', $courrier->regularisation_banque) }}"
                       class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
            </div>
            @endif
        </div>
        @endif

        <div>
            <label class="block text-xs font-semibold mb-1">Observation</label>
            <textarea name="observation" rows="2" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">{{ old('observation') }}</textarea>
        </div>

        <div>
            <p class="block text-xs font-semibold mb-1">Preuve de paiement (optionnel)</p>
            @include('courriers.partials.scans-upload-preview', [
                'scansRequired' => false,
                'scansInputId' => 'fichier-scan-regularisation-payer',
                'scansLabel' => 'Ajouter un ou plusieurs fichiers',
            ])
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Ajoutez un scan si besoin (déposé dans « À classer — dépenses »).</p>
            @error('fichiers')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            @error('fichiers.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" data-loading-text="Enregistrement..."
                    class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 shadow-sm transition-colors">
                Valider le paiement
            </button>
            <a href="{{ $retourUrl }}"
               class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 text-sm font-semibold text-slate-700 dark:text-slate-200 no-underline hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                Annuler
            </a>
        </div>
    </form>
</div>

@push('scripts')
@include('courriers.partials.scans-upload-preview-script')
@endpush
@endsection
