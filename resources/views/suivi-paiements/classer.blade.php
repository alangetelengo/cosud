@extends('layouts.app')

@section('content-container-class', 'w-full max-w-3xl px-4 sm:px-6 lg:px-8')
@section('page-title', 'Classer la dépense')
@section('page-title-info', 'n° '.$ligne->numeroComplet().' — '.$ligne->intitule)

@section('btn-create')
    <a href="{{ route('suivi-paiements.index', ['annee' => $ligne->numero_annee]) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-sm font-semibold no-underline hover:bg-slate-50">
        Retour à la liste
    </a>
@endsection

@section('content')
@include('partials.flash-session', ['class' => 'mb-4'])

@if($errors->any())
<div class="mb-4 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm">
    <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden"
     x-data="{ mode: '{{ old('mode', 'nouveau') }}' }">
    <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 bg-emerald-50/80 dark:bg-emerald-900/20">
        <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">Classement prestataire / bénéficiaire</p>

    </div>
    <form method="post" action="{{ route('suivi-paiements.classer.store', $ligne) }}" class="p-5 space-y-4">
        @csrf
        <div class="text-sm text-slate-600 dark:text-slate-300 space-y-1">
            <p><span class="font-semibold text-slate-800 dark:text-slate-100">Bénéficiaire :</span> {{ $ligne->beneficiaire_libelle ?: ($ligne->fournisseur_libelle ?: '—') }}</p>
            <p><span class="font-semibold text-slate-800 dark:text-slate-100">Montant :</span> {{ number_format((float) $ligne->montant, 0, ',', ' ') }} FCFA</p>
            <p><span class="font-semibold text-slate-800 dark:text-slate-100">Catégorie :</span> {{ $ligne->categorieDepense?->libelle ?? '—' }}</p>
        </div>

        <div class="flex flex-col gap-2">
            <label class="inline-flex items-center gap-2 text-sm font-semibold">
                <input type="radio" name="mode" value="existant" x-model="mode" class="rounded border-slate-300 text-emerald-600">
                Dossier existant
            </label>
            <label class="inline-flex items-center gap-2 text-sm font-semibold">
                <input type="radio" name="mode" value="nouveau" x-model="mode" class="rounded border-slate-300 text-emerald-600">
                Créer un nouveau dossier
            </label>
        </div>

        <div x-show="mode === 'existant'" class="space-y-1">
            <label class="block text-xs font-semibold mb-1">Dossier</label>
            <select name="dossier_id" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
                <option value="">— Choisir —</option>
                @foreach($dossiersClassement as $d)
                    <option value="{{ $d->id }}" @selected((int) old('dossier_id') === (int) $d->id)>{{ $d->chemin_complet }}</option>
                @endforeach
            </select>
            @if($dossiersClassement->isEmpty())
                <p class="text-xs text-amber-700">Aucun dossier accessible — créez-en un.</p>
            @endif
        </div>

        <div x-show="mode === 'nouveau'" class="space-y-3">
            <div>
                <label class="block text-xs font-semibold mb-1">Nom du dossier</label>
                <input type="text" name="nom_dossier" value="{{ old('nom_dossier', $suggestionNom) }}"
                       class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900"
                       placeholder="Ex. SOMAC / ayant droit">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Sous <span class="font-normal text-slate-400">(optionnel)</span></label>
                <select name="parent_id" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
                    <option value="">Prestataires / fournisseurs (par défaut)</option>
                    @foreach($dossiersClassement as $d)
                        <option value="{{ $d->id }}" @selected((int) old('parent_id') === (int) $d->id)>{{ $d->chemin_complet }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
            Confirmer le classement
        </button>
    </form>
</div>
@endsection
