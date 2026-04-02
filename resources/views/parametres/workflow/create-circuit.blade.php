@extends('layouts.app')

@section('page-title', 'Créer un circuit de workflow')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('parametres.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600">Paramètres</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <a href="{{ route('parametres.workflow.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600">Workflow</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">Créer un circuit</span>
    </nav>
@endsection

@section('content')
<div class="flex flex-row gap-8 w-full items-stretch" x-data="circuitWorkflow()">
    {{-- Bloc 1 : Formulaire (gauche) --}}
    <div class="flex-1 min-w-0 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-8">
        <form action="{{ route('parametres.workflow.store-circuit') }}" method="POST" class="space-y-6" @submit="validateCircuit">
            @csrf
            @if($errors->any())
            <div class="p-4 rounded-2xl bg-red-50 dark:bg-red-900/20 border border-red-200/80 dark:border-red-800 text-red-800 dark:text-red-200">
                <ul class="list-disc list-inside space-y-1 text-sm">
                    @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            {{-- Paramètres communs du circuit --}}
            <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-200 dark:border-slate-600">
                <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 mb-4">Paramètres du circuit</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Portée du circuit *</label>
                        <select name="cible_scope" x-model="scope" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                            <option value="global">Global (tous types / services)</option>
                            <option value="type">Type de document</option>
                            <option value="service">Service</option>
                        </select>
                    </div>
                    <div x-show="scope === 'type'" x-cloak>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Type de document *</label>
                        <select name="type_document_id" :required="scope === 'type'" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                            <option value="">— Sélectionner —</option>
                            @foreach($types as $t)
                            <option value="{{ $t->id }}" {{ (string) old('type_document_id') === (string) $t->id ? 'selected' : '' }}>{{ $t->libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="scope === 'service'" x-cloak>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Service *</label>
                        <select name="structure_scope_id" :required="scope === 'service'" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                            <option value="">— Sélectionner —</option>
                            @foreach($services as $s)
                            <option value="{{ $s->id }}" {{ (string) old('structure_scope_id') === (string) $s->id ? 'selected' : '' }}>{{ $s->nom }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Préfixe du code *</label>
                        <input type="text" name="prefixe_code" x-model="prefixeCode" maxlength="30" pattern="[a-z0-9_]+" placeholder="ex: validation_contrat" required
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white font-mono @error('prefixe_code') border-red-500 @enderror">
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Minuscules, chiffres et underscores uniquement. Les codes seront : prefixe_etape_1, prefixe_etape_2, …</p>
                        @error('prefixe_code')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            {{-- Étapes du circuit --}}
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200">Étapes du circuit</h3>
                    <button type="button" @click="addEtape" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Ajouter une étape
                    </button>
                </div>

                <template x-for="(etape, index) in etapes" :key="etape.id">
                    <div class="mb-5 p-5 rounded-xl border-2 border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800/80">
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 font-bold text-sm" x-text="index + 1"></span>
                            <button type="button" x-show="etapes.length > 1" @click="removeEtape(index)" class="p-2 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" title="Supprimer cette étape">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Nom de l'étape *</label>
                                <input type="text" :name="'etapes[' + index + '][nom]'" x-model="etape.nom" required maxlength="255"
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                    placeholder="Ex: Validation chef de service">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Mode de validation</label>
                                <div class="flex flex-wrap gap-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" :name="'etapes[' + index + '][mode]'" value="hierarchique" x-model="etape.mode" class="rounded-full border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                        <span class="text-sm text-slate-700 dark:text-slate-300">Hiérarchique</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" :name="'etapes[' + index + '][mode]'" value="role" x-model="etape.mode" class="rounded-full border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                        <span class="text-sm text-slate-700 dark:text-slate-300">Par rôle</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" :name="'etapes[' + index + '][mode]'" value="fonction" x-model="etape.mode" class="rounded-full border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                        <span class="text-sm text-slate-700 dark:text-slate-300">Par fonction</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" :name="'etapes[' + index + '][mode]'" value="utilisateur" x-model="etape.mode" class="rounded-full border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                        <span class="text-sm text-slate-700 dark:text-slate-300">Utilisateur</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" :name="'etapes[' + index + '][mode]'" value="libre" x-model="etape.mode" class="rounded-full border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                        <span class="text-sm text-slate-700 dark:text-slate-300">Destinataire libre</span>
                                    </label>
                                </div>
                            </div>

                            {{-- Rôle requis (si mode = role) --}}
                            <div x-show="etape.mode === 'role'" x-transition>
                                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Rôle requis</label>
                                <select :name="'etapes[' + index + '][role_requis]'" x-model="etape.role_requis"
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                                    <option value="">— Sélectionner —</option>
                                    @foreach($roles as $r)
                                    <option value="{{ $r }}">{{ $r }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Fonction requise (si mode = fonction) --}}
                            <div x-show="etape.mode === 'fonction'" x-transition>
                                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Fonction requise</label>
                                <select :name="'etapes[' + index + '][fonction_requise_id]'" x-model="etape.fonction_requise_id"
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                                    <option value="">— Sélectionner —</option>
                                    @foreach($fonctions as $f)
                                    <option value="{{ $f->id }}">{{ $f->libelle }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Validateur (si mode = utilisateur) --}}
                            <div x-show="etape.mode === 'utilisateur'" x-transition>
                                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Validateur</label>
                                <select :name="'etapes[' + index + '][validateur_id]'" x-model="etape.validateur_id"
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                                    <option value="">— Sélectionner —</option>
                                    @foreach($utilisateurs as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex flex-wrap gap-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">Créer le circuit</button>
                <a href="{{ route('parametres.workflow.index') }}" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl border-2 border-slate-300 dark:border-slate-600 font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 no-underline">Annuler</a>
            </div>
        </form>
    </div>

    {{-- Bloc 2 : Aide (droite) --}}
    <aside class="flex-1 min-w-0 sticky top-24 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-6">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100 mb-4">Créer un circuit</h3>
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">
            Un circuit regroupe plusieurs étapes de validation chaînées. Les documents passent de l'étape 1 → 2 → 3 → … jusqu'à la dernière.
        </p>
        <ul class="space-y-3 text-sm text-slate-600 dark:text-slate-400">
            <li><strong class="text-slate-800 dark:text-slate-200">Préfixe</strong> — Génère automatiquement les codes : prefixe_etape_1, prefixe_etape_2, …</li>
            <li><strong class="text-slate-800 dark:text-slate-200">Hiérarchique</strong> — Validation par le responsable de structure (organigramme).</li>
            <li><strong class="text-slate-800 dark:text-slate-200">Par rôle</strong> — Seuls les utilisateurs ayant le rôle indiqué peuvent valider.</li>
            <li><strong class="text-slate-800 dark:text-slate-200">Par fonction</strong> — Seuls les agents ayant la fonction sélectionnée peuvent valider.</li>
            <li><strong class="text-slate-800 dark:text-slate-200">Utilisateur</strong> — Un validateur précis est désigné pour cette étape.</li>
            <li><strong class="text-slate-800 dark:text-slate-200">Destinataire libre</strong> — Le déposant choisit librement le validateur à l’envoi.</li>
        </ul>
    </aside>
</div>

@php
    $defaultCircuitEtapes = [
        ['nom' => '', 'mode' => 'hierarchique', 'role_requis' => '', 'fonction_requise_id' => '', 'validateur_id' => ''],
    ];
    $circuitEtapesInit = old('etapes', $defaultCircuitEtapes);
@endphp

<script>
function circuitWorkflow() {
    return {
        scope: @json(old('cible_scope', 'global')),
        prefixeCode: @json(old('prefixe_code', 'validation')),
        etapes: (function() {
            const raw = @json($circuitEtapesInit);
            return raw.map((e, i) => ({ id: i + 1, ...e }));
        })(),
        etapeId: 100,
        addEtape() {
            this.etapes.push({
                id: ++this.etapeId,
                nom: '',
                mode: 'hierarchique',
                role_requis: '',
                fonction_requise_id: '',
                validateur_id: ''
            });
        },
        removeEtape(index) {
            this.etapes.splice(index, 1);
        },
        validateCircuit(e) {
            if (this.etapes.length === 0) {
                e.preventDefault();
                alert('Ajoutez au moins une étape au circuit.');
                return false;
            }
            const emptyNom = this.etapes.some(et => !et.nom || !et.nom.trim());
            if (emptyNom) {
                e.preventDefault();
                alert('Toutes les étapes doivent avoir un nom.');
                return false;
            }
            const invalidRole = this.etapes.some(et => et.mode === 'role' && !et.role_requis);
            const invalidFonction = this.etapes.some(et => et.mode === 'fonction' && !et.fonction_requise_id);
            const invalidUser = this.etapes.some(et => et.mode === 'utilisateur' && !et.validateur_id);
            if (invalidRole || invalidFonction || invalidUser) {
                e.preventDefault();
                alert('Pour chaque étape « Par rôle », « Par fonction » ou « Utilisateur », sélectionnez la valeur requise.');
                return false;
            }
        }
    };
}
</script>
@endsection
