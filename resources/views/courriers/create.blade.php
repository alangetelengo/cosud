@extends('layouts.app')
@section('content-container-class', 'w-full px-4 sm:px-6 lg:px-8')
@section('page-title', 'Nouveau courrier — '.($sensCode === 'depart' ? 'départ' : 'arrivée'))
@section('page-title-info', $sensCode === 'depart' ? 'Brouillon à transmettre au directeur' : 'Enregistrement au registre d’arrivée')

@php
    $field = 'w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/25 focus:border-emerald-500 transition-shadow';
    $label = 'block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1.5';
@endphp

@section('content')
@if($errors->any())
<div class="mb-5 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 text-sm">
    <p class="font-semibold mb-1">Veuillez corriger les erreurs ci-dessous.</p>
    <ul class="list-disc list-inside text-xs space-y-0.5">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start pb-6">
    {{-- Bloc formulaire --}}
    <div class="lg:col-span-8 min-w-0">
        <form method="post" action="{{ route('courriers.store') }}" enctype="multipart/form-data" class="w-full space-y-5" data-loading-text="Enregistrement...">
            @csrf
            <input type="hidden" name="sens" value="{{ $sensCode }}">

            @include('courriers.partials.form-create-identification', compact('field', 'label', 'types', 'priorites', 'sensCode', 'directions'))

            @if($sensCode === 'arrivee')
            <section class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/40">
                    <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Correspondance</h2>
                    <p id="correspondance-sous-titre" class="text-xs text-slate-500 mt-0.5">Dates, expéditeur et références</p>
                </div>
                <div class="p-5 space-y-4">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="{{ $label }}">Date de réception</label>
                            <input type="date" name="date_reception" value="{{ old('date_reception', now()->toDateString()) }}" class="{{ $field }}">
                        </div>
                        <div>
                            <label class="{{ $label }}">Date du courrier</label>
                            <input type="date" name="date_courrier" value="{{ old('date_courrier') }}" class="{{ $field }}">
                        </div>
                    </div>

                    <div id="bloc-fournisseur-prestataire" class="hidden">
                        <label class="{{ $label }}">Fournisseur ou prestataire <span class="text-red-500 normal-case tracking-normal">*</span></label>
                        <select name="fournisseur_prestataire_id" id="select-fournisseur-prestataire" class="{{ $field }}"
                                data-placeholder="— Choisir dans le référentiel —">
                            <option value="">— Choisir dans le référentiel —</option>
                            @foreach(($fournisseursPrestataires ?? collect()) as $fp)
                            <option
                                value="{{ $fp->id }}"
                                data-nom="{{ $fp->nom }}"
                                data-email="{{ $fp->email }}"
                                data-telephone="{{ $fp->telephone }}"
                                @selected((int) old('fournisseur_prestataire_id') === (int) $fp->id)
                            >{{ $fp->nom }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-slate-500 mt-1.5">
                            Obligatoire pour une facture.
                            @can('create', App\Models\FournisseurPrestataire::class)
                            <a href="{{ route('fournisseurs-prestataires.create') }}" target="_blank" class="text-emerald-600 font-semibold no-underline hover:underline">Ajouter une fiche</a>
                            @endcan
                        </p>
                        @error('fournisseur_prestataire_id')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
                    </div>

                    <div id="bloc-expediteur-libre">
                        <label class="{{ $label }}" id="label-expediteur">Expéditeur</label>
                        <input type="text" name="expediteur_libelle" id="input-expediteur" value="{{ old('expediteur_libelle') }}" class="{{ $field }}" placeholder="Organisme ou personne émettrice">
                        @error('expediteur_libelle')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
                    </div>

                    <div id="bloc-contacts-expediteur" class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="{{ $label }}">E-mail expéditeur <span class="text-slate-400 normal-case tracking-normal font-medium">(optionnel)</span></label>
                            <input type="email" name="expediteur_email" id="input-expediteur-email" value="{{ old('expediteur_email') }}" class="{{ $field }}" placeholder="contact@exemple.cg">
                            <p class="text-xs text-slate-500 mt-1.5">Pour informer l’expéditeur à la validation / clôture.</p>
                            @error('expediteur_email')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">
                                Téléphone expéditeur
                                <span id="asterisque-telephone" class="text-red-500 normal-case tracking-normal hidden">*</span>
                                <span id="hint-telephone-optionnel" class="text-slate-400 normal-case tracking-normal font-medium">(optionnel)</span>
                            </label>
                            <input type="text" name="expediteur_telephone" id="input-expediteur-telephone" value="{{ old('expediteur_telephone') }}" class="{{ $field }}" placeholder="+24206…">
                            <p id="aide-telephone" class="text-xs text-slate-500 mt-1.5">SMS / notification à la validation ou signature DG.</p>
                            @error('expediteur_telephone')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label class="{{ $label }}">N° registre <span class="text-red-500 normal-case tracking-normal">*</span></label>
                        <input type="text" name="numero_fulgurant" id="input-numero-registre" value="{{ old('numero_fulgurant') }}" required class="{{ $field }}" placeholder="Ex. 45/2026 ou 192/2026/DAF/SAGP">
                        <p class="text-xs text-slate-500 mt-1.5">Numéro porté au registre papier du secrétariat (saisie libre).</p>
                        @error('numero_fulgurant')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="{{ $label }}" id="label-reference">Référence document</label>
                        <input type="text" name="reference" id="input-reference" value="{{ old('reference') }}" class="{{ $field }}" placeholder="">
                        <p id="aide-reference" class="text-xs text-slate-500 mt-1.5"></p>
                        @error('reference')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/40">
                    <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Scans du courrier</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Un ou plusieurs PDF / images — obligatoire pour une arrivée externe</p>
                </div>
                <div class="p-5">
                    <label class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-200 dark:border-slate-600 bg-slate-50/50 dark:bg-slate-900/30 px-4 py-8 cursor-pointer hover:border-emerald-400 hover:bg-emerald-50/30 dark:hover:bg-emerald-900/10 transition-colors">
                        <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Choisir un ou plusieurs fichiers <span class="text-red-500">*</span></span>
                        <span class="text-xs text-slate-500">PDF, JPG, PNG — max. 10 Mo par fichier</span>
                        <input type="file" name="fichiers[]" accept=".pdf,.jpg,.jpeg,.png" multiple required class="sr-only" id="fichier-scan"
                               onchange="(function(input){var n=input.files?.length||0;document.getElementById('fichier-scan-name').textContent=n===0?'Aucun fichier choisi':(n===1?input.files[0].name:(n+' fichiers sélectionnés'));})(this)">
                    </label>
                    <p id="fichier-scan-name" class="mt-2 text-xs text-slate-500 text-center">Aucun fichier choisi</p>
                    @error('fichiers')<p class="text-sm text-red-600 mt-2 text-center">{{ $message }}</p>@enderror
                    @error('fichiers.*')<p class="text-sm text-red-600 mt-2 text-center">{{ $message }}</p>@enderror
                    @error('fichier')<p class="text-sm text-red-600 mt-2 text-center">{{ $message }}</p>@enderror
                </div>
            </section>
            @else
            <section class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/40">
                    <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Départ interne</h2>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="{{ $label }}">Date du courrier</label>
                        <input type="date" name="date_courrier" value="{{ old('date_courrier', now()->toDateString()) }}" class="{{ $field }}">
                    </div>
                    <p class="text-sm text-slate-600 dark:text-slate-400 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50/80 dark:bg-slate-900/30 px-4 py-3">
                        Le <strong>secrétariat destinataire</strong> sera choisi après validation du directeur de votre direction.
                    </p>
                </div>
            </section>

            <section class="rounded-2xl border border-sky-200 dark:border-sky-800 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-sky-100 dark:border-sky-900/50 bg-sky-50/80 dark:bg-sky-900/20">
                    <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Parapheur départ</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Pièces de rédaction prêtes ou en cours de validation</p>
                </div>
                <div class="p-5 space-y-3">
                    <label class="{{ $label }}">Pièces à joindre</label>
                    @if($documentsParapheur->isEmpty())
                    <p class="text-sm text-slate-500 italic py-2">Aucune pièce dans le parapheur. Déposez une nouvelle pièce ci-dessous.</p>
                    @else
                    <select name="document_ids[]" multiple class="{{ $field }} min-h-[140px]">
                        @foreach($documentsParapheur as $doc)
                        <option value="{{ $doc->id }}" @selected(collect(old('document_ids', []))->contains($doc->id))>
                            {{ $doc->titre ?: $doc->nom_original }} — {{ $doc->typeDocument?->libelle ?? 'Type' }} ({{ $doc->statutDocument?->libelle ?? $doc->statut }})
                        </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-500">Maintenez Ctrl pour sélectionner plusieurs documents.</p>
                    @endif
                    @error('document_ids')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </section>

            <section class="rounded-2xl border border-emerald-200 dark:border-emerald-800 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-emerald-100 dark:border-emerald-900/40 bg-emerald-50/70 dark:bg-emerald-900/20">
                    <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Déposer une nouvelle pièce</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Rangée dans Mes dossiers → Courriers départ, et jointe automatiquement</p>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="{{ $label }}">Type de pièce</label>
                        <select name="nouveau_type_document_id" class="{{ $field }}">
                            <option value="">— Choisir un type —</option>
                            @foreach($typesDocumentParapheur as $td)
                            <option value="{{ $td->id }}" @selected(old('nouveau_type_document_id') == $td->id)>{{ $td->libelle }}</option>
                            @endforeach
                        </select>
                        @error('nouveau_type_document_id')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">Fichier(s)</label>
                        <input type="file" name="nouveaux_fichiers[]" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" multiple class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-emerald-600 file:text-white file:font-semibold file:text-sm hover:file:bg-emerald-700">
                        @error('nouveaux_fichiers')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
                        @error('nouveaux_fichiers.*')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>
            @endif

            <div class="flex flex-wrap items-center gap-3 pt-1">
                <button type="submit" data-loading-text="Enregistrement..." class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 shadow-sm transition-colors">
                    Enregistrer au registre
                </button>
                <a href="{{ $retourUrl }}" class="inline-flex items-center px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 text-sm font-semibold no-underline text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                    Annuler
                </a>
            </div>
        </form>
    </div>

    {{-- Bloc aide --}}
    <div class="lg:col-span-4 lg:sticky lg:top-24">
        @include('courriers.partials.aide-create', ['sensCode' => $sensCode])
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var typeSelect = document.getElementById('type_courrier_id');
    var circuitInfo = document.getElementById('circuit-du-type');
    var blocService = document.getElementById('bloc-service-demandeur');
    var selectService = document.getElementById('service_demandeur_structure_id');
    var blocMontantFacture = document.getElementById('bloc-montant-facture');
    var inputMontantFacture = document.getElementById('montant_facture');
    var blocContacts = document.getElementById('bloc-contacts-expediteur');
    var labelExpediteur = document.getElementById('label-expediteur');
    var inputExpediteur = document.getElementById('input-expediteur');
    var labelReference = document.getElementById('label-reference');
    var inputReference = document.getElementById('input-reference');
    var aideReference = document.getElementById('aide-reference');
    var sousTitre = document.getElementById('correspondance-sous-titre');
    var aideDefaut = document.getElementById('aide-arrivee-defaut');
    var aideFacture = document.getElementById('aide-arrivee-facture');
    var aideMad = document.getElementById('aide-arrivee-mad');
    var inputTelephone = document.getElementById('input-expediteur-telephone');
    var asterisqueTel = document.getElementById('asterisque-telephone');
    var hintTelOptionnel = document.getElementById('hint-telephone-optionnel');
    var blocFournisseur = document.getElementById('bloc-fournisseur-prestataire');
    var selectFournisseur = document.getElementById('select-fournisseur-prestataire');
    var blocExpediteurLibre = document.getElementById('bloc-expediteur-libre');
    var inputEmail = document.getElementById('input-expediteur-email');

    if (!typeSelect || !circuitInfo) return;

    function setBlocVisible(bloc, visible) {
        if (!bloc) return;
        bloc.classList.toggle('hidden', !visible);
        bloc.querySelectorAll('input, select, textarea').forEach(function (el) {
            el.disabled = !visible;
            if (!visible && el.type !== 'file') {
                el.value = '';
            }
        });
    }

    function appliquerContactsDepuisFiche() {
        if (!selectFournisseur || selectFournisseur.disabled) return;
        var opt = selectFournisseur.options[selectFournisseur.selectedIndex];
        if (!opt || !opt.value) return;
        if (inputExpediteur) inputExpediteur.value = opt.getAttribute('data-nom') || '';
        if (inputEmail && !inputEmail.value) inputEmail.value = opt.getAttribute('data-email') || '';
        if (inputTelephone && !inputTelephone.value) inputTelephone.value = opt.getAttribute('data-telephone') || '';
    }

    function profilPour(code) {
        if (code === 'mad') {
            return {
                sousTitre: 'Dates, émetteur et référence de la MAD / état de besoins',
                labelExpediteur: 'Émetteur',
                placeholderExpediteur: 'Ex. DAF / SAGP ou Direction départementale',
                labelReference: 'Référence document',
                placeholderReference: 'Ex. n° état de besoins si différent du n° registre',
                aideReference: 'Optionnel si le n° registre suffit (ex. 192/2026/DAF/SAGP saisi ci-dessus).',
            };
        }
        if (code === 'facture') {
            return {
                sousTitre: 'Dates, fournisseur et références de la facture',
                labelExpediteur: 'Fournisseur / prestataire',
                placeholderExpediteur: 'Raison sociale du fournisseur',
                labelReference: 'Référence facture',
                placeholderReference: 'N° facture fournisseur',
                aideReference: 'Numéro figurant sur la facture (distinct du n° registre secrétariat).',
            };
        }
        if (code === 'demande') {
            return {
                sousTitre: 'Dates, demandeur et références',
                labelExpediteur: 'Demandeur',
                placeholderExpediteur: 'Nom / organisme du demandeur',
                labelReference: 'Référence document',
                placeholderReference: '',
                aideReference: '',
            };
        }
        return {
            sousTitre: 'Dates, expéditeur et références',
            labelExpediteur: 'Expéditeur',
            placeholderExpediteur: 'Organisme ou personne émettrice',
            labelReference: 'Référence document',
            placeholderReference: '',
            aideReference: '',
        };
    }

    function afficherAide(code) {
        if (aideDefaut) aideDefaut.classList.toggle('hidden', code === 'facture' || code === 'mad');
        if (aideFacture) aideFacture.classList.toggle('hidden', code !== 'facture');
        if (aideMad) aideMad.classList.toggle('hidden', code !== 'mad');
    }

    function afficherCircuitDuType() {
        var opt = typeSelect.options[typeSelect.selectedIndex];
        var code = opt ? (opt.getAttribute('data-code') || '') : '';
        var libelle = opt ? opt.getAttribute('data-circuit-libelle') : '';
        circuitInfo.textContent = libelle ? 'Circuit de traitement : ' + libelle : '';

        var necessite = opt && opt.getAttribute('data-service-demandeur') === '1';
        if (blocService) {
            blocService.classList.toggle('hidden', !necessite);
            if (selectService) {
                selectService.required = !!necessite;
                selectService.disabled = !necessite;
                if (!necessite) {
                    selectService.value = '';
                }
            }
        }

        var necessiteMontant = opt && opt.getAttribute('data-montant-facture') === '1';
        if (blocMontantFacture) {
            blocMontantFacture.classList.toggle('hidden', !necessiteMontant);
            if (inputMontantFacture) {
                inputMontantFacture.required = !!necessiteMontant;
                inputMontantFacture.disabled = !necessiteMontant;
                if (!necessiteMontant) {
                    inputMontantFacture.value = '';
                }
            }
        }

        var estFacture = code === 'facture';
        setBlocVisible(blocFournisseur, estFacture);
        setBlocVisible(blocExpediteurLibre, !estFacture);
        if (selectFournisseur) {
            selectFournisseur.required = estFacture;
        }
        if (estFacture) {
            appliquerContactsDepuisFiche();
        }

        var contacts = !opt || opt.getAttribute('data-contacts') !== '0';
        var telRequis = opt && opt.getAttribute('data-telephone-requis') === '1';
        if (!code) {
            contacts = true;
            telRequis = false;
        }
        setBlocVisible(blocContacts, contacts);

        if (inputTelephone) {
            inputTelephone.required = !!(contacts && telRequis);
        }
        if (asterisqueTel) {
            asterisqueTel.classList.toggle('hidden', !(contacts && telRequis));
        }
        if (hintTelOptionnel) {
            hintTelOptionnel.classList.toggle('hidden', !!(contacts && telRequis));
        }

        var profil = profilPour(code);
        if (sousTitre) sousTitre.textContent = profil.sousTitre;
        if (labelExpediteur) labelExpediteur.textContent = profil.labelExpediteur;
        if (inputExpediteur) inputExpediteur.placeholder = profil.placeholderExpediteur;
        if (labelReference) labelReference.textContent = profil.labelReference;
        if (inputReference) inputReference.placeholder = profil.placeholderReference;
        if (aideReference) aideReference.textContent = profil.aideReference;

        afficherAide(code);
    }

    if (selectFournisseur) {
        selectFournisseur.addEventListener('change', appliquerContactsDepuisFiche);
    }
    typeSelect.addEventListener('change', afficherCircuitDuType);
    afficherCircuitDuType();
});
</script>
@endpush
@endsection
