@extends('layouts.app')

@section('content-container-class', 'w-full px-4 sm:px-6 lg:px-8')
@section('page-title', 'Nouvelle version')
@section('page-title-info', $document->nom_original)

@push('styles')
<style>
/* Disposition deux blocs côte à côte (comme documents/create) */
@media (min-width: 768px) {
    #doc-nouvelle-version-grid {
        display: grid !important;
        grid-template-columns: 5fr 7fr !important;
        gap: 1.5rem !important;
    }
}
/* Spinner chargement (identique page documents/create) */
#btn-nouvelle-version-submit .btn-submit-spinner,
#btn-nouvelle-version-submit .auth-spinner {
    display: inline-block !important;
    width: 1em !important;
    height: 1em !important;
    min-width: 16px !important;
    min-height: 16px !important;
    border: 2px solid currentColor !important;
    border-right-color: transparent !important;
    border-radius: 50% !important;
    animation: doc-nouvelle-version-spin 0.6s linear infinite !important;
    vertical-align: -0.2em !important;
    margin-right: 0.35rem !important;
    flex-shrink: 0 !important;
}
@keyframes doc-nouvelle-version-spin {
    to { transform: rotate(360deg); }
}
</style>
@endpush

@section('content')
<div class="w-full">
    @if(session('error'))
    <div class="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 flex items-center gap-2">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>{{ session('error') }}</span>
    </div>
    @endif

    <div id="doc-nouvelle-version-grid" class="grid grid-cols-1 md:grid-cols-12 gap-6">
        {{-- BLOC GAUCHE : Formulaire --}}
        <div class="md:col-span-5 flex flex-col min-w-0">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-700/30">
                    <h3 class="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        Remplacer le fichier actuel
                    </h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Le fichier actuel sera conservé dans l'historique des versions. Version actuelle : v{{ $document->versions->max('numero') ?? 1 }}</p>
                </div>

                <form action="{{ route('documents.nouvelle-version.store', $document) }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Nouveau fichier *</label>
                        <div class="relative">
                            <input type="file" name="fichier" required
                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx,.gif,.webp"
                                   class="block w-full text-sm text-slate-500 dark:text-slate-400
                                          file:mr-4 file:py-3 file:px-5
                                          file:rounded-xl file:border-0
                                          file:text-sm file:font-semibold
                                          file:bg-emerald-50 file:text-emerald-700 dark:file:bg-emerald-900/30 dark:file:text-emerald-300
                                          hover:file:bg-emerald-100 dark:hover:file:bg-emerald-900/50
                                          file:cursor-pointer
                                          border border-slate-200 dark:border-slate-600 dark:bg-slate-700/50 rounded-xl">
                        </div>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Max {{ number_format($document->typeDocument?->taille_max_ko ?? 10240) }} Ko
                            @if($document->typeDocument)
                                ({{ $document->typeDocument->libelle }})
                            @endif
                        </p>
                        @error('fichier')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Commentaire (optionnel)</label>
                        <textarea name="commentaire" rows="2" placeholder="Ex. Correction des données page 3"
                                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 placeholder-slate-400 focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500">{{ old('commentaire') }}</textarea>
                        @error('commentaire')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex flex-wrap items-center gap-4 pt-4">
                        <button type="submit" id="btn-nouvelle-version-submit"
                                class="inline-flex items-center justify-center gap-2 min-w-[240px] px-10 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shadow-md hover:shadow-lg focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-all disabled:opacity-70">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Déposer la nouvelle version
                        </button>
                        <a href="{{ route('documents.edit', $document) }}"
                           class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl border-2 border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                            Annuler
                        </a>
                        <a href="{{ route('documents.index') }}"
                           class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl border-2 border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                            Retour à la liste
                        </a>
                    </div>
                </form>
            </div>

            <div class="mt-6 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    <strong>Fichier actuel :</strong> {{ $document->nom_original }} ({{ $document->taille_formatee }})
                </p>
            </div>
        </div>

        {{-- BLOC DROITE : Aide --}}
        <div class="md:col-span-7 flex flex-col min-w-0">
            <aside class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-6 sticky top-24">
                <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100 mb-4">Aide</h3>
                <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">
                    Déposez ici une nouvelle version du fichier pour remplacer l'actuel. L'ancien fichier sera conservé dans l'historique des versions pour assurer la traçabilité.
                </p>
                <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">
                    Pour modifier uniquement les métadonnées (dossier, type, titre, référence, mots-clés, description) sans changer le fichier, utilisez la page <a href="{{ route('documents.edit', $document) }}" class="text-emerald-600 dark:text-emerald-400 hover:underline font-medium">Modifier le document</a>.
                </p>
                <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800">
                    <p class="text-sm text-emerald-800 dark:text-emerald-200">
                        <strong>Conseil :</strong> Un commentaire optionnel permet d'indiquer les changements (ex. « Correction page 5 », « Version signée »).
                    </p>
                </div>
            </aside>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function(){
    var f = document.querySelector('form[action*="nouvelle-version"]');
    var b = document.getElementById('btn-nouvelle-version-submit');
    if (!f || !b) return;
    f.addEventListener('submit', function(){
        if (b.dataset.loading === '1') return;
        b.dataset.loading = '1';
        b.innerHTML = '<span class="btn-submit-spinner"></span> Enregistrement...';
        b.disabled = true;
    });
})();
</script>
@endpush
@endsection
