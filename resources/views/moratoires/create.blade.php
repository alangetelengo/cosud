@extends('layouts.app')

@section('page-title', 'Nouveau moratoire')
@section('page-title-info', 'État récapitulatif des paiements progressifs')

@section('content')
@include('partials.flash-session', ['class' => 'mb-4'])

@php
    $eligiblesJs = $fournisseursEligibles->map(fn ($f) => [
        'libelle' => $f['fournisseur_libelle'],
        'dette' => $f['dette'],
        'nb' => $f['nb_factures'],
    ])->values();
@endphp

<div class="max-w-2xl" x-data="{
    fournisseur: @js(old('fournisseur_libelle', $fournisseur)),
    eligibles: @js($eligiblesJs),
    get selection() {
        return this.eligibles.find(e => e.libelle === this.fournisseur) || null;
    },
    get montantDette() {
        return this.selection ? this.selection.dette : null;
    },
    get nbFactures() {
        return this.selection ? this.selection.nb : null;
    },
    formatMontant(n) {
        if (n === null || n === undefined) return '—';
        return new Intl.NumberFormat('fr-FR').format(Math.round(n));
    }
}">
    <form method="post" action="{{ route('moratoires.store') }}" enctype="multipart/form-data" data-loading-text="Génération..."
          class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm p-5 space-y-4">
        @csrf
        <p class="text-sm text-slate-600 dark:text-slate-300">
            Le fournisseur et le montant de dette sont issus des <strong>factures saisies par Mme Taty</strong>.
            Choisissez uniquement un prestataire déjà en dette, puis définissez le montant d’échéance.
        </p>

        @if($fournisseursEligibles->isEmpty())
            <div class="rounded-lg border border-amber-200 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-800 px-3 py-2 text-sm text-amber-900 dark:text-amber-100">
                Aucune dette éligible (dette &gt; 0 sans plan actif). Vérifiez d’abord les factures / régularisations saisies.
            </div>
        @endif

        <div>
            <label class="block text-xs font-semibold mb-1">Fournisseur / établissement <span class="text-red-500">*</span></label>
            <select name="fournisseur_libelle" x-model="fournisseur" required
                    class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-900">
                <option value="">— Choisir une dette enregistrée —</option>
                @foreach($fournisseursEligibles as $f)
                    <option value="{{ $f['fournisseur_libelle'] }}"
                            @selected(old('fournisseur_libelle', $fournisseur) === $f['fournisseur_libelle'])>
                        {{ $f['fournisseur_libelle'] }}
                        — {{ number_format($f['dette'], 0, ',', ' ') }} FCFA
                        ({{ $f['nb_factures'] }} fact.)
                    </option>
                @endforeach
            </select>
            @error('fournisseur_libelle')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1" x-show="fournisseur">
                <a :href="'{{ route('moratoires.dettes.detail') }}?fournisseur=' + encodeURIComponent(fournisseur)"
                   class="text-emerald-700 dark:text-emerald-300 font-semibold underline">
                    Voir le détail des factures
                </a>
            </p>
        </div>

        <div class="grid sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold mb-1">Montant dette (FCFA)</label>
                <input type="text" readonly
                       :value="formatMontant(montantDette)"
                       class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900/60 text-slate-700 dark:text-slate-200"
                       placeholder="Sélectionnez un fournisseur">
                <input type="hidden" name="montant_dette_initial" :value="montantDette ?? ''">
                <p class="text-[11px] text-slate-500 mt-1" x-show="nbFactures">
                    <span x-text="nbFactures"></span> facture(s) — calcul automatique (non modifiable)
                </p>
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

        {{-- <div class="grid sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold mb-1">Lieu</label>
                <input type="text" name="lieu" value="{{ old('lieu', 'Brazzaville') }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-900">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Date du document</label>
                <input type="date" name="date_document" value="{{ old('date_document') }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-900">
            </div>
        </div> --}}

        <div>
            <label class="block text-xs font-semibold mb-1">Signataire (Suivi des dépenses)</label>
            <input type="text" name="signataire_libelle" value="{{ old('signataire_libelle', auth()->user()->name) }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-900">
        </div>

        <div>
            <label class="block text-xs font-semibold mb-1">Observation</label>
            <textarea name="observation" rows="2" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-900">{{ old('observation') }}</textarea>
        </div>

        <div>
            <label class="block text-xs font-semibold mb-1">Instruction du DG <span class="text-red-500">*</span></label>
            <input type="file" name="fichiers[]" accept=".pdf,.jpg,.jpeg,.png" multiple required
                   class="w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-3 file:py-2 file:text-white file:font-semibold">
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                Obligatoire — scan ou pièce d’instruction du DG (PDF / images).
                Les justificatifs de dette saisis par le responsable Factures / prestataires restent consultables dans le détail des dettes.
            </p>
            @error('fichiers')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            @error('fichiers.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" data-loading-text="Génération..."
                    class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 shadow-sm transition-colors"
                    @if($fournisseursEligibles->isEmpty()) disabled @endif>
                Générer l’échéancier
            </button>
            <a href="{{ $retourUrl }}"
               class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 text-sm font-semibold text-slate-700 dark:text-slate-200 no-underline hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                Annuler
            </a>
        </div>
    </form>
</div>
@endsection
