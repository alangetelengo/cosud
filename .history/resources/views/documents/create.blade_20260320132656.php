@extends('layouts.app')

@section('page-title', 'Déposer un document')
@section('page-title-info', 'Téléverser un nouveau document dans le GED')

@section('content')
{{-- Grille 8+4 : formulaire (col-md-8) et aide (col-md-4) --}}
<div class="grid grid-cols-1 md:grid-cols-12 gap-6 w-full">
    {{-- Colonne 1 : Formulaire (8/12) --}}
    <div class="row">
        <div class="col-md-8">
            <div class="md:col-span-8">
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-md border border-slate-100 dark:border-slate-700 p-8">
                    <form id="form-document-create" action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Dossier (optionnel)</label>
                        <select name="dossier_id" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('dossier_id') border-red-500 @enderror">
                            <option value="">— Aucun dossier —</option>
                            @foreach($dossiers as $d)
                            <option value="{{ $d->id }}" {{ old('dossier_id', request('dossier')) == $d->id ? 'selected' : '' }}>{{ $d->chemin_complet }}</option>
                            @endforeach
                        </select>
                        @error('dossier_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Type de document *</label>
                        <select name="type_document_id" required class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white @error('type_document_id') border-red-500 @enderror">
                            <option value="">— Sélectionner —</option>
                            @foreach($types as $type)
                            <option value="{{ $type->id }}" {{ old('type_document_id') == $type->id ? 'selected' : '' }}>{{ $type->libelle }} (max {{ number_format($type->taille_max_ko) }} Ko)</option>
                            @endforeach
                        </select>
                        @error('type_document_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Fichier *</label>
                        <input type="file" name="fichier" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-[#00b464] file:text-white">
                        @error('fichier')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Taille max : 10 Mo. Formats : PDF, Word, Excel, images.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Titre (optionnel)</label>
                        <input type="text" name="titre" value="{{ old('titre') }}" placeholder="Titre pour faciliter la recherche" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Référence (optionnel)</label>
                        <input type="text" name="reference" value="{{ old('reference') }}" placeholder="Ex. REF-2024-001" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Mots-clés (optionnel)</label>
                            <input type="text" name="mots_cles" value="{{ old('mots_cles') }}" placeholder="Séparés par des virgules" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Description (optionnel)</label>
                            <textarea name="description" rows="3" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">{{ old('description') }}</textarea>
                        </div>
                        </div>
                        <div class="mt-8 flex gap-4">
                            <button type="submit" id="btn-document-create-submit" class="px-6 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055] btn-submit-loading">Enregistrer</button>
                            <a href="{{ route('documents.index') }}" class="px-6 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">

        </div>
    </div>
        {{-- Colonne 2 : Aide (4/12) --}}
        <div class="md:col-span-4">
            <div class="md:sticky md:top-24 bg-sky-50 dark:bg-sky-900/20 rounded-xl border border-sky-200/80 dark:border-sky-800/80 p-6 h-fit">
                <div class="flex items-center gap-2 mb-4">
                    <span class="w-8 h-8 rounded-lg bg-sky-500/20 dark:bg-sky-500/30 flex items-center justify-center text-sky-600 dark:text-sky-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </span>
                    <h3 class="font-semibold text-sky-800 dark:text-sky-200">Aide</h3>
                </div>
                <ul class="space-y-4 text-sm text-slate-700 dark:text-slate-300">
                    <li>
                        <strong class="text-slate-800 dark:text-slate-200">Dossier</strong><br>
                        Sélectionnez le dossier de destination pour classer le document. Optionnel : vous pouvez déposer sans dossier.
                    </li>
                    <li>
                        <strong class="text-slate-800 dark:text-slate-200">Type de document</strong><br>
                        Choisissez le type (Facture, Contrat, etc.). Chaque type a une taille maximale autorisée.
                    </li>
                    <li>
                        <strong class="text-slate-800 dark:text-slate-200">Fichier</strong><br>
                        Formats acceptés : PDF, Word, Excel, images. Taille max : 10 Mo.
                    </li>
                    <li>
                        <strong class="text-slate-800 dark:text-slate-200">Métadonnées</strong><br>
                        Le titre, la référence et les mots-clés permettent de retrouver rapidement le document.
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
</div>
{{-- Fin grille --}}
@push('scripts')
<style>
.btn-doc-spinner { display: inline-block; width: 1em; height: 1em; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: btn-doc-spin 0.6s linear infinite; vertical-align: -0.2em; margin-right: 0.35rem; }
@keyframes btn-doc-spin { to { transform: rotate(360deg); } }
</style>
<script>
(function(){var f=document.getElementById('form-document-create'),b=document.getElementById('btn-document-create-submit');if(!f||!b)return;f.addEventListener('submit',function(){if(b.dataset.loading==='1')return;b.dataset.loading='1';b.innerHTML='<span class="btn-doc-spinner"></span> Enregistrement...';b.disabled=true;});})();
</script>
@endpush
@endsection
