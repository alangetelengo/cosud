@extends('layouts.app')

@section('page-title', 'Nouveau type de dossier')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('parametres.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">Paramètres</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <a href="{{ route('parametres.types-dossiers.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">Types de dossiers</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">Nouveau</span>
    </nav>
@endsection

@section('content')
<div class="flex flex-row gap-8 w-full items-stretch">
    {{-- Bloc 1 : Formulaire (gauche) --}}
    <div class="flex-1 min-w-0 bg-white dark:bg-slate-800 rounded-xl shadow-md border border-slate-100 dark:border-slate-700 p-8">
    <form id="form-type-dossier-create" action="{{ route('parametres.types-dossiers.store') }}" method="POST">
        @csrf
        <div class="space-y-6">
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Code *</label>
                <input type="text" name="code" value="{{ old('code') }}" required maxlength="50" placeholder="ex. administratif" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('code') border-red-500 @enderror">
                @error('code')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Libellé *</label>
                <input type="text" name="libelle" value="{{ old('libelle') }}" required class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('libelle') border-red-500 @enderror">
                @error('libelle')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Description</label>
                <textarea name="description" rows="2" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">{{ old('description') }}</textarea>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Icône (emoji)</label>
                    <input type="text" name="icone_defaut" value="{{ old('icone_defaut') }}" placeholder="📁" maxlength="50" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Couleur (#hex)</label>
                    <input type="text" name="couleur_defaut" value="{{ old('couleur_defaut') }}" placeholder="#3B82F6" pattern="#[A-Fa-f0-9]{6}" maxlength="7" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('couleur_defaut') border-red-500 @enderror">
                    @error('couleur_defaut')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="actif" value="0">
                    <input type="checkbox" name="actif" value="1" {{ old('actif', true) ? 'checked' : '' }} class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4">
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Actif</span>
                </label>
            </div>
        </div>
        <div class="mt-8 flex gap-4">
            <button type="submit" id="btn-type-dossier-create-submit" class="px-6 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055] btn-submit-loading">Enregistrer</button>
            <a href="{{ route('parametres.types-dossiers.index') }}" class="px-6 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700">Annuler</a>
        </div>
    </form>
    </div>
    {{-- Bloc 2 : Aide (droite) --}}
    <aside class="flex-1 min-w-0 sticky top-24 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-6">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100 mb-4">Aide</h3>
        <ul class="space-y-3 text-sm text-slate-600 dark:text-slate-400">
            <li><strong class="text-slate-800 dark:text-slate-200">Code</strong> — Identifiant unique (ex. administratif, finance, projet). Utilisé dans le plan de classement.</li>
            <li><strong class="text-slate-800 dark:text-slate-200">Icône et couleur</strong> — Emoji ou caractère pour l'affichage. La couleur hex (#RRGGBB) personnalise l'interface.</li>
        </ul>
    </aside>
</div>
@push('styles')
<style>
/* Spinner chargement (identique page login) */
#btn-type-dossier-create-submit .btn-submit-spinner,
#btn-type-dossier-create-submit .auth-spinner {
    display: inline-block !important;
    width: 1em !important;
    height: 1em !important;
    min-width: 16px !important;
    min-height: 16px !important;
    border: 2px solid currentColor !important;
    border-right-color: transparent !important;
    border-radius: 50% !important;
    animation: type-dossier-create-spin 0.6s linear infinite !important;
    vertical-align: -0.2em !important;
    margin-right: 0.35rem !important;
    flex-shrink: 0 !important;
}
@keyframes type-dossier-create-spin {
    to { transform: rotate(360deg); }
}
</style>
@endpush
@push('scripts')
<script>
(function(){var f=document.getElementById('form-type-dossier-create'),b=document.getElementById('btn-type-dossier-create-submit');if(!f||!b)return;f.addEventListener('submit',function(){if(b.dataset.loading==='1')return;b.dataset.loading='1';b.innerHTML='<span class="btn-submit-spinner"></span> Enregistrement...';b.disabled=true;});})();
</script>
@endpush
@endsection
