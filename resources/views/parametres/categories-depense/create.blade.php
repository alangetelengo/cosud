@extends('layouts.app')

@section('page-title', 'Nouvelle catégorie de dépense')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm"><a href="{{ route('parametres.categories-depense.index') }}" class="text-slate-500 hover:text-emerald-600">Catégories</a><span>/</span><span class="font-semibold">Créer</span></nav>
@endsection

@section('content')
<form method="post" action="{{ route('parametres.categories-depense.store') }}" class="max-w-xl space-y-4 rounded-xl border bg-white dark:bg-slate-800 p-6">
    @csrf
    <div>
        <label class="block text-xs font-semibold mb-1">Code <span class="text-red-500">*</span></label>
        <input type="text" name="code" value="{{ old('code') }}" required class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900" placeholder="ex. frais_mission">
        @error('code')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-xs font-semibold mb-1">Libellé <span class="text-red-500">*</span></label>
        <input type="text" name="libelle" value="{{ old('libelle') }}" required class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
        @error('libelle')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-xs font-semibold mb-1">Ordre</label>
        <input type="number" name="ordre" value="{{ old('ordre', 100) }}" min="0" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-slate-900">
    </div>
    <label class="inline-flex items-center gap-2 text-sm">
        <input type="hidden" name="actif" value="0">
        <input type="checkbox" name="actif" value="1" @checked(old('actif', true))>
        Actif
    </label>
    <div class="flex gap-2 pt-2">
        <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white font-semibold text-sm">Enregistrer</button>
        <a href="{{ route('parametres.categories-depense.index') }}" class="px-4 py-2 rounded-lg border font-semibold text-sm no-underline text-slate-700">Annuler</a>
    </div>
</form>
@endsection
