@extends('layouts.app')
@use('App\Support\ReturnUrl')
@section('content-container-class', 'w-full px-4 sm:px-6 lg:px-8')
@section('page-title', 'Corriger le courrier n° '.$courrier->numeroRegistreComplet())
@section('page-title-info', $courrier->estArrivee() ? 'Correction de l’enregistrement d’arrivée' : 'Correction avant retransmission au directeur')

@php
    $field = 'w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/25 focus:border-emerald-500 transition-shadow';
    $label = 'block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1.5';
@endphp

@section('content')
@include('partials.flash-session', ['class' => 'mb-5'])

@if($errors->any())
<div x-data="{ show: true }" x-show="show" x-transition class="mb-5 p-4 rounded-2xl bg-red-50 dark:bg-red-900/20 border border-red-200/80 dark:border-red-800 text-red-800 dark:text-red-200 flex items-start gap-4 shadow-sm" role="alert">
    <span class="flex-shrink-0 w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/50 flex items-center justify-center text-red-600 dark:text-red-400 font-bold" aria-hidden="true">!</span>
    <ul class="flex-1 list-disc list-inside text-sm font-medium space-y-0.5">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
    <button type="button" @click="show = false" class="flex-shrink-0 w-8 h-8 rounded-lg hover:bg-red-200/50 dark:hover:bg-red-800/30 flex items-center justify-center text-lg font-bold transition-colors" title="Fermer" aria-label="Fermer">×</button>
</div>
@endif

<form method="post" action="{{ route('courriers.update', $courrier) }}" enctype="multipart/form-data" class="w-full space-y-5" data-loading-text="Enregistrement...">
    @csrf
    @method('PUT')

    <section class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/40">
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Informations générales</h2>
            <p class="text-xs text-slate-500 mt-0.5">
                @if($courrier->estArrivee())
                Corrigez une erreur de saisie sans relancer le circuit.
                @else
                Corrigez avant de retransmettre au directeur.
                @endif
            </p>
        </div>
        <div class="p-5 space-y-4">
            <div>
                <label class="{{ $label }}">Objet <span class="text-red-500 normal-case tracking-normal">*</span></label>
                <input type="text" name="objet" value="{{ old('objet', $courrier->objet) }}" required class="{{ $field }}">
                @error('objet')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">Type</label>
                    <select name="type_courrier_id" id="type_courrier_id" class="{{ $field }}">
                        <option value="">—</option>
                        @foreach($types as $t)
                        @php
                            $necessiteServiceDemandeur = in_array($t->code, ['facture', 'mad'], true);
                            $telephoneObligatoire = in_array($t->code, ['facture', 'demande'], true);
                            $necessiteMontantFacture = $t->code === 'facture';
                        @endphp
                        <option
                            value="{{ $t->id }}"
                            data-code="{{ $t->code }}"
                            data-service-demandeur="{{ $necessiteServiceDemandeur ? '1' : '0' }}"
                            data-telephone-requis="{{ $telephoneObligatoire ? '1' : '0' }}"
                            data-montant-facture="{{ $necessiteMontantFacture ? '1' : '0' }}"
                            @selected(old('type_courrier_id', $courrier->type_courrier_id) == $t->id)
                        >{{ $t->libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $label }}">Priorité</label>
                    <select name="priorite_courrier_id" class="{{ $field }}">
                        <option value="">Normale</option>
                        @foreach($priorites as $p)<option value="{{ $p->id }}" @selected(old('priorite_courrier_id', $courrier->priorite_courrier_id) == $p->id)>{{ $p->libelle }}</option>@endforeach
                    </select>
                </div>
            </div>

            @if($courrier->estArrivee())
            @if(isset($directions) && $directions->isNotEmpty())
            <div id="bloc-service-demandeur" class="hidden">
                <label class="{{ $label }}">Service demandeur <span class="text-red-500 normal-case tracking-normal">*</span></label>
                <select name="service_demandeur_structure_id" id="service_demandeur_structure_id" class="{{ $field }}">
                    <option value="">— Choisir une direction —</option>
                    @foreach($directions as $direction)
                    <option value="{{ $direction->id }}" @selected(old('service_demandeur_structure_id', $courrier->service_demandeur_structure_id) == $direction->id)>{{ $direction->nom }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-500 mt-1.5">Direction ou antenne départementale à l’origine de la demande.</p>
                @error('service_demandeur_structure_id')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
            </div>
            @endif

            <div id="bloc-montant-facture" class="{{ ($courrier->typeCourrier?->code === 'facture') ? '' : 'hidden' }}">
                @include('partials.input-montant-fcfa', [
                    'name' => 'montant_facture',
                    'id' => 'montant_facture',
                    'label' => 'Montant de la facture (FCFA)',
                    'labelClass' => $label,
                    'required' => $courrier->typeCourrier?->code === 'facture',
                    'class' => $field,
                    'placeholder' => 'Ex. : 1 500 000',
                    'value' => old('montant_facture', $courrier->montant_facture),
                ])
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">Date de réception</label>
                    <input type="date" name="date_reception" value="{{ old('date_reception', $courrier->date_reception?->format('Y-m-d')) }}" class="{{ $field }}">
                    @error('date_reception')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="{{ $label }}">Date du courrier</label>
                    <input type="date" name="date_courrier" value="{{ old('date_courrier', $courrier->date_courrier?->format('Y-m-d')) }}" class="{{ $field }}">
                    @error('date_courrier')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
                </div>
            </div>

            <div id="bloc-fournisseur-prestataire" class="{{ ($courrier->typeCourrier?->code === 'facture') ? '' : 'hidden' }}">
                <label class="{{ $label }}">Fournisseur ou prestataire <span class="text-red-500 normal-case tracking-normal">*</span></label>
                <select name="fournisseur_prestataire_id" id="select-fournisseur-prestataire" class="{{ $field }}">
                    <option value="">— Choisir dans le référentiel —</option>
                    @foreach(($fournisseursPrestataires ?? collect()) as $fp)
                    <option
                        value="{{ $fp->id }}"
                        data-nom="{{ $fp->nom }}"
                        data-email="{{ $fp->email }}"
                        data-telephone="{{ $fp->telephone }}"
                        data-telephone-2="{{ $fp->telephone_2 }}"
                        data-notifier-telephone="{{ $fp->notifier_telephone ? '1' : '0' }}"
                        data-notifier-telephone-2="{{ $fp->notifier_telephone_2 ? '1' : '0' }}"
                        @selected((int) old('fournisseur_prestataire_id', $courrier->fournisseur_prestataire_id) === (int) $fp->id)
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

            <div id="bloc-expediteur-libre" class="{{ ($courrier->typeCourrier?->code === 'facture') ? 'hidden' : '' }}">
                <label class="{{ $label }}" id="label-expediteur">Expéditeur</label>
                <input type="text" name="expediteur_libelle" id="input-expediteur" value="{{ old('expediteur_libelle', $courrier->expediteur_libelle) }}" class="{{ $field }}" placeholder="Organisme ou personne émettrice" @disabled($courrier->typeCourrier?->code === 'facture')>
                @error('expediteur_libelle')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
            </div>

            <div id="bloc-contacts-expediteur" class="space-y-4">
                <div>
                    <label class="{{ $label }}">E-mail expéditeur <span class="text-slate-400 normal-case tracking-normal font-medium">(optionnel)</span></label>
                    <input type="email" name="expediteur_email" id="input-expediteur-email" value="{{ old('expediteur_email', $courrier->expediteur_email) }}" class="{{ $field }}" placeholder="contact@exemple.cg">
                    @error('expediteur_email')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $label }}" id="label-telephone-expediteur">
                            Téléphone 1
                            <span id="asterisque-telephone" class="text-red-500 normal-case tracking-normal hidden">*</span>
                            <span id="hint-telephone-optionnel" class="text-slate-400 normal-case tracking-normal font-medium">(optionnel, SMS)</span>
                        </label>
                        <input type="text" name="expediteur_telephone" id="input-expediteur-telephone" value="{{ old('expediteur_telephone', $courrier->expediteur_telephone) }}" class="{{ $field }}" placeholder="+24206…">
                        <p id="aide-telephone" class="text-xs text-slate-500 mt-1.5 hidden">Obligatoire pour facture / demande (SMS ou mail à la validation).</p>
                        @error('expediteur_telephone')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
                        <label class="inline-flex items-center gap-2 mt-2 text-xs font-medium text-slate-600 dark:text-slate-300">
                            <input type="hidden" name="expediteur_notifier_telephone" value="0">
                            <input type="checkbox" name="expediteur_notifier_telephone" id="input-expediteur-notifier-telephone" value="1"
                                   @checked(old('expediteur_notifier_telephone', $courrier->expediteur_notifier_telephone ?? true))
                                   class="rounded border-slate-300 text-emerald-600">
                            Notifier ce numéro
                        </label>
                    </div>
                    <div>
                        <label class="{{ $label }}">Téléphone 2 <span class="text-slate-400 normal-case tracking-normal font-medium">(optionnel)</span></label>
                        <input type="text" name="expediteur_telephone_2" id="input-expediteur-telephone-2" value="{{ old('expediteur_telephone_2', $courrier->expediteur_telephone_2) }}" class="{{ $field }}" placeholder="+24206…">
                        @error('expediteur_telephone_2')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
                        <label class="inline-flex items-center gap-2 mt-2 text-xs font-medium text-slate-600 dark:text-slate-300">
                            <input type="hidden" name="expediteur_notifier_telephone_2" value="0">
                            <input type="checkbox" name="expediteur_notifier_telephone_2" id="input-expediteur-notifier-telephone-2" value="1"
                                   @checked(old('expediteur_notifier_telephone_2', $courrier->expediteur_notifier_telephone_2 ?? true))
                                   class="rounded border-slate-300 text-emerald-600">
                            Notifier ce numéro
                        </label>
                    </div>
                </div>
            </div>

            <div>
                <label class="{{ $label }}">N° registre <span class="text-red-500 normal-case tracking-normal">*</span></label>
                <input type="text" name="numero_fulgurant" value="{{ old('numero_fulgurant', $courrier->numero_fulgurant) }}" required class="{{ $field }}" placeholder="Ex. 45/2026 ou 192/2026/DAF/SAGP">
                <p class="text-xs text-slate-500 mt-1.5">Numéro porté au registre papier du secrétariat.</p>
                @error('numero_fulgurant')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="{{ $label }}">Référence document</label>
                <input type="text" name="reference" value="{{ old('reference', $courrier->reference) }}" class="{{ $field }}">
                @error('reference')<p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>@enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">Nombre de pièces</label>
                    <input type="number" name="nombre_pieces" min="0" value="{{ old('nombre_pieces', $courrier->nombre_pieces) }}" class="{{ $field }}">
                </div>
                <div>
                    <label class="{{ $label }}">N° archives</label>
                    <input type="text" name="numero_archives" value="{{ old('numero_archives', $courrier->numero_archives) }}" class="{{ $field }}">
                </div>
            </div>

            <div>
                <label class="{{ $label }}">Observations</label>
                <textarea name="observations" rows="3" class="{{ $field }}">{{ old('observations', $courrier->observations) }}</textarea>
            </div>
            @else
            <div>
                <label class="{{ $label }}">Date du courrier</label>
                <input type="date" name="date_courrier" value="{{ old('date_courrier', $courrier->date_courrier?->format('Y-m-d')) }}" class="{{ $field }}">
            </div>
            <p class="text-sm text-slate-600 dark:text-slate-400 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50/80 px-4 py-3">
                Le destinataire sera choisi après validation du directeur.
            </p>
            @endif
        </div>
    </section>

    @if($courrier->estArrivee())
    <section class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/40">
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Scans du courrier</h2>
            <p class="text-xs text-slate-500 mt-0.5">
                Cochez « Retirer » pour remplacer une pièce erronée, puis importez le bon fichier.
            </p>
        </div>
        <div class="p-5 space-y-4">
            @if($courrier->documents->isNotEmpty())
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2.5">
                @foreach($courrier->documents as $doc)
                @php
                    $ext = strtolower((string) ($doc->extension ?: pathinfo((string) $doc->nom_original, PATHINFO_EXTENSION)));
                    $estImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                    $estPdf = $ext === 'pdf';
                    $libelle = $doc->titre ?: $doc->nom_original;
                    $urlApercu = route('courriers.documents.apercu', [$courrier, $doc]);
                @endphp
                <article class="rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50/80 dark:bg-slate-900/40 overflow-hidden flex flex-col"
                         x-data="{ retirer: {{ collect(old('documents_a_retirer', []))->contains($doc->id) ? 'true' : 'false' }} }">
                    <div class="relative bg-slate-100 dark:bg-slate-950/50 h-28 sm:h-32 flex items-center justify-center"
                         :class="retirer ? 'opacity-40 grayscale' : ''">
                        @if($estImage)
                        <a href="{{ $urlApercu }}" target="_blank" rel="noopener" class="block w-full h-full" title="Ouvrir l’aperçu">
                            <img src="{{ $urlApercu }}" alt="{{ $libelle }}" class="w-full h-full object-contain p-2">
                        </a>
                        @elseif($estPdf)
                        <iframe src="{{ $urlApercu }}#toolbar=0&navpanes=0" class="w-full h-full bg-white" title="Aperçu {{ $libelle }}"></iframe>
                        @else
                        <div class="flex flex-col items-center gap-2 text-slate-400 px-4 text-center">
                            <span class="text-xs font-medium">Aperçu indisponible</span>
                        </div>
                        @endif
                    </div>
                    <div class="px-3 py-2 border-t border-slate-200 dark:border-slate-600 space-y-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="truncate text-xs font-medium text-slate-700 dark:text-slate-200 flex-1" title="{{ $libelle }}">{{ $libelle }}</span>
                            <a href="{{ $urlApercu }}" target="_blank" rel="noopener"
                               class="shrink-0 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300 no-underline hover:underline">
                                Ouvrir
                            </a>
                        </div>
                        <label class="inline-flex items-center gap-2 text-[11px] font-semibold text-red-700 dark:text-red-300 cursor-pointer">
                            <input type="checkbox" name="documents_a_retirer[]" value="{{ $doc->id }}"
                                   class="rounded border-slate-300 text-red-600 focus:ring-red-500"
                                   x-model="retirer">
                            Retirer (erreur / remplacement)
                        </label>
                    </div>
                </article>
                @endforeach
            </div>
            @endif

            @include('courriers.partials.scans-upload-preview', [
                'scansRequired' => $courrier->documents->isEmpty(),
                'scansInputId' => 'fichier-scan-edit',
                'scansLabel' => $courrier->documents->isNotEmpty()
                    ? 'Ajouter un ou plusieurs fichiers'
                    : 'Choisir un ou plusieurs fichiers',
            ])
            @error('fichiers')<p class="text-sm text-red-600 mt-2 text-center">{{ $message }}</p>@enderror
            @error('fichiers.*')<p class="text-sm text-red-600 mt-2 text-center">{{ $message }}</p>@enderror
            @error('fichier')<p class="text-sm text-red-600 mt-2 text-center">{{ $message }}</p>@enderror
            @error('documents_a_retirer')<p class="text-sm text-red-600 mt-2 text-center">{{ $message }}</p>@enderror
            @error('documents_a_retirer.*')<p class="text-sm text-red-600 mt-2 text-center">{{ $message }}</p>@enderror
        </div>
    </section>
    @endif

    <div class="flex flex-wrap items-center gap-3 pb-6">
        <button type="submit" data-loading-text="Enregistrement..." class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 shadow-sm transition-colors">
            Enregistrer les corrections
        </button>
        <a href="{{ route('courriers.show', ReturnUrl::propagate($courrier, ReturnUrl::validated(request()->query('return')))) }}" class="inline-flex items-center px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 text-sm font-semibold no-underline text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
            Annuler
        </a>
    </div>
</form>

@if($courrier->estArrivee())
@push('scripts')
@include('courriers.partials.scans-upload-preview-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var typeSelect = document.getElementById('type_courrier_id');
    var blocService = document.getElementById('bloc-service-demandeur');
    var selectService = document.getElementById('service_demandeur_structure_id');
    var blocMontantFacture = document.getElementById('bloc-montant-facture');
    var inputMontantFacture = document.getElementById('montant_facture');
    var inputTelephone = document.getElementById('input-expediteur-telephone');
    var inputTelephone2 = document.getElementById('input-expediteur-telephone-2');
    var inputNotifierTel = document.getElementById('input-expediteur-notifier-telephone');
    var inputNotifierTel2 = document.getElementById('input-expediteur-notifier-telephone-2');
    var asterisqueTel = document.getElementById('asterisque-telephone');
    var hintTelOptionnel = document.getElementById('hint-telephone-optionnel');
    var aideTelephone = document.getElementById('aide-telephone');
    var blocFournisseur = document.getElementById('bloc-fournisseur-prestataire');
    var selectFournisseur = document.getElementById('select-fournisseur-prestataire');
    var blocExpediteurLibre = document.getElementById('bloc-expediteur-libre');
    var inputExpediteur = document.getElementById('input-expediteur');
    var inputEmail = document.getElementById('input-expediteur-email');

    if (!typeSelect) return;

    function setBlocVisible(bloc, visible) {
        if (!bloc) return;
        bloc.classList.toggle('hidden', !visible);
        bloc.querySelectorAll('input, select, textarea').forEach(function (el) {
            el.disabled = !visible;
            if (!visible && el.type !== 'file' && el.id !== 'input-expediteur') {
                // Ne pas vider l’expéditeur libre au toggle (conserve old/valeur courrier).
            }
        });
    }

    function appliquerContactsDepuisFiche(forceContacts) {
        if (!selectFournisseur || selectFournisseur.disabled) return;
        var opt = selectFournisseur.options[selectFournisseur.selectedIndex];
        if (!opt || !opt.value) return;
        if (inputExpediteur) inputExpediteur.value = opt.getAttribute('data-nom') || '';
        if (forceContacts) {
            if (inputEmail) inputEmail.value = opt.getAttribute('data-email') || '';
            if (inputTelephone) inputTelephone.value = opt.getAttribute('data-telephone') || '';
            if (inputTelephone2) inputTelephone2.value = opt.getAttribute('data-telephone-2') || '';
            if (inputNotifierTel) inputNotifierTel.checked = opt.getAttribute('data-notifier-telephone') !== '0';
            if (inputNotifierTel2) inputNotifierTel2.checked = opt.getAttribute('data-notifier-telephone-2') !== '0';
            return;
        }
        if (inputEmail && !inputEmail.value) inputEmail.value = opt.getAttribute('data-email') || '';
        if (inputTelephone && !inputTelephone.value) inputTelephone.value = opt.getAttribute('data-telephone') || '';
        if (inputTelephone2 && !inputTelephone2.value) inputTelephone2.value = opt.getAttribute('data-telephone-2') || '';
    }

    function synchroniserSelonType() {
        var opt = typeSelect.options[typeSelect.selectedIndex];
        var code = opt ? (opt.getAttribute('data-code') || '') : '';
        var necessiteService = opt && opt.getAttribute('data-service-demandeur') === '1';
        var telRequis = opt && opt.getAttribute('data-telephone-requis') === '1';
        var necessiteMontant = opt && opt.getAttribute('data-montant-facture') === '1';
        var estFacture = code === 'facture';

        if (!code) {
            necessiteService = false;
            telRequis = false;
            necessiteMontant = false;
            estFacture = false;
        }

        if (blocService) {
            blocService.classList.toggle('hidden', !necessiteService);
            if (selectService) {
                selectService.required = !!necessiteService;
                selectService.disabled = !necessiteService;
                if (!necessiteService) {
                    selectService.value = '';
                }
            }
        }

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

        setBlocVisible(blocFournisseur, estFacture);
        setBlocVisible(blocExpediteurLibre, !estFacture);
        if (selectFournisseur) {
            selectFournisseur.required = estFacture;
            selectFournisseur.disabled = !estFacture;
        }
        if (inputExpediteur) {
            inputExpediteur.disabled = estFacture;
        }
        if (estFacture) {
            appliquerContactsDepuisFiche(false);
        }

        if (inputTelephone) {
            inputTelephone.required = !!telRequis;
        }
        if (asterisqueTel) {
            asterisqueTel.classList.toggle('hidden', !telRequis);
        }
        if (hintTelOptionnel) {
            hintTelOptionnel.classList.toggle('hidden', !!telRequis);
        }
        if (aideTelephone) {
            aideTelephone.classList.toggle('hidden', !telRequis);
        }
    }

    if (selectFournisseur) {
        selectFournisseur.addEventListener('change', function () {
            appliquerContactsDepuisFiche(true);
        });
    }
    typeSelect.addEventListener('change', synchroniserSelonType);
    synchroniserSelonType();
});
</script>
@endpush
@endif
@endsection
