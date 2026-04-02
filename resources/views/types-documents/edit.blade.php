@extends('layouts.app')

@section('page-title', 'Modifier le type de document')
@section('page-title-info', $type->libelle)

@section('content')
<div class="flex flex-row gap-8 w-full items-stretch">
    {{-- Bloc 1 : Formulaire (gauche) --}}
    <div class="flex-1 min-w-0 bg-white dark:bg-slate-800 rounded-xl shadow-md border border-slate-100 dark:border-slate-700 p-8">
    <form id="form-type-edit" action="{{ route('types-documents.update', $type) }}" method="POST">
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
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Description</label>
                <textarea name="description" rows="2" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">{{ old('description', $type->description) }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Durée de conservation (années)</label>
                <input type="number" name="duree_conservation_annees" value="{{ old('duree_conservation_annees', $type->duree_conservation_annees) }}" min="0" max="500" step="1" placeholder="Vide = non défini, 0 = permanent"
                    class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('duree_conservation_annees') border-red-500 @enderror">
                @error('duree_conservation_annees')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Vide = non défini ; <strong>0</strong> = conservation permanente.</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Extension par défaut</label>
                <input type="text" name="extension_defaut" value="{{ old('extension_defaut', $type->extension_defaut) }}" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
            </div>
            <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50/80 dark:bg-slate-700/30 space-y-2">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Limite au dépôt (fichier)</p>
                <label class="block text-sm text-slate-600 dark:text-slate-400 mb-1">Taille maximale du fichier (Ko)</label>
                <input type="number" name="taille_max_ko" value="{{ old('taille_max_ko', $type->taille_max_ko) }}" min="1" class="w-full max-w-xs px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                <p class="text-xs text-slate-500 dark:text-slate-400">Plafond par fichier pour ce type à l’upload (chaque document a sa taille réelle une fois enregistré).</p>
            </div>
            <div class="p-4 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50/60 dark:bg-emerald-900/20 space-y-4">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Politique de validation</p>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="validation_obligatoire" value="1" {{ old('validation_obligatoire', $type->validation_obligatoire ?? true) ? 'checked' : '' }} class="rounded">
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Validation obligatoire</span>
                </label>
                <div>
                    <label class="block text-sm text-slate-600 dark:text-slate-400 mb-1">Niveau final attendu</label>
                    @php($niveau = old('niveau_validation_final', $type->niveau_validation_final ?? 'dg'))
                    <select name="niveau_validation_final" class="w-full max-w-xs px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('niveau_validation_final') border-red-500 @enderror">
                        <option value="chef_service" {{ $niveau === 'chef_service' ? 'selected' : '' }}>Chef de service</option>
                        <option value="directeur" {{ $niveau === 'directeur' ? 'selected' : '' }}>Directeur</option>
                        <option value="dg" {{ $niveau === 'dg' ? 'selected' : '' }}>DG</option>
                    </select>
                    @error('niveau_validation_final')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="actif" value="1" {{ old('actif', $type->actif) ? 'checked' : '' }} class="rounded">
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Actif</span>
                </label>
            </div>
        </div>
        <div class="mt-8 flex gap-4">
            <button type="submit" id="btn-type-edit-submit" class="px-6 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055] btn-submit-loading">Mettre à jour</button>
            <a href="{{ route('types-documents.index') }}" class="px-6 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700">Annuler</a>
        </div>
    </form>
    </div>
    {{-- Bloc 2 : Aide (droite) --}}
    <aside class="flex-1 min-w-0 sticky top-24 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-6">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100 mb-4">Aide</h3>
        <ul class="space-y-3 text-sm text-slate-600 dark:text-slate-400">
            <li><strong class="text-slate-800 dark:text-slate-200">Modification</strong> — Conservation, limites de dépôt et état actif. Les documents existants gardent ce type ; les plafonds s’appliquent aux prochains fichiers.</li>
        </ul>
    </aside>
</div>
@push('styles')
<style>
/* Spinner chargement (identique page login) */
#btn-type-edit-submit .btn-submit-spinner,
#btn-type-edit-submit .auth-spinner,
#btn-type-edit-submit .btn-type-edit-spinner {
    display: inline-block !important;
    width: 1em !important;
    height: 1em !important;
    min-width: 16px !important;
    min-height: 16px !important;
    border: 2px solid currentColor !important;
    border-right-color: transparent !important;
    border-radius: 50% !important;
    animation: type-doc-edit-spin 0.6s linear infinite !important;
    vertical-align: -0.2em !important;
    margin-right: 0.35rem !important;
    flex-shrink: 0 !important;
}
@keyframes type-doc-edit-spin {
    to { transform: rotate(360deg); }
}
</style>
@endpush
@push('scripts')
<script>
(function(){var f=document.getElementById('form-type-edit'),b=document.getElementById('btn-type-edit-submit');if(!f||!b)return;f.addEventListener('submit',function(){if(b.dataset.loading==='1')return;b.dataset.loading='1';b.innerHTML='<span class="btn-submit-spinner"></span> Mise à jour...';b.disabled=true;});})();
</script>
@endpush
@endsection
