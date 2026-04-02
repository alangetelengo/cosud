@extends('layouts.app')

@section('page-title', 'Modifier le type de métadonnée')
@section('page-title-info', $type->libelle)

@section('content')
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-md border border-slate-100 dark:border-slate-700 p-8 max-w-2xl">
    <form action="{{ route('parametres.types-metadonnees.update', $type) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Code *</label>
            <input type="text" name="code" value="{{ old('code', $type->code) }}" required maxlength="50" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('code') border-red-500 @enderror">
            @error('code')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Libellé *</label>
            <input type="text" name="libelle" value="{{ old('libelle', $type->libelle) }}" required class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('libelle') border-red-500 @enderror">
            @error('libelle')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Type de valeur *</label>
            <select name="type_valeur" required class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                @foreach(['texte','numerique','date','booleen'] as $tv)
                <option value="{{ $tv }}" {{ old('type_valeur', $type->type_valeur) == $tv ? 'selected' : '' }}>{{ ucfirst($tv) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Description</label>
            <textarea name="description" rows="2" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">{{ old('description', $type->description) }}</textarea>
        </div>
        <div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="hidden" name="actif" value="0">
                <input type="checkbox" name="actif" value="1" {{ old('actif', $type->actif) ? 'checked' : '' }} class="rounded w-4 h-4">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Actif</span>
            </label>
        </div>
        <div class="flex gap-4 pt-4">
            <button type="submit" class="px-6 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055]">Mettre à jour</button>
            <a href="{{ route('parametres.types-metadonnees.index') }}" class="px-6 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700">Annuler</a>
        </div>
    </form>
</div>
@endsection
