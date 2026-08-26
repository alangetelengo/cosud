@extends('layouts.app')

@section('page-title', 'Nouveau moratoire')
@section('page-title-info', 'État récapitulatif des paiements progressifs')

@section('content')
@include('partials.flash-session', ['class' => 'mb-4'])

<div class="max-w-2xl">
    <form method="post" action="{{ route('moratoires.store') }}" enctype="multipart/form-data" data-loading-text="Génération..."
          class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm p-5 space-y-4">
        @csrf
        <p class="text-sm text-slate-600 dark:text-slate-300">
            Saisissez la dette et le montant d’échéance : le système génère automatiquement le tableau (solde en cascade jusqu’à zéro).
        </p>

        <div>
            <label class="block text-xs font-semibold mb-1">Fournisseur / établissement <span class="text-red-500">*</span></label>
            <input type="text" name="fournisseur_libelle" value="{{ old('fournisseur_libelle', $fournisseur) }}" required
                   class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-900" placeholder="Ex. : ETABLISSEMENT JAY">
            @error('fournisseur_libelle')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="grid sm:grid-cols-2 gap-3">
            <div>
                @include('partials.input-montant-fcfa', [
                    'name' => 'montant_dette_initial',
                    'label' => 'Montant dette (FCFA)',
                    'required' => true,
                    'value' => old('montant_dette_initial', $montantDette),
                    'placeholder' => 'Ex. : 17 989 516',
                ])
            </div>
            <div>
                @include('partials.input-montant-fcfa', [
                    'name' => 'montant_echeance_defaut',
                    'label' => 'Montant échéance (FCFA)',
                    'required' => true,
                    'value' => old('montant_echeance_defaut', $montantEcheanceDefaut),
                    'placeholder' => 'Ex. : 1 500 000',
                ])
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold mb-1">Lieu</label>
                <input type="text" name="lieu" value="{{ old('lieu', 'Brazzaville') }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-900">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Date du document</label>
                <input type="date" name="date_document" value="{{ old('date_document') }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-900">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold mb-1">Signataire (Suivi des dépenses)</label>
            <input type="text" name="signataire_libelle" value="{{ old('signataire_libelle', auth()->user()->name) }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-900">
        </div>

        <div>
            <label class="block text-xs font-semibold mb-1">Observation</label>
            <textarea name="observation" rows="2" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-900">{{ old('observation') }}</textarea>
        </div>

        <div>
            <label class="block text-xs font-semibold mb-1">Pièces justificatives de la dette <span class="text-red-500">*</span></label>
            <input type="file" name="fichiers[]" accept=".pdf,.jpg,.jpeg,.png" multiple required
                   class="w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-3 file:py-2 file:text-white file:font-semibold">
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Obligatoire — PDF ou images (facture, état de dette, etc.). Plusieurs fichiers possibles.</p>
            @error('fichiers')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            @error('fichiers.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" data-loading-text="Génération..."
                    class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 shadow-sm transition-colors">
                Générer l’échéancier
            </button>
            <a href="{{ route('moratoires.index') }}"
               class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 text-sm font-semibold text-slate-700 dark:text-slate-200 no-underline hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                Annuler
            </a>
        </div>
    </form>
</div>
@endsection
