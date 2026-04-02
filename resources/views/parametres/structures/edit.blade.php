@extends('layouts.app')

@section('page-title', 'Modifier la structure')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('parametres.index') }}" class="text-slate-500 hover:text-emerald-600 transition-colors">Paramètres</a>
        <span class="text-slate-300">/</span>
        <a href="{{ route('parametres.structures.index') }}" class="text-slate-500 hover:text-emerald-600 transition-colors">Organigramme</a>
        <span class="text-slate-300">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">{{ $structure->nom }}</span>
    </nav>
@endsection

@section('content')
<div class="flex flex-row gap-8 w-full items-stretch">
    {{-- Bloc 1 : Formulaire (gauche) --}}
    <div class="flex-1 min-w-0 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-8">
        <form id="formUpdateStructure" action="{{ route('parametres.structures.update', $structure) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Nom *</label>
                <input type="text" name="nom" value="{{ old('nom', $structure->nom) }}" required maxlength="255" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('nom') border-red-500 @enderror">
                @error('nom')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Code *</label>
                <input type="text" name="code" value="{{ old('code', $structure->code) }}" required maxlength="50" class="w-full max-w-xs px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white font-mono @error('code') border-red-500 @enderror">
                @error('code')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Responsable (manuel)</label>
                <select name="responsable_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('responsable_id') border-red-500 @enderror">
                    <option value="">— Aucun —</option>
                    @foreach($utilisateurs as $u)
                    <option value="{{ $u->id }}" {{ (string) old('responsable_id', $structure->responsable_id) === (string) $u->id ? 'selected' : '' }}>
                        {{ $u->name }}{{ $u->email ? ' — '.$u->email : '' }}
                    </option>
                    @endforeach
                </select>
                @error('responsable_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Utilisé comme fallback si aucun titulaire n'est résolu via les affectations/fonctions.</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Fonction métier (validation)</label>
                <select name="fonction_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('fonction_id') border-red-500 @enderror">
                    <option value="">— Non définie —</option>
                    @foreach($fonctions as $f)
                    <option value="{{ $f->id }}" {{ (string) old('fonction_id', $structure->fonction_id) === (string) $f->id ? 'selected' : '' }}>{{ $f->libelle }} ({{ $f->code }})</option>
                    @endforeach
                </select>
                @error('fonction_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Titulaire = utilisateur affecté à cette structure avec la même fonction (pivot).</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Rôle technique (optionnel)</label>
                <select name="role_technique" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('role_technique') border-red-500 @enderror">
                    <option value="">— Aucun —</option>
                    @foreach($rolesTechniques as $rn)
                    <option value="{{ $rn }}" {{ old('role_technique', $structure->role_technique) === $rn ? 'selected' : '' }}>{{ $rn }}</option>
                    @endforeach
                </select>
                @error('role_technique')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
            </div>
            @php $titulaire = $structure->titulaireValidationActuel(); @endphp
            @if($titulaire)
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-200 dark:border-slate-600 text-sm text-slate-600 dark:text-slate-300">
                <span class="font-semibold text-slate-800 dark:text-slate-200">Titulaire actuel (résolu) :</span> {{ $titulaire->name }}
            </div>
            @endif
            <div>
                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input type="hidden" name="actif" value="0">
                    <input type="checkbox" name="actif" value="1" {{ old('actif', $structure->actif) ? 'checked' : '' }} class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Actif</span>
                </label>
            </div>
        </form>
        <div class="flex flex-wrap items-center gap-4 pt-4 border-t border-slate-100 dark:border-slate-700">
            <button type="submit" form="formUpdateStructure" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">Enregistrer</button>
            <a href="{{ route('parametres.structures.index') }}" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl border-2 border-slate-300 dark:border-slate-600 font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 no-underline">Annuler</a>
            <form action="{{ route('parametres.structures.destroy', $structure) }}" method="POST" class="inline">
                @csrf
                @method('DELETE')
                <button type="button" onclick="flashAlert('Êtes-vous sûr de vouloir supprimer cette structure ? Les utilisateurs rattachés seront déliés.', this.closest('form'), {icon:'🗑️', danger:true, confirmText:'Supprimer'})" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl border-2 border-red-300 dark:border-red-600 text-red-600 dark:text-red-400 font-semibold hover:bg-red-50 dark:hover:bg-red-900/20">Supprimer</button>
            </form>
        </div>
    </div>
    {{-- Bloc 2 : Aide (droite) --}}
    <aside class="flex-1 min-w-0 sticky top-24 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-6">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100 mb-4">Aide</h3>
        <ul class="space-y-3 text-sm text-slate-600 dark:text-slate-400">
            <li><strong class="text-slate-800 dark:text-slate-200">Code</strong> — Identifiant unique. Modifier le code peut impacter les règles métier.</li>
            <li><strong class="text-slate-800 dark:text-slate-200">Fonction & rôle technique</strong> — Définissent qui peut valider à ce niveau ; le nom affiché est le titulaire courant.</li>
            <li><strong class="text-slate-800 dark:text-slate-200">Supprimer</strong> — Impossible si la structure a des sous-structures. Les utilisateurs rattachés seront déliés.</li>
        </ul>
    </aside>
</div>
@endsection
