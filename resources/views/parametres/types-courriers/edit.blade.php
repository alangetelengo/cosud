@extends('layouts.app')

@section('page-title', 'Modifier le type de courrier')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('parametres.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">Paramètres</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <a href="{{ route('parametres.types-courriers.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">Types de courriers</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">{{ $type->libelle }}</span>
    </nav>
@endsection

@section('content')
<div class="flex flex-row gap-8 w-full items-stretch">
    {{-- Bloc 1 : Formulaire (gauche) --}}
    <div class="flex-1 min-w-0 bg-white dark:bg-slate-800 rounded-xl shadow-md border border-slate-100 dark:border-slate-700 p-8">
    <form action="{{ route('parametres.types-courriers.update', $type) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="space-y-6">
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
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Circuit de traitement</label>
                <select name="circuit_courrier_id" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('circuit_courrier_id') border-red-500 @enderror">
                    <option value="">— Aucun (enregistrement simple, sans circuit) —</option>
                    @foreach($circuits as $c)
                    <option value="{{ $c->id }}" @selected(old('circuit_courrier_id', $type->circuit_courrier_id) == $c->id)>{{ $c->libelle }}</option>
                    @endforeach
                </select>
                @error('circuit_courrier_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                <p class="text-xs text-slate-500 mt-1.5">Tout courrier arrivée créé avec ce type suivra automatiquement ce circuit.</p>
            </div>
            <div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="actif" value="0">
                    <input type="checkbox" name="actif" value="1" {{ old('actif', $type->actif) ? 'checked' : '' }} class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4">
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Actif</span>
                </label>
            </div>
        </div>
        <div class="mt-8 flex gap-4">
            <button type="submit" class="px-6 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055]">Mettre à jour</button>
            <a href="{{ route('parametres.types-courriers.index') }}" class="px-6 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700">Annuler</a>
        </div>
    </form>
    </div>
    {{-- Bloc 2 : Aide (droite) --}}
    <aside class="flex-1 min-w-0 sticky top-24 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-6">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100 mb-4">Aide</h3>
        <ul class="space-y-3 text-sm text-slate-600 dark:text-slate-400">
            <li><strong class="text-slate-800 dark:text-slate-200">Changer le circuit</strong> — S’applique uniquement aux prochains courriers créés avec ce type. Les courriers déjà en cours conservent leur circuit d’origine.</li>
        </ul>
    </aside>
</div>
@endsection
