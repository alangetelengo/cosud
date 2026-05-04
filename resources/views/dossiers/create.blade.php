@extends('layouts.app')

@section('page-title', 'Nouveau dossier')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('dossiers.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors">Plan de classement</a>
        <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">Créer un dossier</span>
    </nav>
@endsection

@section('content')
@php
    $modeRacineCombined = !empty($modeRacineOuPersonnel);
    $afficheFormulaireRacineOrgSeul = !empty($modeRacineSeulementVue) && ! $modeRacineCombined;
    $racineDef = (bool) ($racineParDefaut ?? false);
    $cocherRacineParDefaut = (bool) ($cocherRacineParDefaut ?? false);
    $racineOld = old('creer_racine') === '1' || old('creer_racine') === 1 || old('creer_racine') === true || old('placement') === 'structure';
    $formSoumisAvecErreurs = session()->has('errors');
    $racineInitial = $afficheFormulaireRacineOrgSeul
        || $racineOld
        || (! $formSoumisAvecErreurs && ! $afficheFormulaireRacineOrgSeul && $racineDef && old('creer_racine') === null && old('placement') === null)
        || (! $formSoumisAvecErreurs && ! $afficheFormulaireRacineOrgSeul && $cocherRacineParDefaut);
    $espacePersoInit = ! empty($sansRacinePersonnelle)
        && ($formSoumisAvecErreurs ? (bool) old('creer_racine_personnelle') : true);
    $placementInitial = old('placement', 'personnel');
@endphp

@if($errors->any())
<div class="max-w-2xl mb-4">
    <div class="rounded-xl border border-red-200 bg-red-50 text-red-800 px-4 py-3 text-sm">
        <p class="font-semibold">La création du dossier a échoué :</p>
        <ul class="mt-1 list-disc pl-5">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
</div>
@endif

@if(empty($formulaireCreationDisponible))
<div class="max-w-2xl">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-8">
        <div class="p-6 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
            <p class="text-amber-800 dark:text-amber-200 font-medium">Aucun emplacement disponible pour créer un dossier</p>
            <p class="text-sm text-amber-700 dark:text-amber-300 mt-1">Vous n’avez pas de dossier parent accessible, pas la permission de créer une racine de structure, et pas la permission de créer votre espace personnel. Contactez un administrateur si besoin.</p>
            <a href="{{ route('dossiers.index') }}" class="inline-flex mt-4 px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium no-underline">Retour au plan de classement</a>
        </div>
    </div>
</div>
@elseif($modeRacineCombined)
<div class="max-w-2xl" x-data="{ placement: @json($placementInitial) }">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-8">
        @if(isset($parent) && $parent)
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-6 p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800">
            Sous-dossier de : <strong class="text-slate-800 dark:text-slate-200">{{ $parent->chemin_complet }}</strong>
        </p>
        @endif
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-6 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-600">
            Vous n’avez pas encore d’<strong class="text-slate-800 dark:text-slate-200">espace personnel</strong> à la racine du plan. Choisissez ce que vous souhaitez créer en premier.
        </p>
        <form action="{{ route('dossiers.store') }}" method="POST" class="space-y-6" @submit="if(placement==='personnel'){ document.querySelectorAll('[data-champ=creer_racine],[data-champ=structure_id_racine]').forEach(el=>el.removeAttribute('name')); } else { const cp=document.querySelector('[data-champ=creer_racine_perso]'); if(cp) cp.removeAttribute('name'); }">
            @csrf
            <fieldset class="space-y-3">
                <legend class="text-sm font-semibold text-slate-800 dark:text-slate-200 mb-2">Type de création *</legend>
                <label class="flex items-start gap-3 cursor-pointer p-3 rounded-xl border border-slate-200 dark:border-slate-600 has-[:checked]:border-emerald-400 dark:has-[:checked]:border-emerald-600">
                    <input type="radio" name="placement" value="personnel" class="mt-1 text-emerald-600 focus:ring-emerald-500" x-model="placement">
                    <span>
                        <span class="block text-sm font-semibold text-slate-800 dark:text-slate-200">Mon espace personnel</span>
                        <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">Dossier racine sans parent (ex. « Mes dossiers ») — vous pourrez y créer des sous-dossiers.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 cursor-pointer p-3 rounded-xl border border-slate-200 dark:border-slate-600 has-[:checked]:border-emerald-400 dark:has-[:checked]:border-emerald-600">
                    <input type="radio" name="placement" value="structure" class="mt-1 text-emerald-600 focus:ring-emerald-500" x-model="placement">
                    <span>
                        <span class="block text-sm font-semibold text-slate-800 dark:text-slate-200">Racine pour une structure</span>
                        <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">Point d’entrée du plan de classement pour une direction ou un service.</span>
                    </span>
                </label>
            </fieldset>
            <input type="hidden" name="creer_racine_personnelle" value="1" data-champ="creer_racine_perso">

            <div class="space-y-5" x-show="placement === 'structure'" x-cloak>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Structure *</label>
                    <input type="hidden" name="creer_racine" value="1" data-champ="creer_racine">
                    <select name="structure_id" data-champ="structure_id_racine" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('structure_id') border-red-500 @enderror" :required="placement === 'structure'">
                        <option value="">— Choisir la structure —</option>
                        @foreach($structuresRacine as $st)
                        <option value="{{ $st->id }}" {{ (string) old('structure_id') === (string) $st->id ? 'selected' : '' }}>{{ $st->nom }} ({{ $st->code }})</option>
                        @endforeach
                    </select>
                    @error('structure_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Nom du dossier *</label>
                    <input type="text" name="nom" value="{{ old('nom') }}" required maxlength="255" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('nom') border-red-500 @enderror">
                    @error('nom')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Type de dossier</label>
                    <select name="type_dossier_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                        <option value="">— Non défini —</option>
                        @include('dossiers.partials.type-dossier-select-options', ['typesDossier' => $typesDossier, 'selectedId' => old('type_dossier_id')])
                    </select>
                    @error('type_dossier_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    @include('dossiers.partials.aide-types-dossier-choix')
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Description</label>
                    <textarea name="description" rows="2" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">{{ old('description') }}</textarea>
                </div>
            </div>
            <div class="flex flex-wrap gap-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">Créer le dossier</button>
                <a href="{{ $parent ? route('dossiers.show', $parent) : route('dossiers.index') }}" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl border-2 border-slate-300 dark:border-slate-600 font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 no-underline">Annuler</a>
            </div>
        </form>
    </div>
</div>
@elseif($afficheFormulaireRacineOrgSeul)
<div class="max-w-2xl">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-8">
        @if(isset($parent) && $parent)
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-6 p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800">
            Sous-dossier de : <strong class="text-slate-800 dark:text-slate-200">{{ $parent->chemin_complet }}</strong>
        </p>
        @endif
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-6 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-600">
            <strong class="text-slate-800 dark:text-slate-200">Dossier racine (source)</strong> — choisissez la structure pour laquelle ce dossier servira de point d’entrée dans le plan de classement.
        </p>
        <form action="{{ route('dossiers.store') }}" method="POST" class="space-y-6">
            @csrf
            <input type="hidden" name="creer_racine" value="1">
            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Structure *</label>
                    <select name="structure_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('structure_id') border-red-500 @enderror">
                        <option value="">— Choisir la structure —</option>
                        @foreach($structuresRacine as $st)
                        <option value="{{ $st->id }}" {{ (string) old('structure_id') === (string) $st->id ? 'selected' : '' }}>{{ $st->nom }} ({{ $st->code }})</option>
                        @endforeach
                    </select>
                    @error('structure_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Nom du dossier *</label>
                    <input type="text" name="nom" value="{{ old('nom') }}" required maxlength="255" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('nom') border-red-500 @enderror">
                    @error('nom')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Type de dossier</label>
                    <select name="type_dossier_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                        <option value="">— Non défini —</option>
                        @include('dossiers.partials.type-dossier-select-options', ['typesDossier' => $typesDossier, 'selectedId' => old('type_dossier_id')])
                    </select>
                    @error('type_dossier_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    @include('dossiers.partials.aide-types-dossier-choix')
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Description</label>
                    <textarea name="description" rows="2" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">{{ old('description') }}</textarea>
                </div>
            </div>
            <div class="flex flex-wrap gap-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">Créer le dossier racine</button>
                <a href="{{ $parent ? route('dossiers.show', $parent) : route('dossiers.index') }}" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl border-2 border-slate-300 dark:border-slate-600 font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 no-underline">Annuler</a>
            </div>
        </form>
    </div>
</div>
@elseif(!empty($modeEspacePersonnelSeul))
<div class="max-w-2xl">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-8">
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-6 p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800">
            <strong class="text-slate-800 dark:text-slate-200">Premier accès</strong> — créez votre espace personnel à la racine du plan (vous pourrez y ajouter des sous-dossiers ensuite). Choisissez le nom du dossier (souvent « Mes dossiers »).
        </p>
        <form action="{{ route('dossiers.store') }}" method="POST" class="space-y-6">
            @csrf
            <input type="hidden" name="creer_racine_personnelle" value="1">
            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Nom du dossier *</label>
                    <input type="text" name="nom" value="{{ old('nom', 'Mes dossiers') }}" required maxlength="255" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('nom') border-red-500 @enderror">
                    @error('nom')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Type de dossier</label>
                    <select name="type_dossier_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                        <option value="">— Non défini —</option>
                        @include('dossiers.partials.type-dossier-select-options', ['typesDossier' => $typesDossier, 'selectedId' => old('type_dossier_id')])
                    </select>
                    @error('type_dossier_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    @include('dossiers.partials.aide-types-dossier-choix')
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Description</label>
                    <textarea name="description" rows="2" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">{{ old('description') }}</textarea>
                </div>
            </div>
            <div class="flex flex-wrap gap-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">Créer mon espace personnel</button>
                <a href="{{ route('dossiers.index') }}" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl border-2 border-slate-300 dark:border-slate-600 font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 no-underline">Annuler</a>
            </div>
        </form>
    </div>
</div>
@else
<div class="max-w-2xl" x-data="{ racine: {{ $racineInitial ? 'true' : 'false' }}, espacePerso: {{ $espacePersoInit ? 'true' : 'false' }}, aSansRacine: @json(!empty($sansRacinePersonnelle)) }">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-8">
        @if(!empty($sansRacinePersonnelle))
        <div class="mb-6 p-4 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50/80 dark:bg-emerald-900/20">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="creer_racine_personnelle" value="1" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" x-model="espacePerso" @if($espacePersoInit) checked @endif>
                <span class="text-sm text-slate-700 dark:text-slate-300">
                    <span class="font-semibold text-slate-800 dark:text-slate-200 block">Créer mon espace personnel à la racine du plan</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400 mt-1">Sans dossier parent — pour vos dossiers personnels. Décochez pour choisir un dossier parent (structure ou partagé) ci-dessous.</span>
                </span>
            </label>
        </div>
        @endif
        @if(isset($parent) && $parent)
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-3 p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800">
            Sous-dossier de : <strong class="text-slate-800 dark:text-slate-200">{{ $parent->chemin_complet }}</strong>
        </p>
        @if(!empty($peutCreerRacine))
        <div class="text-sm mb-6 p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-900 dark:text-amber-100">
            <strong class="font-semibold">Emplacement</strong> — par défaut, ce formulaire créera un dossier <strong>sous</strong> « {{ $parent->nom }} ». Pour l’ajouter au <strong>même niveau</strong> que les dossiers principaux du plan (à côté de « {{ $parent->nom }} »), cochez <strong>« Créer un dossier racine pour une structure »</strong> et choisissez la structure concernée.
        </div>
        @endif
        @endif

        <form action="{{ route('dossiers.store') }}" method="POST" class="space-y-6" @submit="
            if (aSansRacine && espacePerso) { const p2 = document.querySelector('select[name=parent_id]'); if (p2) p2.removeAttribute('name'); document.querySelectorAll('input[name=creer_racine], select[name=structure_id]').forEach(el => el.removeAttribute('name')); }
            else if (racine) { const p = document.querySelector('select[name=parent_id]'); if (p) p.removeAttribute('name'); }
            if (aSansRacine && !espacePerso) { const cp = document.querySelector('input[name=creer_racine_personnelle]'); if (cp) cp.removeAttribute('name'); }
        ">
            @csrf
            <div class="space-y-5">
                @if(!empty($peutCreerRacine) && $structuresRacine->isNotEmpty() && !empty($peutCreerSousDossier))
                <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50/80 dark:bg-slate-800/50" x-show="!aSansRacine || !espacePerso">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="creer_racine" value="1" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" x-model="racine" {{ $racineInitial ? 'checked' : '' }}>
                        <span>
                            <span class="block text-sm font-semibold text-slate-800 dark:text-slate-200">Créer un dossier racine (source) pour une structure</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400 mt-1">Sans dossier parent — pour le plan de classement au niveau direction ou service.</span>
                            @if($cocherRacineParDefaut && ! $formSoumisAvecErreurs)
                            <span class="block text-xs text-emerald-700 dark:text-emerald-300 mt-2 font-medium">Présélectionné car vous venez du plan sans dossier parent ciblé. Décochez pour choisir un dossier parent ci-dessous.</span>
                            @endif
                        </span>
                    </label>
                    <div class="mt-4" x-show="racine" x-cloak>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Structure *</label>
                        <select name="structure_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('structure_id') border-red-500 @enderror" :required="racine">
                            <option value="">— Choisir la structure —</option>
                            @foreach($structuresRacine as $st)
                            <option value="{{ $st->id }}" {{ (string) old('structure_id') === (string) $st->id ? 'selected' : '' }}>{{ $st->nom }} ({{ $st->code }})</option>
                            @endforeach
                        </select>
                        @error('structure_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    </div>
                </div>
                @endif

                <div x-show="!racine && (!aSansRacine || !espacePerso)">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Dossier parent *</label>
                    <select name="parent_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('parent_id') border-red-500 @enderror" :required="!racine && (!aSansRacine || !espacePerso)">
                        <option value="">— Sélectionner un dossier —</option>
                        @foreach($parents as $opt)
                        <option value="{{ $opt->value }}" {{ (string) old('parent_id', $parent?->id ?? '') === (string) $opt->value ? 'selected' : '' }}>{{ $opt->label }}</option>
                        @endforeach
                    </select>
                    @error('parent_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Dossiers de votre structure auxquels vous avez accès @if(empty($sansRacinePersonnelle))et votre espace personnel @endif.</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Nom du dossier *</label>
                    <input type="text" name="nom" value="{{ old('nom') }}" required maxlength="255" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('nom') border-red-500 @enderror">
                    @error('nom')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Type de dossier</label>
                    <select name="type_dossier_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                        <option value="">— Non défini —</option>
                        @include('dossiers.partials.type-dossier-select-options', ['typesDossier' => $typesDossier, 'selectedId' => old('type_dossier_id')])
                    </select>
                    @error('type_dossier_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    @include('dossiers.partials.aide-types-dossier-choix')
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Description</label>
                    <textarea name="description" rows="2" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">{{ old('description') }}</textarea>
                </div>
            </div>
            <div class="flex flex-wrap gap-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">Créer le dossier</button>
                <a href="{{ $parent ? route('dossiers.show', $parent) : route('dossiers.index') }}" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl border-2 border-slate-300 dark:border-slate-600 font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 no-underline">Annuler</a>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var forms = document.querySelectorAll('form[action="{{ route('dossiers.store') }}"]');
    forms.forEach(function (form) {
        var submitBtn = form.querySelector('button[type="submit"]');
        if (!submitBtn) return;

        form.addEventListener('submit', function () {
            if (submitBtn.dataset.loading === '1') return;
            submitBtn.dataset.loading = '1';
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-70', 'cursor-not-allowed');
            submitBtn.innerHTML = '<span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin shrink-0"></span> Enregistrement...';
        });
    });
});
</script>
@endpush
