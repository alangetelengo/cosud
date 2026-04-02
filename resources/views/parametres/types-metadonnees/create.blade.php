@extends('layouts.app')

@section('page-title', 'Nouveau type de métadonnée')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('parametres.index') }}" class="text-slate-500 hover:text-emerald-600">Paramètres</a> / <a href="{{ route('parametres.types-metadonnees.index') }}" class="text-slate-500 hover:text-emerald-600">Types de métadonnées</a> / <span class="text-slate-700 dark:text-slate-200 font-semibold">Nouveau</span>
    </nav>
@endsection

@section('content')
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-md border border-slate-100 dark:border-slate-700 p-8 max-w-2xl">
    <form action="{{ route('parametres.types-metadonnees.store') }}" method="POST" class="space-y-6">
        @csrf
        <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Code *</label>
            <input type="text" name="code" value="{{ old('code') }}" required maxlength="50" placeholder="ex. auteur" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('code') border-red-500 @enderror">
            @error('code')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Libellé *</label>
            <input type="text" name="libelle" value="{{ old('libelle') }}" required class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('libelle') border-red-500 @enderror">
            @error('libelle')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Type de valeur *</label>
            <select name="type_valeur" required class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                <option value="texte" {{ old('type_valeur') == 'texte' ? 'selected' : '' }}>Texte</option>
                <option value="numerique" {{ old('type_valeur') == 'numerique' ? 'selected' : '' }}>Numérique</option>
                <option value="date" {{ old('type_valeur') == 'date' ? 'selected' : '' }}>Date</option>
                <option value="booleen" {{ old('type_valeur') == 'booleen' ? 'selected' : '' }}>Booléen</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Description</label>
            <textarea name="description" rows="2" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">{{ old('description') }}</textarea>
        </div>
        <div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="hidden" name="actif" value="0">
                <input type="checkbox" name="actif" value="1" {{ old('actif', true) ? 'checked' : '' }} class="rounded w-4 h-4">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Actif</span>
            </label>
        </div>
        <div class="flex gap-4 pt-4">
            <button type="submit" class="px-6 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055]">Enregistrer</button>
            <a href="{{ route('parametres.types-metadonnees.index') }}" class="px-6 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700">Annuler</a>
        </div>
    </form>
</div>
@endsection
