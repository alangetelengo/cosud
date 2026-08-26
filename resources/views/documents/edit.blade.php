@extends('layouts.app')

@section('content-container-class', 'w-full px-4 sm:px-6 lg:px-8')
@section('page-title', 'Modifier le document')
@section('page-title-info', $document->nom_original)

@push('styles')
<style>
/*
 * Même disposition que documents/create : 2 colonnes (gauche = formulaire, droite = panneaux).
 */
@media (min-width: 768px) {
    #doc-edit-grid {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: flex-start !important;
        gap: 2rem !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    #doc-edit-grid > #doc-edit-form {
        flex: 0 0 41.666667% !important;
        width: 41.666667% !important;
        max-width: 41.666667% !important;
        min-width: 0 !important;
    }
    #doc-edit-grid > #doc-edit-preview {
        flex: 0 0 58.333333% !important;
        width: 58.333333% !important;
        max-width: 58.333333% !important;
        min-width: 0 !important;
    }
}
#form-document-edit {
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
}
#btn-document-edit-submit {
    min-width: 280px !important;
    padding-left: 2.5rem !important;
    padding-right: 2.5rem !important;
}
#btn-document-edit-submit .btn-submit-spinner,
#btn-document-edit-submit .auth-spinner {
    display: inline-block !important;
    width: 1em !important;
    height: 1em !important;
    min-width: 16px !important;
    min-height: 16px !important;
    border: 2px solid currentColor !important;
    border-right-color: transparent !important;
    border-radius: 50% !important;
    animation: doc-edit-spin 0.6s linear infinite !important;
    vertical-align: -0.2em !important;
    margin-right: 0.35rem !important;
    flex-shrink: 0 !important;
}
@keyframes doc-edit-spin {
    to { transform: rotate(360deg); }
}
</style>
@endpush

@section('content')
<div class="w-full">
    @if(session('success'))
    <div class="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 flex items-center gap-2">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <span>{{ session('success') }}</span>
    </div>
    @endif
    @if(strtolower($document->statut ?? '') === 'rejete' && ($motifRejet = $document->dernierMotifRejet()))
    <div class="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
        <p class="font-semibold text-red-800 dark:text-red-200 flex items-center gap-2">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            Motif du rejet
        </p>
        <p class="mt-2 text-sm text-red-700 dark:text-red-300 whitespace-pre-wrap">{{ $motifRejet }}</p>
    </div>
    @endif

    {{-- Deux blocs côte à côte dès md (≥768px) : gauche = métadonnées + actions | droite = aide + fichier + extrait --}}
    <div id="doc-edit-grid" class="flex flex-col gap-6 md:flex-row md:gap-8 md:items-start">
        {{-- BLOC GAUCHE : Formulaire — 5/12 --}}
        <div id="doc-edit-form" class="w-full min-w-0 md:w-5/12 md:shrink-0 flex flex-col">
            <form id="form-document-edit" action="{{ route('documents.update', $document) }}" method="POST" data-loading-text="Enregistrement..." class="flex flex-col">
                @csrf
                @method('PUT')
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-700/30">
                    <h3 class="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        Métadonnées du document
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Modifiez les informations ci-dessous</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Dossier (optionnel)</label>
                            <select name="dossier_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition-colors @error('dossier_id') border-red-500 @enderror">
                                @include('partials.dossiers-select-depot-options', [
                                    'dossiersPersoDepot' => $dossiersPersoDepot,
                                    'dossiersPlanDepot' => $dossiersPlanDepot,
                                    'selectedDossierId' => old('dossier_id', $document->dossier_id),
                                ])
                            </select>
                            @error('dossier_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                            @include('partials.dossier-deposit-help')
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Type de document *</label>
                            <select name="type_document_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition-colors @error('type_document_id') border-red-500 @else border-slate-200 @enderror">
                                <option value="">— Sélectionner —</option>
                                @foreach($types as $type)
                                <option value="{{ $type->id }}" {{ old('type_document_id', $document->type_document_id) == $type->id ? 'selected' : '' }}>{{ $type->libelle }} (max {{ number_format($type->taille_max_ko) }} Ko)</option>
                                @endforeach
                            </select>
                            @error('type_document_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Titre (optionnel)</label>
                            <input type="text" name="titre" value="{{ old('titre', $document->titre) }}" placeholder="Titre pour faciliter la recherche"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 placeholder-slate-400 focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500">
                            @error('titre')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Référence (optionnel)</label>
                            <input type="text" name="reference" value="{{ old('reference', $document->reference) }}" placeholder="Ex. REF-2024-001"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 placeholder-slate-400 focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500">
                            @error('reference')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Mots-clés (optionnel)</label>
                        <input type="text" name="mots_cles" value="{{ old('mots_cles', $document->mots_cles) }}" placeholder="Séparés par des virgules"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 placeholder-slate-400 focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500">
                        @error('mots_cles')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Description (optionnel)</label>
                        <textarea name="description" rows="3" placeholder="Description du document"
                                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 placeholder-slate-400 focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500">{{ old('description', $document->description) }}</textarea>
                        @error('description')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex items-start gap-3">
                        <input type="checkbox" name="confidentiel" value="1" id="confidentiel-edit"
                               {{ old('confidentiel', $document->confidentiel) ? 'checked' : '' }}
                               class="mt-1 w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-emerald-600 focus:ring-emerald-500">
                        <label for="confidentiel-edit" class="text-sm text-slate-700 dark:text-slate-300">
                            Document confidentiel — vous y restez toujours accès en tant que déposant / propriétaire ; les autres doivent disposer du droit « Voir dossiers confidentiels »
                        </label>
                    </div>
                </div>
            </div>

                {{-- Boutons — sous la carte, comme sur la page Déposer --}}
                <div class="flex flex-wrap items-center gap-4 mt-6">
                    <button type="submit" id="btn-document-edit-submit" data-loading-text="Enregistrement..."
                            class="inline-flex items-center justify-center gap-2 min-w-[280px] px-10 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shadow-md hover:shadow-lg focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-all disabled:opacity-70 whitespace-nowrap">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <span>Enregistrer les modifications</span>
                    </button>
                    <a href="{{ url()->previous() }}"
                       class="inline-flex items-center justify-center gap-2 min-w-[120px] px-6 py-3 rounded-xl border-2 border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors whitespace-nowrap">
                        Annuler
                    </a>
                    <a href="{{ route('documents.index') }}"
                       class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl border-2 border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors whitespace-nowrap">
                        Retour à la liste
                    </a>
                </div>
            </form>
        </div>

        {{-- BLOC DROIT : Aide + Fichier actuel + métadonnées extraites — ~58 % --}}
        <div id="doc-edit-preview" class="w-full min-w-0 md:w-7/12 flex flex-col gap-6">
            {{-- Panneau Aide --}}
            <aside class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-6">
                <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100 mb-4">Aide</h3>
                <p class="text-sm text-slate-600 dark:text-slate-400">Vous pouvez modifier le dossier, le type, le titre, la référence, les mots-clés et la description. Pour remplacer le fichier, cliquez sur <strong>Nouvelle version</strong> dans le bloc « Fichier actuel »</p>
            </aside>
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-700/30">
                    <h3 class="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        Fichier actuel
                    </h3>
                </div>
                <div class="p-6">
                    @php
                        $ext = strtolower($document->extension ?? pathinfo($document->nom_original ?? '', PATHINFO_EXTENSION));
                        $lib = strtolower($document->typeDocument->libelle ?? '');
                        $icon = match(true) {
                            $ext === 'pdf' || str_contains($lib, 'pdf') => ['bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400', '📄'],
                            in_array($ext, ['doc','docx']) || str_contains($lib, 'word') => ['bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400', '📝'],
                            in_array($ext, ['xls','xlsx']) || str_contains($lib, 'excel') => ['bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400', '📊'],
                            in_array($ext, ['jpg','jpeg','png','gif','webp']) => ['bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400', '🖼️'],
                            default => ['bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400', '📄'],
                        };
                    @endphp
                    <div class="flex items-center gap-4 p-4 rounded-xl bg-slate-50/50 dark:bg-slate-700/30">
                        <div class="w-16 h-16 rounded-xl flex items-center justify-center text-3xl {{ $icon[0] }}">
                            {{ $icon[1] }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-slate-800 dark:text-slate-200 truncate">{{ $document->nom_original }}</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $document->taille_formatee }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Le fichier ne peut pas être remplacé ici.</p>
                            <a href="{{ route('documents.nouvelle-version', $document) }}" class="inline-flex items-center gap-2 mt-3 px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 transition-colors no-underline">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                Nouvelle version
                            </a>
                        </div>
                    </div>

                    @if(strtolower($document->extension ?? '') === 'pdf')
                    <form action="{{ route('documents.extraire-metadonnees', $document) }}" method="POST" class="mt-4">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-emerald-300 dark:border-emerald-600 text-emerald-700 dark:text-emerald-300 font-medium hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Extraire les métadonnées du PDF
                        </button>
                    </form>
                    @endif
                </div>
            </div>

            {{-- Métadonnées extraites --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-700/30">
                    <h3 class="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                        Métadonnées extraites
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Propriétés du fichier (auteur, titre, dates…)</p>
                </div>
                <div class="p-6">
                    @if($document->metadonnees->isEmpty())
                    <p class="text-sm text-slate-500 dark:text-slate-400">Aucune métadonnée. Pour les PDF, cliquez sur « Extraire les métadonnées » ci-dessus.</p>
                    @else
                    <dl class="space-y-2">
                        @foreach($document->metadonnees as $m)
                        <div class="flex justify-between gap-4 py-2 border-b border-slate-100 dark:border-slate-700 last:border-0">
                            <dt class="text-sm font-medium text-slate-600 dark:text-slate-400">{{ $m->typeMetadonnee?->libelle ?? $m->cle }}</dt>
                            <dd class="text-sm text-slate-800 dark:text-slate-200 text-right">{{ $m->valeur_formatee ?? '—' }}</dd>
                        </div>
                        @endforeach
                    </dl>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function(){
    var f = document.getElementById('form-document-edit');
    var b = document.getElementById('btn-document-edit-submit');
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
