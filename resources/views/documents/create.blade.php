@extends('layouts.app')

@section('page-title', 'Déposer un document')
@section('page-title-info', 'Téléversez en glisser-déposer ou sélectionnez un fichier — les métadonnées seront extraites automatiquement')
@section('content-container-class', 'w-full px-4 sm:px-6 lg:px-8')

@push('styles')
<style>
/*
 * Disposition 2 colonnes (gauche = formulaire, droite = dépôt + aperçu).
 * Règles explicites + !important : le CSS global / Bootstrap / build Vite incomplet peut sinon tout empiler en une colonne.
 */
@media (min-width: 768px) {
    #doc-create-grid {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: flex-start !important;
        gap: 2rem !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    #doc-create-grid > #doc-create-form {
        flex: 0 0 41.666667% !important;
        width: 41.666667% !important;
        max-width: 41.666667% !important;
        min-width: 0 !important;
    }
    #doc-create-grid > #doc-create-preview {
        flex: 0 0 58.333333% !important;
        width: 58.333333% !important;
        max-width: 58.333333% !important;
        min-width: 0 !important;
    }
}
#form-document-create {
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
}
#btn-document-create-submit {
    min-width: 280px !important;
    padding-left: 2.5rem !important;
    padding-right: 2.5rem !important;
}
/* Spinner chargement (identique page login) — garanti affichage sur cette page */
#btn-document-create-submit .btn-submit-spinner,
#btn-document-create-submit .auth-spinner {
    display: inline-block !important;
    width: 1em !important;
    height: 1em !important;
    min-width: 16px !important;
    min-height: 16px !important;
    border: 2px solid currentColor !important;
    border-right-color: transparent !important;
    border-radius: 50% !important;
    animation: doc-create-spin 0.6s linear infinite !important;
    vertical-align: -0.2em !important;
    margin-right: 0.35rem !important;
    flex-shrink: 0 !important;
}
@keyframes doc-create-spin {
    to { transform: rotate(360deg); }
}
</style>
@endpush

@php
    $typesJson = $types->map(fn ($t) => [
        'id' => $t->id,
        'libelle' => $t->libelle,
        'extension_defaut' => strtolower($t->extension_defaut ?? ''),
        'taille_max_ko' => $t->taille_max_ko,
    ])->values()->toJson();
@endphp

@section('content')
<div class="w-full" x-data="documentDepot()">
    <form id="form-document-create" action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" data-loading-text="Enregistrement...">
        @csrf
        {{-- Deux blocs côte à côte dès md (≥768px) : gauche = métadonnées + actions | droite = dépôt + aperçu --}}
        <div id="doc-create-grid" class="flex flex-col gap-6 md:flex-row md:gap-8 md:items-start">
            {{-- BLOC GAUCHE : Formulaire (métadonnées + boutons) — 5/12 --}}
            <div id="doc-create-form" class="w-full min-w-0 md:w-5/12 md:shrink-0 flex flex-col">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-700/30">
                    <h3 class="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        Métadonnées du document
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Extraction automatique à partir du fichier — modifiez si nécessaire</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Dossier (optionnel)</label>
                            <select name="dossier_id" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition-colors">
                                @include('partials.dossiers-select-depot-options', [
                                    'dossiersPersoDepot' => $dossiersPersoDepot,
                                    'dossiersPlanDepot' => $dossiersPlanDepot,
                                    'selectedDossierId' => old('dossier_id', request('dossier')),
                                ])
                            </select>
                            @include('partials.dossier-deposit-help')
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Type de document *</label>
                            <select name="type_document_id" x-model="typeDocumentId" required
                                    class="w-full px-4 py-2.5 rounded-xl border dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition-colors @error('type_document_id') border-red-500 @else border-slate-200 @enderror">
                                <option value="">— Sélectionner —</option>
                                @foreach($types as $type)
                                <option value="{{ $type->id }}" {{ old('type_document_id') == $type->id ? 'selected' : '' }}>{{ $type->libelle }} (max {{ number_format($type->taille_max_ko) }} Ko)</option>
                                @endforeach
                            </select>
                            @error('type_document_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                            <p x-show="suggestedType" class="mt-1 text-xs text-emerald-600 dark:text-emerald-400" x-text="'Suggestion : ' + (suggestedType || '')"></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Titre (optionnel)</label>
                            <input type="text" name="titre" x-model="titre" placeholder="Titre pour faciliter la recherche"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 placeholder-slate-400 focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Référence (optionnel)</label>
                            <input type="text" name="reference" x-model="reference" placeholder="Ex. REF-2024-001"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 placeholder-slate-400 focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Mots-clés (optionnel)</label>
                        <input type="text" name="mots_cles" x-model="motsCles" placeholder="Séparés par des virgules"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 placeholder-slate-400 focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Description (optionnel)</label>
                        <textarea name="description" rows="3" placeholder="Description du document"
                                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 placeholder-slate-400 focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500">{{ old('description') }}</textarea>
                    </div>
                    <div class="flex items-start gap-3">
                        <input type="checkbox" name="confidentiel" value="1" id="confidentiel-create"
                               {{ old('confidentiel') ? 'checked' : '' }}
                               class="mt-1 w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-emerald-600 focus:ring-emerald-500">
                        <label for="confidentiel-create" class="text-sm text-slate-700 dark:text-slate-300">
                            Document confidentiel — visible uniquement par les personnes disposant du droit « Voir dossiers confidentiels »
                        </label>
                    </div>
                </div>
            </div>

                {{-- Boutons d'action — largeur suffisante pour le texte --}}
                <div class="flex flex-wrap items-center gap-4 mt-6">
                    <button type="submit" id="btn-document-create-submit" data-loading-text="Enregistrement..."
                            class="inline-flex items-center justify-center gap-2 min-w-[280px] px-10 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shadow-md hover:shadow-lg focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-all disabled:opacity-70 whitespace-nowrap">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0l4-4m-4 4V8" /></svg>
                        <span>Déposer le document</span>
                    </button>
                    <a href="{{ route('documents.index') }}"
                       class="inline-flex items-center justify-center gap-2 min-w-[120px] px-6 py-3 rounded-xl border-2 border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors whitespace-nowrap">
                        Annuler
                    </a>
                </div>
            </div>

            {{-- BLOC DROIT : Zone drag & drop + aperçu — ~58 % sur grand écran --}}
            <div id="doc-create-preview" class="w-full min-w-0 md:w-7/12 flex flex-col gap-6">
                {{-- Zone glisser-déposer --}}
                <div class="relative shrink-0">
                    <div @click="$refs.fileInput.click()"
                         @dragover.prevent="isDragging = true"
                         @dragleave.prevent="isDragging = false"
                         @drop.prevent="onDrop($event)"
                         :class="[
                             'border-2 border-dashed rounded-2xl transition-all duration-300 cursor-pointer',
                             selectedFile ? 'p-6' : 'p-12',
                             isDragging ? 'border-emerald-500 bg-emerald-50/50 dark:bg-emerald-900/10' : 'border-slate-300 dark:border-slate-600 hover:border-emerald-400 dark:hover:border-emerald-600',
                             selectedFile ? 'bg-emerald-50/30 dark:bg-emerald-900/10 border-emerald-400' : 'bg-slate-50/50 dark:bg-slate-800/50'
                         ]">
                        <input type="file" x-ref="fileInput" name="fichier" required
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx"
                               class="hidden" @change="onFileSelect($event)">
                        <div class="text-center">
                            <template x-if="!selectedFile">
                                <div>
                                    <div class="mx-auto w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center mb-4 shadow-lg shadow-emerald-500/25">
                                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
                                    </div>
                                    <p class="text-lg font-semibold text-slate-700 dark:text-slate-200 mb-1">Glissez votre fichier ici</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">ou cliquez pour parcourir • PDF, Word, Excel, images (max 10 Mo)</p>
                                </div>
                            </template>
                            <template x-if="selectedFile">
                                <div class="flex items-center justify-center gap-4">
                                    <div class="w-14 h-14 rounded-xl flex items-center justify-center text-2xl"
                                         :class="fileIconBg">
                                        <span x-text="fileIcon"></span>
                                    </div>
                                    <div class="text-left">
                                        <p class="font-semibold text-slate-800 dark:text-slate-100" x-text="selectedFile?.name"></p>
                                        <p class="text-sm text-slate-500" x-text="formatSize(selectedFile?.size)"></p>
                                        <button type="button" @click.stop="clearFile()" class="mt-2 text-sm text-red-600 hover:text-red-700 dark:text-red-400 font-medium">
                                            Changer de fichier
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                    @error('fichier')<p class="mt-2 text-sm text-red-500">{{ $message }}</p>@enderror
                </div>

                {{-- Aperçu du document --}}
                <div x-show="selectedFile && canPreview" x-cloak x-transition
                     class="flex-1 min-h-0 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-700/30 shrink-0">
                        <h3 class="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                            <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                            Aperçu du document
                        </h3>
                    </div>
                    <div class="p-4 bg-slate-100 dark:bg-slate-900/50 flex-1 min-h-[400px] flex flex-col">
                        <template x-if="previewType === 'image'">
                            <img :src="previewUrl" alt="Aperçu" class="max-w-full mx-auto max-h-[60vh] object-contain rounded-lg border border-slate-200 dark:border-slate-600">
                        </template>
                        <template x-if="previewType === 'pdf'">
                            <iframe :src="previewUrl" class="w-full flex-1 min-h-[450px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white" title="Aperçu PDF"></iframe>
                        </template>
                    </div>
                </div>
                <div x-show="selectedFile && !canPreview" x-cloak x-transition class="p-6 rounded-2xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 text-sm">
                    <span>Aperçu non disponible pour les fichiers Word et Excel.</span>
                    <span x-show="suggestedType" class="block mt-2 font-medium" x-text="'Type détecté : ' + (suggestedType || '')"></span>
                </div>
            </div>
        </div>
    </form>
</div>

@php
    $typesForJs = $types->map(fn($t) => [
        'id' => $t->id,
        'libelle' => $t->libelle,
        'code' => $t->code ?? '',
        'ext' => strtolower($t->extension_defaut ?? ''),
    ])->values();
@endphp
@push('scripts')
<script>
function documentDepot() {
    const types = @json($typesForJs);
    const extToType = {};
    const genericCodes = { pdf: 'PDF', docx: 'DOCUMENT_WORD', xlsx: 'TABLEUR', jpg: 'IMAGE', jpeg: 'IMAGE', png: 'IMAGE', gif: 'IMAGE', webp: 'IMAGE' };
    types.forEach(t => {
        if (!t.ext) return;
        const preferred = genericCodes[t.ext];
        if (preferred && t.code === preferred) {
            extToType[t.ext] = t;
        } else if (!extToType[t.ext]) {
            extToType[t.ext] = t;
        }
    });
    types.forEach(t => {
        if (t.ext && !extToType[t.ext]) extToType[t.ext] = t;
    });
    const extIcons = { pdf: '📄', doc: '📝', docx: '📝', xls: '📊', xlsx: '📊', jpg: '🖼️', jpeg: '🖼️', png: '🖼️' };
    const extBg = { pdf: 'bg-red-100 dark:bg-red-900/30', doc: 'bg-blue-100 dark:bg-blue-900/30', docx: 'bg-blue-100 dark:bg-blue-900/30', xls: 'bg-green-100 dark:bg-green-900/30', xlsx: 'bg-green-100 dark:bg-green-900/30', jpg: 'bg-purple-100 dark:bg-purple-900/30', jpeg: 'bg-purple-100 dark:bg-purple-900/30', png: 'bg-purple-100 dark:bg-purple-900/30' };
    return {
        isDragging: false,
        selectedFile: null,
        titre: @json(old('titre', '')),
        reference: @json(old('reference', '')),
        motsCles: @json(old('mots_cles', '')),
        typeDocumentId: @json((string) (old('type_document_id') ?? '')),
        suggestedType: null,
        get fileIcon() {
            if (!this.selectedFile) return '📁';
            const ext = (this.selectedFile.name.split('.').pop() || '').toLowerCase();
            return extIcons[ext] || '📄';
        },
        get fileIconBg() {
            const ext = this.selectedFile ? (this.selectedFile.name.split('.').pop() || '').toLowerCase() : '';
            return extBg[ext] || 'bg-slate-100 dark:bg-slate-600';
        },
        formatSize(bytes) {
            if (!bytes) return '';
            if (bytes < 1024) return bytes + ' o';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' Ko';
            return (bytes / 1048576).toFixed(1) + ' Mo';
        },
        extractFromFilename(name) {
            const base = name.replace(/\.[^/.]+$/, '');
            const ext = (name.split('.').pop() || '').toLowerCase();
            const words = base.replace(/[-_]/g, ' ').split(/[\s,.;]+/).filter(w => w.length > 1);
            const titre = words.join(' ').trim() || base;
            const refMatch = base.match(/(?:REF|ref)[-_\s]?(\d{4})[-_\s]?(\d+)/i) || base.match(/\d{4}[-_]\d+/);
            const reference = refMatch ? (refMatch[0].replace(/[-_\s]/g, '-').toUpperCase()) : '';
            const motsCles = [...new Set(words.filter(w => w.length > 2 && !/^\d+$/.test(w)))].slice(0, 5).join(', ');
            const extAliases = { doc: 'docx', xls: 'xlsx' };
            const extLookup = extAliases[ext] || ext;
            const suggested = extToType[extLookup] || extToType[ext] || types.find(t => t.ext === 'pdf') || types[0];
            return { titre, reference, motsCles, suggested };
        },
        onFileSelect(e) {
            const f = e.target.files?.[0];
            if (f) this.applyFile(f);
        },
        onDrop(e) {
            this.isDragging = false;
            const f = e.dataTransfer?.files?.[0];
            if (f) {
                this.$refs.fileInput.files = e.dataTransfer.files;
                this.applyFile(f);
            }
        },
        previewUrl: null,
        canPreview: false,
        previewType: null,
        applyFile(f) {
            if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
            this.selectedFile = f;
            const ext = (f.name.split('.').pop() || '').toLowerCase();
            const imageExts = ['jpg','jpeg','png','gif','webp'];
            const pdfExt = ['pdf'];
            this.previewUrl = URL.createObjectURL(f);
            this.canPreview = imageExts.includes(ext) || pdfExt.includes(ext);
            this.previewType = imageExts.includes(ext) ? 'image' : (pdfExt.includes(ext) ? 'pdf' : null);
            const { titre, reference, motsCles, suggested } = this.extractFromFilename(f.name);
            this.titre = titre;
            this.reference = reference;
            this.motsCles = motsCles;
            if (suggested) {
                this.typeDocumentId = String(suggested.id);
                this.suggestedType = suggested.libelle;
            }
        },
        clearFile() {
            if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
            this.previewUrl = null;
            this.canPreview = false;
            this.previewType = null;
            this.selectedFile = null;
            this.$refs.fileInput.value = '';
            this.$refs.fileInput.files = new DataTransfer().files;
        }
    };
}
(function(){
    var f=document.getElementById('form-document-create'), b=document.getElementById('btn-document-create-submit');
    if(!f||!b) return;
    f.addEventListener('submit', function(){
        if(b.dataset.loading==='1') return;
        b.dataset.loading='1';
        b.innerHTML='<span class="btn-submit-spinner"></span> Enregistrement...';
        b.disabled=true;
    });
})();
</script>
@endpush
@endsection
