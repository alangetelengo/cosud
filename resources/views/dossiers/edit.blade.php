@extends('layouts.app')

@section('page-title', 'Modifier le dossier')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('dossiers.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors">Plan de classement</a>
        @foreach($dossier->cheminAncetres() as $ancetre)
        <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">/</span>
        <a href="{{ route('dossiers.show', $ancetre) }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors">{{ $ancetre->nom }}</a>
        @endforeach
        <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">/</span>
        <a href="{{ route('dossiers.show', $dossier) }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">{{ $dossier->nom }}</a>
        <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">Modifier</span>
    </nav>
@endsection

@section('content')
<div class="max-w-2xl">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-8">
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-600">
            Emplacement actuel : <strong class="text-slate-800 dark:text-slate-200">{{ $dossier->chemin_complet }}</strong>
        </p>
        @if((int) $dossier->proprietaire_id === (int) auth()->id())
        <p class="text-sm text-emerald-800 dark:text-emerald-200 mb-4 p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800">
            Vous êtes propriétaire de ce dossier : vous pouvez le repositionner dans les emplacements visibles de votre périmètre.
        </p>
        @endif

        <form action="{{ route('dossiers.update', $dossier) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')
            <div class="space-y-5">
                @if($peutChangerParent && $parentOptions->isNotEmpty())
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Emplacement dans le plan</label>
                    <select name="parent_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('parent_id') border-red-500 @enderror">
                        @foreach($parentOptions as $opt)
                        <option value="{{ $opt->value }}" {{ (string) old('parent_id', $dossier->parent_id ?? '') === (string) $opt->value ? 'selected' : '' }}>{{ $opt->label }}</option>
                        @endforeach
                    </select>
                    @error('parent_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Racine du plan = même niveau que les dossiers principaux de la structure ; sinon choisissez un dossier parent visible dans votre périmètre.</p>
                </div>
                @if($peutChoisirRacineOrg && $dossier->parent_id !== null)
                <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50/80 dark:bg-slate-800/50">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="est_racine_org" value="1" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" {{ old('est_racine_org') ? 'checked' : '' }}>
                        <span class="text-sm text-slate-700 dark:text-slate-300">
                            <span class="font-semibold text-slate-800 dark:text-slate-200">Racine de structure</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400 mt-1">Si vous déplacez ce dossier à la racine du plan, cochez pour l’enregistrer comme <strong>entrée racine de structure</strong> (équivalent à la création « dossier racine »). Sinon il reste une racine « simple » comme les dossiers issus du référentiel.</span>
                        </span>
                    </label>
                </div>
                @endif
                @elseif($peutChangerParent && $parentOptions->isEmpty())
                <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm text-amber-800 dark:text-amber-200">
                    Aucun autre emplacement disponible (dossier seul dans l’arborescence ou périmètre limité).
                </div>
                @elseif(!$peutChangerParent)
                <p class="text-xs text-slate-500 dark:text-slate-400">La racine « Mes dossiers » ne peut pas être déplacée.</p>
                @endif
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Nom du dossier *</label>
                    <input type="text" name="nom" value="{{ old('nom', $dossier->nom) }}" required maxlength="255" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('nom') border-red-500 @enderror">
                    @error('nom')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Type de dossier</label>
                    <select name="type_dossier_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                        <option value="">— Non défini —</option>
                        @foreach($typesDossier as $td)
                        <option value="{{ $td->id }}" {{ (string) old('type_dossier_id', $dossier->type_dossier_id ?? '') === (string) $td->id ? 'selected' : '' }}>{{ $td->libelle }} ({{ $td->code }})</option>
                        @endforeach
                    </select>
                    @error('type_dossier_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Description</label>
                    <textarea name="description" rows="2" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">{{ old('description', $dossier->description) }}</textarea>
                    @error('description')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="flex flex-wrap gap-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">Enregistrer</button>
                <a href="{{ route('dossiers.show', $dossier) }}" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl border-2 border-slate-300 dark:border-slate-600 font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 no-underline">Annuler</a>
            </div>
        </form>
    </div>
</div>
@endsection
