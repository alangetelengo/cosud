@extends('layouts.app')
@section('content-container-class', 'w-full px-4 sm:px-6 lg:px-8')
@section('page-title', 'Courrier n° '.$courrier->numeroRegistreComplet())

@php
    $hasFil = isset($filCourriers) && $filCourriers->count() >= 1;
    $hasOrientations = $courrier->orientations->isNotEmpty();
    $hasTransmissions = $courrier->transmissions->isNotEmpty();
    $hasVentilations = $courrier->ventilationDestinataires->isNotEmpty();
    $hasCircuit = (bool) $courrier->circuit_courrier_id;
    $defaultTab = $hasCircuit ? 'historique' : ($hasFil ? 'fil' : ($hasOrientations ? 'orientations' : ($hasTransmissions ? 'transmissions' : 'historique')));

    // Un utilisateur à accès total (DG, admin) garde le droit d'agir à tout moment sur le circuit
    // (cf. CircuitCourrierMoteurService::peutAgir), mais ce n'est pas pour autant « son tour » : on
    // distingue ici l'acteur réellement attendu par l'étape en cours pour adapter l'affichage des
    // actions « Modifier » / « Créer courrier réponse » et éviter une action prématurée par erreur.
    $moteurCircuitActions = app(\App\Services\CircuitCourrierMoteurService::class);
    $etapeCircuitActuelle = $hasCircuit
        && $courrier->circuit
        && $moteurCircuitActions->circuitCompatibleAvecSens($courrier->circuit, $courrier->sensCourrier?->code)
        ? $courrier->circuitEtapeActuelle
        : null;
    $estActeurCircuitActuel = ! $etapeCircuitActuelle
        || $moteurCircuitActions->userCorrespondActeur(auth()->user(), $etapeCircuitActuelle, $courrier);
    $libelleActeurCircuitActuel = $etapeCircuitActuelle
        ? $moteurCircuitActions->libelleActeurPour($courrier, $etapeCircuitActuelle)
        : null;

    // Chemin A (particulière) : préparer le courrier de réponse et le transmettre pour signature.
    $peutSoumettreReponse = $etapeCircuitActuelle?->code === 'traitement_particuliere';
    // DG : signe (ou rejette) le courrier de réponse déjà créé.
    $peutStatuerSurReponse = $etapeCircuitActuelle?->code === 'validation_reponse_dg' && $estActeurCircuitActuel;
    $reponseEnAttenteSignature = $peutStatuerSurReponse || $etapeCircuitActuelle?->code === 'validation_reponse_dg'
        ? $courrier->reponseDepartEnAttenteSignature()
        : null;
    $peutExpedierReponseCircuit = $etapeCircuitActuelle?->code === 'expedition_reponse'
        && $estActeurCircuitActuel;
    $reponseSigneeAExpedier = $peutExpedierReponseCircuit
        ? $courrier->reponseDepartSigneeEnAttenteExpedition()
        : null;
    // Chemin B : le DG peut créer et signer lui-même la réponse uniquement tant qu’il
    // n’a pas encore instruit le dossier (étape d’instructions).
    $peutCreerReponseDirecte = $hasCircuit
        && auth()->user()->aAccesTotal()
        && $courrier->statutCourrier->code !== 'cloture'
        && in_array($etapeCircuitActuelle?->code, [
            'instruction_dg',
            'instructions_dg',
        ], true);
    // AC : envoi du chèque au DG (message + montant) — avance vers signature DG.
    $peutEnvoyerChequeAc = $etapeCircuitActuelle?->code === 'ac_etablit_cheque'
        && $estActeurCircuitActuel
        && $courrier->statutCourrier->code !== 'cloture';
    // DG : confirme la signature (sans scan) — renvoi à l’AC.
    $peutSignerChequeDg = $etapeCircuitActuelle?->code === 'dg_signe_cheque'
        && $estActeurCircuitActuel
        && $courrier->statutCourrier->code !== 'cloture';
    // AC : bordereau + pièces à la décharge bénéficiaire.
    $peutEnregistrerDechargeAc = $etapeCircuitActuelle?->code === 'preuve_paiement'
        && $estActeurCircuitActuel
        && $courrier->statutCourrier->code !== 'cloture';
    // Eleni : contrôle + confirmation de clôture.
    $peutConfirmerControleDepense = $etapeCircuitActuelle?->code === 'cloture_depenses'
        && $estActeurCircuitActuel
        && $courrier->statutCourrier->code !== 'cloture';
    $peutDeposerPreuvePaiement = $peutEnregistrerDechargeAc;

    // Formulaire de soumission ouvert d’emblée pour l’actrice attendue.
    $ouvrirFormulaireSoumettreReponse = $peutSoumettreReponse
        && ($estActeurCircuitActuel || $errors->has('document_reponse'));
    $formInitial = $ouvrirFormulaireSoumettreReponse
        ? 'soumettre-reponse'
        : ($errors->has('motif_rejet') ? 'rejeter-reponse' : null);
@endphp

@section('content')
<div class="w-full" x-data="{ tab: '{{ $defaultTab }}', form: @js($formInitial) }">
    @include('partials.flash-session', ['class' => 'mb-4'])

    <div class="flex flex-col lg:flex-row gap-5 items-start">
        {{-- COLONNE GAUCHE --}}
        <div class="flex-1 min-w-0 space-y-4 order-2 lg:order-1">
            {{-- En-tête + métadonnées --}}
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <span class="px-2.5 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 font-semibold text-xs">{{ $courrier->sensCourrier->libelle }}</span>
                        <span class="px-2.5 py-0.5 rounded-md bg-sky-100 dark:bg-sky-900/40 text-sky-800 dark:text-sky-200 text-xs font-semibold">{{ $courrier->statutCourrier->libelle }}</span>
                        <span class="px-2.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs">{{ ucfirst($courrier->origine) }}</span>
                        @if($courrier->reference)
                        <span class="text-xs text-slate-500">Réf. {{ $courrier->reference }}</span>
                        @endif
                    </div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white leading-snug">{{ $courrier->objet }}</h2>
                </div>

                <div class="p-5">
                    <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        @if($courrier->estArrivee())
                        <div>
                            <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Expéditeur</dt>
                            <dd class="font-medium text-slate-800 dark:text-slate-100 mt-0.5">{{ $courrier->expediteur_libelle ?? $courrier->structureExpediteur?->nom ?? '—' }}</dd>
                        </div>
                        @if($courrier->estOrigineExterne() && ($courrier->expediteur_email || $courrier->expediteur_telephone))
                        <div>
                            <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Contact expéditeur</dt>
                            <dd class="mt-0.5 text-slate-700 dark:text-slate-200">
                                @if($courrier->expediteur_email)<div>{{ $courrier->expediteur_email }}</div>@endif
                                @if($courrier->expediteur_telephone)<div>{{ $courrier->expediteur_telephone }}</div>@endif
                            </dd>
                        </div>
                        @endif
                        <div>
                            <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">N° fulgurant</dt>
                            <dd class="mt-0.5 text-slate-700 dark:text-slate-200">{{ $courrier->numero_fulgurant ?? '—' }}</dd>
                        </div>
                        @if($courrier->serviceDemandeurStructure)
                        <div>
                            <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Service demandeur</dt>
                            <dd class="mt-0.5 text-slate-700 dark:text-slate-200">{{ $courrier->serviceDemandeurStructure->nom }}</dd>
                        </div>
                        @endif
                        <div>
                            <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Date réception</dt>
                            <dd class="mt-0.5 text-slate-700 dark:text-slate-200">{{ $courrier->date_reception?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                        @if($courrier->courrierDepartSource)
                        <div>
                            <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Courrier départ source</dt>
                            <dd class="mt-0.5"><a href="{{ route('courriers.show', $courrier->courrierDepartSource) }}" class="text-emerald-600 no-underline font-medium">n° {{ $courrier->courrierDepartSource->numeroRegistreComplet() }}</a></dd>
                        </div>
                        @endif
                        @else
                        <div>
                            <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Destinataire</dt>
                            <dd class="font-medium text-slate-800 dark:text-slate-100 mt-0.5">
                                @if($courrier->structureDestinataire || $courrier->destinataire_libelle)
                                    {{ $courrier->structureDestinataire?->nom ?? $courrier->destinataire_libelle }}
                                @elseif(in_array($courrier->statutCourrier->code, ['brouillon', 'rejete_directeur', 'transmis_directeur', 'signe'], true))
                                    <span class="text-slate-500 italic font-normal">À définir après validation du directeur</span>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Date expédition</dt>
                            <dd class="mt-0.5 text-slate-700 dark:text-slate-200">{{ $courrier->date_expedition?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                        @if($courrier->directeurEnAttente)
                        <div>
                            <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Directeur en attente</dt>
                            <dd class="mt-0.5 text-slate-700 dark:text-slate-200">{{ $courrier->directeurEnAttente->name }}</dd>
                        </div>
                        @endif
                        @if($courrier->signataire)
                        <div>
                            <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Signé par</dt>
                            <dd class="mt-0.5 text-slate-700 dark:text-slate-200">{{ $courrier->signataire->name }}</dd>
                        </div>
                        @endif
                        @if($courrier->courrierParent)
                        <div>
                            <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Réponse à</dt>
                            <dd class="mt-0.5"><a href="{{ route('courriers.show', $courrier->courrierParent) }}" class="text-emerald-600 no-underline font-medium">Arrivée n° {{ $courrier->courrierParent->numeroRegistreComplet() }}</a></dd>
                        </div>
                        @endif
                        @endif
                        <div>
                            <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Créé par</dt>
                            <dd class="mt-0.5 text-slate-700 dark:text-slate-200">{{ $courrier->createur?->name }}</dd>
                        </div>
                        @if($courrier->nombre_pieces)
                        <div>
                            <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Pièces</dt>
                            <dd class="mt-0.5 text-slate-700 dark:text-slate-200">{{ $courrier->nombre_pieces }}</dd>
                        </div>
                        @endif
                        @if($courrier->numero_archives)
                        <div>
                            <dt class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">N° archives</dt>
                            <dd class="mt-0.5 text-slate-700 dark:text-slate-200">{{ $courrier->numero_archives }}</dd>
                        </div>
                        @endif
                    </dl>

                    @if($courrier->motif_rejet)
                    <div class="mt-4 p-3 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 text-sm"><strong>Motif de rejet :</strong> {{ $courrier->motif_rejet }}</div>
                    @endif
                    @if($courrier->instructions_dg)
                    <div class="mt-4 p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 text-sm">
                        <strong>Orientation direction :</strong> {{ $courrier->instructions_dg }}
                        @if($courrier->agentsConfies->isNotEmpty() || $courrier->agentConfie)
                            <div class="text-[11px] text-amber-800/80 mt-1">Confié à : <strong>{{ implode(', ', $courrier->libellesAgentsConfies()) }}</strong></div>
                        @endif
                        @if($courrier->est_confidentiel)
                            <span class="ml-2 inline-flex px-1.5 py-0.5 rounded bg-rose-100 text-rose-800 text-[10px] font-bold uppercase">Confidentiel</span>
                        @endif
                        @if($courrier->orientation_mode === 'via_particuliere')
                            <div class="text-[11px] text-amber-800/80 mt-1">Mode : préparation de réponse par la particulière.</div>
                        @endif
                    </div>
                    @endif
                    @if($courrier->message_ac)
                    <div class="mt-4 p-3 rounded-xl bg-sky-50 dark:bg-sky-900/20 border border-sky-200 text-sm">
                        <strong>Message AC :</strong> {{ $courrier->message_ac }}
                    </div>
                    @endif
                    @if($courrier->suiviPaiement)
                    <div class="mt-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 text-sm">
                        <strong>{{ $courrier->suiviPaiement->libelleType() }} n° {{ $courrier->suiviPaiement->numeroComplet() }}</strong>
                        <span class="block text-slate-600 dark:text-slate-300 mt-1">Montant : {{ number_format((float) $courrier->suiviPaiement->montant, 0, ',', ' ') }} FCFA</span>
                    </div>
                    @endif
                    @if($courrier->observations)
                    <div class="mt-4 p-3 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm"><strong>Observations :</strong> {{ $courrier->observations }}</div>
                    @endif
                    @if($courrier->courrierParent)
                    <div class="mt-4 p-3 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 text-sm">
                        <strong>Réponse au courrier :</strong>
                        <a href="{{ route('courriers.show', $courrier->courrierParent) }}" class="text-emerald-600 no-underline font-medium">
                            Arrivée n° {{ $courrier->courrierParent->numeroRegistreComplet() }} — {{ $courrier->courrierParent->objet }}
                        </a>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Pièces jointes --}}
            @if($courrier->documents->isNotEmpty())
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-700/30 flex items-center justify-between">
                    <h3 class="font-semibold text-sm text-slate-800 dark:text-slate-100">Pièces jointes</h3>
                    <span class="text-xs text-slate-500">{{ $courrier->documents->count() }}</span>
                </div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach($courrier->documents as $doc)
                    <li class="px-5 py-2.5 flex items-center gap-2 text-sm">
                        <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                        @can('view', $doc)
                        <a href="{{ route('documents.fiche', $doc) }}" class="text-emerald-600 font-medium no-underline truncate">{{ $doc->nom_original }}</a>
                        @else
                        <span class="text-slate-500 truncate">{{ $doc->nom_original }} (accès restreint)</span>
                        @endcan
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif

            {{-- Onglets suivi --}}
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
                <div class="px-2 pt-2 border-b border-slate-100 dark:border-slate-700 flex flex-wrap gap-1">
                    @if($hasCircuit)
                    <button type="button" @click="tab = 'historique'"
                        :class="tab === 'historique' ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400' : 'border-transparent text-slate-500 hover:text-slate-700'"
                        class="px-3 py-2 text-xs font-semibold border-b-2 -mb-px transition-colors">Historique circuit</button>
                    @endif
                    @if($hasFil)
                    <button type="button" @click="tab = 'fil'"
                        :class="tab === 'fil' ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400' : 'border-transparent text-slate-500 hover:text-slate-700'"
                        class="px-3 py-2 text-xs font-semibold border-b-2 -mb-px transition-colors">Fil ({{ $filCourriers->count() }})</button>
                    @endif
                    @if($hasOrientations)
                    <button type="button" @click="tab = 'orientations'"
                        :class="tab === 'orientations' ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400' : 'border-transparent text-slate-500 hover:text-slate-700'"
                        class="px-3 py-2 text-xs font-semibold border-b-2 -mb-px transition-colors">Orientations</button>
                    @endif
                    @if($hasTransmissions)
                    <button type="button" @click="tab = 'transmissions'"
                        :class="tab === 'transmissions' ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400' : 'border-transparent text-slate-500 hover:text-slate-700'"
                        class="px-3 py-2 text-xs font-semibold border-b-2 -mb-px transition-colors">Transmissions</button>
                    @endif
                    @if($hasVentilations)
                    <button type="button" @click="tab = 'ventilations'"
                        :class="tab === 'ventilations' ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400' : 'border-transparent text-slate-500 hover:text-slate-700'"
                        class="px-3 py-2 text-xs font-semibold border-b-2 -mb-px transition-colors">Ventilations</button>
                    @endif
                </div>

                <div class="p-4">
                    @if($hasCircuit)
                    <div x-show="tab === 'historique'" x-cloak>
                        @include('courriers.partials.historique-circuit')
                    </div>
                    @endif

                    @if($hasFil)
                    <div x-show="tab === 'fil'" x-cloak>
                        @include('courriers.partials.fil-historique')
                    </div>
                    @endif

                    @if($hasOrientations)
                    <div x-show="tab === 'orientations'" x-cloak>
                        <ul class="text-sm space-y-2">
                            @foreach($courrier->orientations as $o)
                            <li class="flex flex-col gap-0.5 py-2 border-b border-slate-100 dark:border-slate-700 last:border-0">
                                <span class="font-medium text-slate-800 dark:text-slate-200">
                                    {{ $o->libelleDestinataire() }}
                                    @if($o->structure) — {{ $o->structure->nom }}@endif
                                    @if($o->destinataireUser) ({{ $o->destinataireUser->name }})@endif
                                </span>
                                <span class="text-slate-500 text-xs">{{ $o->orientePar?->name }} · {{ $o->created_at->format('d/m/Y') }}</span>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    @if($hasTransmissions)
                    <div x-show="tab === 'transmissions'" x-cloak>
                        <ul class="text-sm space-y-2">
                            @foreach($courrier->transmissions as $t)
                            <li class="py-2 border-b border-slate-100 dark:border-slate-700 last:border-0">
                                {{ $t->date_transmission->format('d/m/Y H:i') }} — vers {{ $t->versStructure?->nom ?? $t->versUser?->name ?? '—' }}
                                @if($t->accuse_reception) <span class="text-emerald-600 font-semibold">(AR)</span>@endif
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    @if($hasVentilations)
                    <div x-show="tab === 'ventilations'" x-cloak>
                        <ul class="text-sm space-y-2">
                            @foreach($courrier->ventilationDestinataires as $v)
                            <li class="py-2 border-b border-slate-100 dark:border-slate-700 last:border-0">
                                {{ $v->user?->name }} — {{ $v->document?->nom_original ?? 'tout le courrier' }}
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    @if(! $hasCircuit && ! $hasFil && ! $hasOrientations && ! $hasTransmissions && ! $hasVentilations)
                    <p class="text-sm text-slate-500 py-2">Aucun suivi complémentaire pour ce courrier.</p>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap gap-4 text-sm pb-2">
                <a href="{{ route('courriers.index', ['sens' => $courrier->sensCourrier->code]) }}" class="text-emerald-600 font-semibold no-underline">← Retour à la liste</a>
                @if(auth()->user()->gereCourrierSecretariat())
                <a href="{{ route('courriers.a-recevoir') }}" class="text-sky-600 font-semibold no-underline">Courriers à réceptionner</a>
                @endif
            </div>
        </div>

        {{-- COLONNE DROITE : actions --}}
        <div class="w-full lg:w-96 shrink-0 space-y-4 lg:sticky lg:top-4 order-1 lg:order-2">
            @include('courriers.partials.circuit-suivi')

            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-700/30">
                    <h3 class="font-semibold text-sm text-slate-800 dark:text-slate-100">Actions</h3>
                </div>
                <div class="p-3 space-y-2">
                    @if($courrier->estArrivee())
                        @can('update', $courrier)
                        @if($courrier->statutCourrier->code === 'recu' && ! $courrier->circuit_courrier_id)
                        <form method="post" action="{{ route('courriers.parapheur', $courrier) }}">@csrf
                            <button type="button"
                                    onclick="flashAlert('Mettre ce courrier arrivée en parapheur pour instruction de la direction ? Il passera du statut « Reçu » à « En parapheur », en attente d’orientation.', this.closest('form'), {icon:'📁', danger:false, confirmText:'Mettre en parapheur', title:'Parapheur'})"
                                    class="w-full px-3 py-2 rounded-lg bg-slate-800 text-white text-xs font-semibold">
                                Mettre en parapheur
                            </button>
                        </form>
                        @endif
                        @endcan
                        @can('orienter', $courrier)
                        @if($courrier->statutCourrier->code === 'en_parapheur')
                        <button type="button"
                                @click="flashAlert('Ouvrir le formulaire d’orientation (instructions de la direction) ?', () => { form = 'orienter' }, {icon:'🧭', danger:false, confirmText:'Continuer', title:'Orientation'})"
                                class="w-full px-3 py-2 rounded-lg bg-amber-600 text-white text-xs font-semibold">Orienter (direction)</button>
                        @endif
                        @endcan
                        @can('ventiler', $courrier)
                        @if($courrier->statutCourrier->code === 'oriente')
                        <button type="button"
                                @click="flashAlert('Ouvrir le formulaire de ventilation des pièces ?', () => { form = 'ventiler' }, {icon:'📤', danger:false, confirmText:'Continuer', title:'Ventilation'})"
                                class="w-full px-3 py-2 rounded-lg bg-sky-600 text-white text-xs font-semibold">Ventiler (pièce seule)</button>
                        @endif
                        @endcan
                        @can('update', $courrier)
                        @if(! $hasCircuit)
                        @if($courrier->statutCourrier->code !== 'cloture' && $courrier->instructions_dg)
                        @php
                            $libelleReponse = $courrier->statutCourrier->code === 'attente_reponse_particuliere' ? 'Préparer l’élément de réponse' : 'Créer courrier réponse';
                        @endphp
                        @if($estActeurCircuitActuel)
                        <button type="button"
                                @click="flashAlert('Créer un courrier départ en réponse à celui-ci ? Le formulaire de saisie s’ouvrira ensuite.', () => { form = 'reponse' }, {icon:'✉️', danger:false, confirmText:'Continuer', title:'Courrier réponse'})"
                                class="w-full px-3 py-2 rounded-lg bg-indigo-600 text-white text-xs font-semibold">
                            {{ $libelleReponse }}
                        </button>
                        @else
                        <button type="button"
                                @click="flashAlert('Ce n’est pas votre tour dans le circuit : c’est actuellement à « {{ $libelleActeurCircuitActuel }} » d’agir. Continuer quand même à créer ce courrier réponse ?', () => { form = 'reponse' }, {icon:'⚠️', danger:true, confirmText:'Continuer quand même', title:'Pas votre tour'})"
                                class="w-full px-3 py-2 rounded-lg border border-dashed border-amber-300 dark:border-amber-700 text-xs font-semibold text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20">
                            {{ $libelleReponse }} <span class="opacity-70">(pas votre tour)</span>
                        </button>
                        @endif
                        @elseif($courrier->statutCourrier->code !== 'cloture')
                        <p class="text-[11px] text-slate-400 italic px-1">En attente des instructions du DG / directeur avant de pouvoir créer une réponse.</p>
                        @endif
                        @else
                        {{-- Circuit général : préparer réponse (= départ) → signature DG → expédition --}}
                        @if($peutSoumettreReponse)
                        @if(! $estActeurCircuitActuel && ! auth()->user()->aAccesTotal())
                        <button type="button"
                                @click="flashAlert('Ce n’est pas votre tour dans le circuit : c’est actuellement à « {{ $libelleActeurCircuitActuel }} » d’agir. Ouvrir le formulaire quand même ?', () => { form = 'soumettre-reponse' }, {icon:'⚠️', danger:true, confirmText:'Ouvrir le formulaire', title:'Pas votre tour'})"
                                class="w-full px-3 py-2 rounded-lg border border-dashed border-amber-300 dark:border-amber-700 text-xs font-semibold text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20">
                            Préparer la réponse <span class="opacity-70">(pas votre tour)</span>
                        </button>
                        @endif
                        @if($courrier->motif_rejet && $courrier->rejete_par_id)
                        <p class="text-[11px] rounded-lg border border-red-200 bg-red-50/80 dark:bg-red-900/20 text-red-700 dark:text-red-300 px-2.5 py-2 leading-snug">
                            <strong>Rejeté par {{ $courrier->rejetePar?->name }} :</strong> {{ $courrier->motif_rejet }}
                        </p>
                        @endif
                        <form x-show="form === 'soumettre-reponse'" x-cloak method="post" action="{{ route('courriers.circuit.soumettre-reponse', $courrier) }}" enctype="multipart/form-data" class="space-y-2 {{ $estActeurCircuitActuel ? '' : 'pt-1 border-t border-slate-100' }}">
                            @csrf
                            <p class="text-[11px] font-semibold text-slate-700 dark:text-slate-200">Courrier de réponse à transmettre pour signature</p>
                            <div>
                                <label class="block text-[11px] font-semibold mb-1">Document <span class="text-red-500">*</span></label>
                                <input type="file" name="document_reponse" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="block w-full text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-indigo-600 file:text-white file:font-semibold file:text-xs">
                                @error('document_reponse')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div class="rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-2.5 py-2">
                                <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold">Objet du courrier départ</p>
                                <p class="text-xs text-slate-800 dark:text-slate-100 mt-0.5 leading-snug">{{ $courrier->objetReponseDepartParDefaut() }}</p>
                                <p class="text-[10px] text-slate-500 mt-1 leading-snug">Dérivé automatiquement de l’arrivée — visible dans le registre départ.</p>
                            </div>
                            <p class="text-[11px] text-slate-500 leading-snug">
                                Le document devient le courrier de départ en attente de signature du DG. Le destinataire se choisit à l’expédition.
                                @if($courrier->est_confidentiel)
                                <span class="block mt-1 text-amber-700 dark:text-amber-300">🔒 Courrier confidentiel.</span>
                                @endif
                            </p>
                            <button type="button"
                                    onclick="flashAlert('Transmettre ce courrier de réponse au DG pour signature ?', this.closest('form'), {icon:'📎', danger:false, confirmText:'Transmettre', title:'Transmission pour signature'})"
                                    class="w-full px-3 py-2 rounded-lg bg-indigo-600 text-white text-xs font-semibold">
                                Transmettre pour signature
                            </button>
                        </form>
                        @elseif($peutStatuerSurReponse)
                        <div class="rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50/70 dark:bg-indigo-900/20 p-3 space-y-2">
                            <p class="text-xs font-semibold text-indigo-900 dark:text-indigo-200">Courrier de réponse en attente de signature</p>
                            @if($reponseEnAttenteSignature)
                            <a href="{{ route('courriers.show', $reponseEnAttenteSignature) }}" class="block text-xs text-indigo-700 dark:text-indigo-300 underline">
                                Voir le départ n° {{ $reponseEnAttenteSignature->numeroRegistreComplet() }} — {{ $reponseEnAttenteSignature->objet }}
                            </a>
                            @php $docSign = $reponseEnAttenteSignature->documents->first(); @endphp
                            @if($docSign)
                            <a href="{{ route('documents.download', $docSign) }}" target="_blank" class="block text-xs text-indigo-700 dark:text-indigo-300 underline truncate">📎 {{ $docSign->titre ?: $docSign->nom_original }}</a>
                            @endif
                            @endif
                            <p class="text-[11px] text-slate-500 leading-snug">Signez pour autoriser l’expédition, ou rejetez avec un motif.</p>
                            <form method="post" action="{{ route('courriers.circuit.valider-reponse', $courrier) }}">
                                @csrf
                                <button type="button"
                                        onclick="flashAlert('Signer ce courrier de réponse ? La particulière pourra ensuite l’expédier.', this.closest('form'), {icon:'✓', danger:false, confirmText:'Signer', title:'Signature de la réponse'})"
                                        class="w-full px-3 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold">Signer la réponse</button>
                            </form>
                            <button type="button"
                                    @click="flashAlert('Rejeter cette réponse ? Indiquez le motif à la particulière.', () => { form = 'rejeter-reponse' }, {icon:'↩️', danger:true, confirmText:'Continuer', title:'Rejet de la réponse'})"
                                    class="w-full px-3 py-2 rounded-lg border border-red-300 text-red-700 dark:text-red-300 text-xs font-semibold">Rejeter</button>
                        </div>
                        @elseif($peutExpedierReponseCircuit)
                        <div class="rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50/70 dark:bg-emerald-900/20 p-3 space-y-2">
                            <p class="text-xs font-semibold text-emerald-900 dark:text-emerald-200">Réponse signée — à expédier</p>
                            @if($reponseSigneeAExpedier)
                            <p class="text-[11px] text-slate-600 dark:text-slate-300 leading-snug">
                                Le DG a signé le courrier n° {{ $reponseSigneeAExpedier->numeroRegistreComplet() }}.
                                Expédiez-le vers le secrétariat destinataire (dernière étape).
                            </p>
                            <a href="{{ route('courriers.show', $reponseSigneeAExpedier) }}"
                               class="block w-full text-center px-3 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold no-underline">
                                Ouvrir le départ pour l’expédier
                            </a>
                            @else
                            <p class="text-[11px] text-amber-700">Aucun départ signé trouvé — vérifiez le fil du courrier.</p>
                            @endif
                        </div>
                        @elseif(in_array($etapeCircuitActuelle?->code, ['instruction_dg', 'instructions_dg'], true))
                        @if($estActeurCircuitActuel)
                        <p class="text-[11px] text-slate-500 italic px-1 leading-snug">
                            <strong>A — Instruire :</strong> utilisez le panneau « Circuit métier » (instructions ± collaborateur, sans pièce).
                        </p>
                        @else
                        <p class="text-[11px] text-slate-400 italic px-1">En attente des instructions du DG / directeur.</p>
                        @endif
                        @elseif($peutEnvoyerChequeAc)
                        <div class="rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50/70 dark:bg-emerald-900/20 p-3 space-y-2">
                            <p class="text-xs font-semibold text-emerald-900 dark:text-emerald-200">Envoyer le chèque au DG</p>
                            @if($courrier->instructions_dg)
                            <p class="text-[11px] text-slate-600 dark:text-slate-300 leading-snug"><strong>Instructions DG :</strong> {{ $courrier->instructions_dg }}</p>
                            @endif
                            <form method="post" action="{{ route('courriers.circuit.envoyer-cheque', $courrier) }}" enctype="multipart/form-data" class="space-y-2">
                                @csrf
                                @php
                                    $montantChequeAffiche = old('montant') !== null && old('montant') !== ''
                                        ? number_format((float) preg_replace('/\s+/', '', (string) old('montant')), 0, ',', ' ')
                                        : '';
                                @endphp
                                <div>
                                    <label class="block text-[11px] font-semibold mb-1">Montant du chèque (FCFA) <span class="text-red-500">*</span></label>
                                    <div
                                        x-data="{
                                            montant: @js($montantChequeAffiche),
                                            formatMontant(v) {
                                                const chiffres = String(v ?? '').replace(/\D/g, '');
                                                if (!chiffres) return '';
                                                return chiffres.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                                            }
                                        }"
                                    >
                                        <input
                                            type="text"
                                            name="montant"
                                            x-model="montant"
                                            @input="montant = formatMontant($event.target.value)"
                                            inputmode="numeric"
                                            autocomplete="off"
                                            required
                                            class="w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900"
                                            placeholder="Ex. : 1 949 700"
                                        >
                                    </div>
                                    @error('montant')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold mb-1">Message au DG <span class="text-red-500">*</span></label>
                                    <textarea name="message" required rows="3" class="w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900" placeholder="Ex. : Chèque établi, prêt pour signature…">{{ old('message') }}</textarea>
                                    @error('message')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <button type="button"
                                        onclick="flashAlert('Transmettre ce chèque au DG pour signature ? Le circuit avancera automatiquement.', this.closest('form'), {icon:'✓', danger:false, confirmText:'Envoyer au DG', title:'Envoi du chèque'})"
                                        class="w-full px-3 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold">
                                    Envoyer le chèque au DG
                                </button>
                            </form>
                        </div>
                        @elseif($peutSignerChequeDg)
                        <div class="rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50/70 dark:bg-indigo-900/20 p-3 space-y-2">
                            <p class="text-xs font-semibold text-indigo-900 dark:text-indigo-200">Confirmer la signature du chèque</p>
                            <p class="text-[11px] text-slate-500 leading-snug">Aucun scan dans le GED : le chèque est signé sur papier. Confirmez pour renvoyer le dossier à l’AC.</p>
                            @if($courrier->message_ac)
                            <p class="text-[11px] text-slate-600 dark:text-slate-300 leading-snug"><strong>Message AC :</strong> {{ $courrier->message_ac }}</p>
                            @endif
                            <form method="post" action="{{ route('courriers.circuit.signer-cheque', $courrier) }}" class="space-y-2">
                                @csrf
                                <div>
                                    <label class="block text-[11px] font-semibold mb-1">Message <span class="font-normal text-slate-400">(facultatif)</span></label>
                                    <textarea name="message" rows="2" class="w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900" placeholder="Note pour l’AC / le dossier…">{{ old('message') }}</textarea>
                                    @error('message')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <label class="flex items-start gap-2 text-[11px] text-slate-600 dark:text-slate-300">
                                    <input type="hidden" name="notifier_fournisseur" value="0">
                                    <input type="checkbox" name="notifier_fournisseur" value="1" class="mt-0.5" @checked(old('notifier_fournisseur', '1') === '1')>
                                    <span>Notifier le fournisseur / prestataire pour le recouvrement
                                        @if($courrier->expediteur_email)
                                        <span class="block text-slate-400">({{ $courrier->expediteur_email }})</span>
                                        @elseif($courrier->expediteur_libelle)
                                        <span class="block text-slate-400">({{ $courrier->expediteur_libelle }} — aucun e-mail renseigné)</span>
                                        @endif
                                    </span>
                                </label>
                                <button type="button"
                                        onclick="flashAlert('Confirmer que le chèque est signé et renvoyer le dossier à l’AC ?', this.closest('form'), {icon:'✍️', danger:false, confirmText:'Confirmer la signature', title:'Signature du chèque'})"
                                        class="w-full px-3 py-2 rounded-lg bg-indigo-600 text-white text-xs font-semibold">
                                    Chèque signé — renvoyer à l’AC
                                </button>
                            </form>
                        </div>
                        @elseif($peutEnregistrerDechargeAc)
                        @php
                            $suiviBordereau = $courrier->suiviPaiement;
                            $montantDechargeAffiche = old('montant', $suiviBordereau?->montant
                                ? number_format((float) $suiviBordereau->montant, 0, '', ' ')
                                : '');
                        @endphp
                        <div class="rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50/70 dark:bg-emerald-900/20 p-3 space-y-2">
                            <p class="text-xs font-semibold text-emerald-900 dark:text-emerald-200">Bordereau — décharge bénéficiaire</p>
                            <p class="text-[11px] text-slate-500 leading-snug">Enregistrez le paiement lorsque le bénéficiaire décharge le chèque (comme sur le bordereau de transmission).</p>
                            <form method="post" action="{{ route('courriers.circuit.deposer-preuve-paiement', $courrier) }}" enctype="multipart/form-data" class="space-y-2">
                                @csrf
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[11px] font-semibold mb-1">Date <span class="text-red-500">*</span></label>
                                        <input type="date" name="date_decharge" value="{{ old('date_decharge', now()->toDateString()) }}" required class="w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900">
                                        @error('date_decharge')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold mb-1">Banque <span class="text-red-500">*</span></label>
                                        <input type="text" name="banque" value="{{ old('banque', $suiviBordereau?->banque) }}" required class="w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900" placeholder="Ex. : BCH, BOA">
                                        @error('banque')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold mb-1">N° pièce <span class="text-red-500">*</span></label>
                                    <input type="text" name="numero_piece" value="{{ old('numero_piece', $suiviBordereau?->numero_piece) }}" required class="w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900" placeholder="Ex. : Chèque N° 0000312">
                                    @error('numero_piece')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold mb-1">Montant (FCFA) <span class="text-red-500">*</span></label>
                                    <div x-data="{
                                        montant: @js($montantDechargeAffiche),
                                        formatMontant(v) {
                                            const chiffres = String(v ?? '').replace(/\D/g, '');
                                            if (!chiffres) return '';
                                            return chiffres.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                                        }
                                    }">
                                        <input type="text" name="montant" x-model="montant" @input="montant = formatMontant($event.target.value)" inputmode="numeric" required class="w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900" placeholder="Ex. : 1 000 000">
                                    </div>
                                    @error('montant')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold mb-1">Bénéficiaire <span class="text-red-500">*</span></label>
                                    <input type="text" name="beneficiaire_libelle" value="{{ old('beneficiaire_libelle', $suiviBordereau?->beneficiaire_libelle ?: $suiviBordereau?->fournisseur_libelle ?: $courrier->expediteur_libelle) }}" required class="w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900">
                                    @error('beneficiaire_libelle')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold mb-1">Programmation <span class="font-normal text-slate-400">(facultatif)</span></label>
                                    <input type="text" name="programmation" value="{{ old('programmation', $suiviBordereau?->programmation) }}" class="w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900" placeholder="Ex. : du 14 juillet 2026">
                                    @error('programmation')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold mb-1">Pièces (chèque déchargé, identité…) <span class="text-red-500">*</span></label>
                                    <input type="file" name="preuves_paiement[]" required accept=".pdf,.jpg,.jpeg,.png" multiple class="block w-full text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:text-white file:font-semibold file:text-xs">
                                    @error('preuves_paiement')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    @error('preuves_paiement.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold mb-1">Observation <span class="font-normal text-slate-400">(facultatif)</span></label>
                                    <textarea name="observation" rows="2" class="w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900" placeholder="Remarque sur la décharge…">{{ old('observation') }}</textarea>
                                    @error('observation')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <button type="button"
                                        onclick="flashAlert('Enregistrer la décharge et notifier le suivi des dépenses ?', this.closest('form'), {icon:'✓', danger:false, confirmText:'Enregistrer', title:'Décharge bénéficiaire'})"
                                        class="w-full px-3 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold">
                                    Enregistrer la décharge / le paiement
                                </button>
                            </form>
                        </div>
                        @elseif($peutConfirmerControleDepense)
                        <div class="rounded-lg border border-sky-200 dark:border-sky-800 bg-sky-50/70 dark:bg-sky-900/20 p-3 space-y-2">
                            <p class="text-xs font-semibold text-sky-900 dark:text-sky-200">Contrôle de la dépense</p>
                            <p class="text-[11px] text-slate-500 leading-snug">Vérifiez les éléments saisis par l’AC avec les pièces physiques. Vous pouvez joindre des pièces complémentaires, puis confirmer la clôture.</p>
                            @if($courrier->suiviPaiement)
                            <div class="rounded-md bg-white/70 dark:bg-slate-900/40 border border-sky-100 dark:border-sky-900 px-2.5 py-2 text-[11px] text-slate-700 dark:text-slate-200 space-y-0.5">
                                <p><strong>N° pièce :</strong> {{ $courrier->suiviPaiement->numero_piece ?? '—' }}</p>
                                <p><strong>Montant :</strong> {{ $courrier->suiviPaiement->montant ? number_format((float) $courrier->suiviPaiement->montant, 0, ',', ' ') : '—' }}</p>
                                <p><strong>Banque :</strong> {{ $courrier->suiviPaiement->banque ?? '—' }}</p>
                                <p><strong>Bénéficiaire :</strong> {{ $courrier->suiviPaiement->beneficiaire_libelle ?? '—' }}</p>
                                <p><strong>Programmation :</strong> {{ $courrier->suiviPaiement->programmation ?? '—' }}</p>
                            </div>
                            @endif
                            <form method="post" action="{{ route('courriers.circuit.confirmer-controle-depense', $courrier) }}" enctype="multipart/form-data" class="space-y-2">
                                @csrf
                                <div>
                                    <label class="block text-[11px] font-semibold mb-1">Pièces complémentaires <span class="font-normal text-slate-400">(facultatif)</span></label>
                                    <input type="file" name="pieces_complementaires[]" accept=".pdf,.jpg,.jpeg,.png" multiple class="block w-full text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-sky-600 file:text-white file:font-semibold file:text-xs">
                                    @error('pieces_complementaires')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    @error('pieces_complementaires.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold mb-1">Commentaire <span class="font-normal text-slate-400">(facultatif)</span></label>
                                    <textarea name="message" rows="2" class="w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900" placeholder="Résultat du contrôle…">{{ old('message') }}</textarea>
                                    @error('message')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <button type="button"
                                        onclick="flashAlert('Confirmer le contrôle et clôturer le dossier ?', this.closest('form'), {icon:'✓', danger:false, confirmText:'Confirmer la clôture', title:'Contrôle dépense'})"
                                        class="w-full px-3 py-2 rounded-lg bg-sky-600 text-white text-xs font-semibold">
                                    Confirmer le contrôle et clôturer
                                </button>
                            </form>
                        </div>
                        @elseif($courrier->statutCourrier->code !== 'cloture')
                        <p class="text-[11px] text-slate-400 italic px-1">En attente de l’acteur de l’étape en cours.</p>
                        @endif
                        @if($peutCreerReponseDirecte)
                        <div class="pt-1 border-t border-slate-100 dark:border-slate-700 space-y-1.5">
                            @if(in_array($etapeCircuitActuelle?->code, ['instruction_dg', 'instructions_dg'], true))
                            <p class="text-[11px] text-slate-500 px-1 leading-snug">
                                <strong>B — Répondre moi-même :</strong> créer et signer une réponse (pièce obligatoire) — clôture le circuit.
                            </p>
                            @endif
                            <button type="button"
                                    @click="flashAlert('Établir et signer vous-même la réponse, avec les destinataires de votre choix ?', () => { form = 'reponse-directe' }, {icon:'✍️', danger:false, confirmText:'Continuer', title:'Répondre directement'})"
                                    class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                Répondre moi-même et signer
                            </button>
                        </div>
                        @endif
                        @endif
                        @endcan
                        @can('corriger', $courrier)
                        <div x-data="{ autresActions: false }" class="pt-1 border-t border-slate-100 dark:border-slate-700">
                            <button type="button" @click="autresActions = ! autresActions"
                                    class="w-full flex items-center gap-1 text-left text-[11px] text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 font-medium px-1 py-1.5">
                                <span x-text="autresActions ? '▾' : '▸'"></span> Autres actions
                            </button>
                            <div x-show="autresActions" x-cloak class="space-y-1.5 pb-1">
                                @if($estActeurCircuitActuel)
                                <a href="{{ route('courriers.edit', $courrier) }}" class="block w-full text-center px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-xs font-semibold no-underline text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Corriger l’enregistrement</a>
                                @else
                                <button type="button"
                                        @click="flashAlert('Ce n’est pas votre tour dans le circuit : c’est actuellement à « {{ $libelleActeurCircuitActuel }} » d’agir. Corriger l’enregistrement quand même ?', () => { window.location.href = '{{ route('courriers.edit', $courrier) }}' }, {icon:'⚠️', danger:true, confirmText:'Corriger quand même', title:'Pas votre tour'})"
                                        class="block w-full text-center px-3 py-2 rounded-lg border border-dashed border-amber-300 dark:border-amber-700 text-xs font-semibold text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20">
                                    Corriger l’enregistrement <span class="opacity-70">(pas votre tour)</span>
                                </button>
                                @endif
                                <p class="text-[10.5px] text-slate-400 italic px-1 leading-snug">Pour corriger une erreur de saisie (date, expéditeur, objet, pièces…). Cette action ne fait pas avancer le circuit.</p>
                            </div>
                        </div>
                        @endcan
                    @else
                        @if(in_array($courrier->statutCourrier->code, ['brouillon', 'rejete_directeur'], true))
                        @can('transmettreAuDirecteur', $courrier)
                        <div class="rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50/80 dark:bg-slate-900/30 p-3 space-y-2">
                            <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                                @if($directeurValidation)
                                Transmission au directeur
                                @if($directionEmettrice) de <strong>{{ $directionEmettrice->nom }}</strong>@endif :
                                <strong class="text-slate-900 dark:text-white">{{ $directeurValidation->name }}</strong>
                                @else
                                <span class="text-amber-700 dark:text-amber-300">Aucun directeur trouvé pour votre direction.</span>
                                @endif
                            </p>
                            <form method="post" action="{{ route('courriers.transmettre-directeur', $courrier) }}">@csrf
                                <button type="button"
                                        @disabled(! $directeurValidation)
                                        onclick="flashAlert('Transmettre ce courrier au directeur pour validation ?', this.closest('form'), {icon:'📨', danger:false, confirmText:'Transmettre', title:'Transmission au directeur'})"
                                        class="w-full px-3 py-2 rounded-lg bg-slate-800 text-white text-xs font-semibold disabled:opacity-50">
                                    {{ $courrier->statutCourrier->code === 'rejete_directeur' ? 'Retransmettre au directeur' : 'Transmettre au directeur' }}
                                </button>
                            </form>
                            @can('update', $courrier)
                            <a href="{{ route('courriers.edit', $courrier) }}" class="block w-full text-center px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-xs font-semibold no-underline text-slate-700 dark:text-slate-200">Modifier</a>
                            @endcan
                            @can('annuler', $courrier)
                            <button type="button"
                                    @click="flashAlert('Annuler ce courrier départ ?', () => { form = 'annuler-brouillon' }, {icon:'🗑️', danger:true, confirmText:'Continuer', title:'Annulation'})"
                                    class="w-full px-3 py-2 rounded-lg border border-red-300 text-red-700 text-xs font-semibold">Annuler le courrier</button>
                            @endcan
                        </div>
                        @endcan
                        @endif

                        @if($courrier->statutCourrier->code === 'transmis_directeur')
                        @can('signer', $courrier)
                        <form method="post" action="{{ route('courriers.signer', $courrier) }}">@csrf
                            <button type="button"
                                    onclick="flashAlert('Valider ce courrier pour envoi ? Le secrétariat pourra ensuite l’expédier.', this.closest('form'), {icon:'✓', danger:false, confirmText:'Valider', title:'Validation directeur'})"
                                    class="w-full px-3 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold">Valider pour envoi</button>
                        </form>
                        @endcan
                        @can('rejeter', $courrier)
                        <button type="button"
                                @click="flashAlert('Renvoyer ce courrier au secrétariat pour correction ?', () => { form = 'rejeter' }, {icon:'↩️', danger:true, confirmText:'Continuer', title:'Renvoi pour correction'})"
                                class="w-full px-3 py-2 rounded-lg bg-amber-600 text-white text-xs font-semibold">Renvoyer pour correction</button>
                        @endcan
                        @can('annuler', $courrier)
                        <button type="button"
                                @click="flashAlert('Annuler définitivement ce courrier ?', () => { form = 'annuler-directeur' }, {icon:'🗑️', danger:true, confirmText:'Continuer', title:'Annulation'})"
                                class="w-full px-3 py-2 rounded-lg bg-red-600 text-white text-xs font-semibold">Annuler le courrier</button>
                        @endcan
                        @endif

                        @can('expedierVersSecretariat', $courrier)
                        <div class="rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50/60 dark:bg-emerald-900/20 p-3 space-y-2">
                            <p class="text-xs text-slate-700 dark:text-slate-300">
                                @if($courrier->courrier_parent_id)
                                Réponse signée. Choisissez le secrétariat destinataire puis expédiez (dernière étape).
                                @else
                                Le directeur a validé ce courrier. Choisissez le secrétariat destinataire puis expédiez.
                                @endif
                            </p>
                            <form method="post" action="{{ route('courriers.expedier-interne', $courrier) }}" class="space-y-2">
                                @csrf
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Secrétariat destinataire <span class="text-red-500">*</span></label>
                                    <select name="structure_destinataire_id" required class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-2.5 py-1.5 text-xs dark:bg-slate-800">
                                        <option value="">— Choisir un secrétariat —</option>
                                        @foreach($secretariats as $s)
                                        <option value="{{ $s->id }}" @selected(old('structure_destinataire_id', $courrier->structure_destinataire_id) == $s->id)>{{ $s->nom }}</option>
                                        @endforeach
                                    </select>
                                    @error('structure_destinataire_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">N° archives <span class="text-slate-400 font-normal">(registre)</span></label>
                                    <input type="text" name="numero_archives" value="{{ old('numero_archives', $courrier->numero_archives) }}" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-2.5 py-1.5 text-xs dark:bg-slate-800" placeholder="Ex. DG/DEP/2026/001">
                                    @error('numero_archives')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Observations <span class="text-slate-400 font-normal">(registre)</span></label>
                                    <textarea name="observations" rows="2" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-2.5 py-1.5 text-xs dark:bg-slate-800" placeholder="Optionnel…">{{ old('observations', $courrier->observations) }}</textarea>
                                    @error('observations')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <button type="button"
                                        onclick="flashAlert('Expédier ce courrier vers le secrétariat destinataire choisi ?', this.closest('form'), {icon:'🚀', danger:false, confirmText:'Expédier', title:'Expédition'})"
                                        class="w-full px-3 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold">Expédier vers le secrétariat destinataire</button>
                            </form>
                        </div>
                        @endcan

                        @can('recevoir', $courrier)
                        <div class="rounded-lg border border-sky-200 dark:border-sky-800 bg-sky-50/70 dark:bg-sky-900/20 p-3 space-y-2">
                            <p class="text-xs text-slate-700 dark:text-slate-300">Ce courrier a été expédié vers votre secrétariat. Acceptez-le pour l’enregistrer en <strong>arrivée interne</strong>.</p>
                            <form method="post" action="{{ route('courriers.accepter-reception', $courrier) }}">
                                @csrf
                                <button type="button"
                                        onclick="flashAlert('Accepter la réception de ce courrier ? Un courrier arrivée interne sera créé.', this.closest('form'), {icon:'📥', danger:false, confirmText:'Accepter', title:'Réception'})"
                                        class="w-full px-3 py-2 rounded-lg bg-sky-600 text-white text-xs font-semibold">Accepter la réception</button>
                            </form>
                            <a href="{{ route('courriers.a-recevoir') }}" class="block w-full text-center px-3 py-2 rounded-lg border border-sky-300 text-sky-800 dark:text-sky-200 text-xs font-semibold no-underline">Voir les courriers à réceptionner</a>
                        </div>
                        @endcan

                        @can('update', $courrier)
                        @if($courrier->statutCourrier->code === 'reception_refusee')
                        <span class="block text-center px-3 py-2 rounded-lg bg-red-100 text-red-800 text-xs font-semibold">Réception refusée</span>
                        @endif
                        @endcan

                        @if($courrier->statutCourrier->code === 'annule')
                        <span class="block text-center px-3 py-2 rounded-lg bg-slate-200 text-slate-700 text-xs font-semibold">Courrier annulé</span>
                        @endif
                    @endif

                    @can('transmettre', $courrier)
                    <button type="button"
                            @click="flashAlert('Enregistrer une trace de transmission / accusé de réception ?', () => { form = 'transmettre' }, {icon:'📋', danger:false, confirmText:'Continuer', title:'Transmission'})"
                            class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-xs font-semibold text-slate-700 dark:text-slate-200">Transmission</button>
                    @endcan
                    @if($courrier->estDepart() && $courrier->statutCourrier?->code === 'expedie')
                    <p class="rounded-lg border border-emerald-200 bg-emerald-50/80 dark:bg-emerald-900/20 px-3 py-2 text-[11px] text-emerald-800 dark:text-emerald-200 leading-snug">
                        Courrier expédié — aucune action supplémentaire.
                    </p>
                    @endif
                    @can('archiver', $courrier)
                    <button type="button"
                            @click="flashAlert('Compléter les infos registre (n° archives, observations…) puis archiver ?', () => { form = 'archiver' }, {icon:'📦', danger:false, confirmText:'Continuer', title:'Archivage'})"
                            class="w-full px-3 py-2 rounded-lg border border-slate-400 text-xs font-semibold text-slate-700 dark:text-slate-200">Archiver</button>
                    @endcan

                    {{-- Formulaires dépliables --}}
                    @can('archiver', $courrier)
                    <form x-show="form === 'archiver'" x-cloak method="post" action="{{ route('courriers.archiver', $courrier) }}" class="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-700">
                        @csrf
                        <p class="text-[11px] text-slate-500 leading-snug">Ces infos apparaissent dans le registre papier{{ $courrier->estDepart() ? ' départ' : ' arrivée' }}.</p>
                        <div>
                            <label class="block text-[11px] font-semibold mb-1">Nombre de pièces</label>
                            <input type="number" name="nombre_pieces" min="0" max="9999" value="{{ old('nombre_pieces', $courrier->nombre_pieces) }}" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-2.5 py-1.5 text-xs dark:bg-slate-900" placeholder="Ex. 2">
                            @error('nombre_pieces')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold mb-1">N° archives</label>
                            <input type="text" name="numero_archives" value="{{ old('numero_archives', $courrier->numero_archives) }}" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-2.5 py-1.5 text-xs dark:bg-slate-900" placeholder="Ex. DG/DEP/2026/001">
                            @error('numero_archives')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold mb-1">Observations</label>
                            <textarea name="observations" rows="2" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-2.5 py-1.5 text-xs dark:bg-slate-900" placeholder="Optionnel…">{{ old('observations', $courrier->observations) }}</textarea>
                            @error('observations')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <button type="button"
                                onclick="flashAlert('Confirmer l’archivage ? Le courrier ne sera plus modifiable dans le circuit courant.', this.closest('form'), {icon:'📦', danger:false, confirmText:'Archiver', title:'Archivage'})"
                                class="w-full px-3 py-2 rounded-lg bg-slate-700 text-white text-xs font-semibold">Confirmer l’archivage</button>
                    </form>
                    @endcan

                    @can('rejeter', $courrier)
                    <form x-show="form === 'rejeter'" x-cloak method="post" action="{{ route('courriers.rejeter-depart', $courrier) }}" class="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-700">
                        @csrf
                        <p class="text-[11px] text-slate-500">Indiquez ce que le secrétariat doit corriger.</p>
                        <textarea name="motif_rejet" required rows="3" class="w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900" placeholder="Motif…"></textarea>
                        <button type="button"
                                onclick="flashAlert('Confirmer le renvoi pour correction ?', this.closest('form'), {icon:'↩️', danger:true, confirmText:'Renvoyer', title:'Renvoi'})"
                                class="w-full px-3 py-2 rounded-lg bg-amber-600 text-white text-xs font-semibold">Confirmer le renvoi</button>
                    </form>
                    @endcan

                    @can('annuler', $courrier)
                    @if(in_array($courrier->statutCourrier->code, ['brouillon', 'rejete_directeur'], true))
                    <form x-show="form === 'annuler-brouillon'" x-cloak method="post" action="{{ route('courriers.annuler', $courrier) }}" class="space-y-2 pt-2 border-t border-slate-100">
                        @csrf
                        <textarea name="motif_annulation" rows="2" class="w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900" placeholder="Motif (optionnel)…"></textarea>
                        <button type="button"
                                onclick="flashAlert('Confirmer l’annulation de ce courrier ?', this.closest('form'), {icon:'🗑️', danger:true, confirmText:'Annuler le courrier', title:'Annulation'})"
                                class="w-full px-3 py-2 rounded-lg bg-red-600 text-white text-xs font-semibold">Confirmer l’annulation</button>
                    </form>
                    @endif
                    @if($courrier->statutCourrier->code === 'transmis_directeur')
                    <form x-show="form === 'annuler-directeur'" x-cloak method="post" action="{{ route('courriers.annuler', $courrier) }}" class="space-y-2 pt-2 border-t border-slate-100">
                        @csrf
                        <textarea name="motif_annulation" required rows="3" class="w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900" placeholder="Motif d’annulation…"></textarea>
                        <button type="button"
                                onclick="flashAlert('Confirmer l’annulation définitive de ce courrier ?', this.closest('form'), {icon:'🗑️', danger:true, confirmText:'Annuler le courrier', title:'Annulation'})"
                                class="w-full px-3 py-2 rounded-lg bg-red-600 text-white text-xs font-semibold">Confirmer l’annulation</button>
                    </form>
                    @endif
                    @endcan

                    @can('orienter', $courrier)
                    <form x-show="form === 'orienter'" x-cloak method="post" action="{{ route('courriers.orienter', $courrier) }}"
                          x-data="{ mode: @js(old('orientation_mode', 'direct')), confidentiel: @json((bool) old('est_confidentiel')), destType: @js(old('destinataire_type', 'secretariat')) }"
                          class="space-y-2 pt-2 border-t border-slate-100">
                        @csrf
                        @include('courriers.partials.form-orientation', [
                            'compact' => true,
                            'field' => 'w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900',
                            'label' => 'block text-[11px] font-semibold mb-1',
                        ])
                        <button type="button"
                                onclick="flashAlert('Confirmer l’orientation / les instructions ?', this.closest('form'), {icon:'🧭', danger:false, confirmText:'Enregistrer', title:'Orientation'})"
                                class="w-full px-3 py-2 rounded-lg bg-amber-600 text-white text-xs font-semibold">Enregistrer</button>
                    </form>
                    @endcan

                    @can('ventiler', $courrier)
                    <form x-show="form === 'ventiler'" x-cloak method="post" action="{{ route('courriers.ventiler', $courrier) }}" class="space-y-2 pt-2 border-t border-slate-100">
                        @csrf
                        <p class="text-[11px] text-slate-500">Chaque destinataire ne verra que le document assigné.</p>
                        @foreach($courrier->documents as $i => $doc)
                        <div class="space-y-1 p-2 rounded-lg bg-slate-50 dark:bg-slate-900/40">
                            <input type="hidden" name="ventilations[{{ $i }}][document_id]" value="{{ $doc->id }}">
                            <div class="text-[11px] font-medium truncate">{{ $doc->nom_original }}</div>
                            <select name="ventilations[{{ $i }}][user_id]" class="w-full rounded-lg border px-2 py-1 text-xs dark:bg-slate-800">
                                <option value="">— Destinataire —</option>
                                @foreach($utilisateursVentilation as $u)<option value="{{ $u->id }}" title="{{ $u->name }}">{{ $u->libelleDestinataireCourrier() }}</option>@endforeach
                            </select>
                        </div>
                        @endforeach
                        <button type="button"
                                onclick="flashAlert('Confirmer la ventilation des pièces ?', this.closest('form'), {icon:'📤', danger:false, confirmText:'Ventiler', title:'Ventilation'})"
                                class="w-full px-3 py-2 rounded-lg bg-sky-600 text-white text-xs font-semibold">Ventiler</button>
                    </form>
                    @endcan

                    @can('update', $courrier)
                    @if($courrier->estArrivee() && $courrier->statutCourrier->code !== 'cloture')
                    @if(! $hasCircuit)
                    <form x-show="form === 'reponse'" x-cloak method="post" action="{{ route('courriers.creer-reponse', $courrier) }}" class="space-y-2 pt-2 border-t border-slate-100">
                        @csrf
                        @if($courrier->estOrigineInterne())
                        <p class="text-[11px] text-slate-500">Réponse interne vers <strong>{{ $courrier->structureExpediteur?->nom ?? $courrier->expediteur_libelle ?? 'la direction émettrice' }}</strong>.</p>
                        @else
                        <select name="structure_destinataire_id" required class="w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900">
                            <option value="">Destinataire de la réponse</option>
                            @foreach($secretariats as $s)<option value="{{ $s->id }}">{{ $s->nom }}</option>@endforeach
                        </select>
                        @endif
                        <div class="rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-2.5 py-2">
                            <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold">Objet du courrier départ</p>
                            <p class="text-xs text-slate-800 dark:text-slate-100 mt-0.5 leading-snug">{{ $courrier->objetReponseDepartParDefaut() }}</p>
                        </div>
                        <button type="button"
                                onclick="flashAlert('Confirmer la création du courrier départ en réponse ?', this.closest('form'), {icon:'✉️', danger:false, confirmText:'Créer', title:'Courrier réponse'})"
                                class="w-full px-3 py-2 rounded-lg bg-indigo-600 text-white text-xs font-semibold">
                            Créer le courrier départ
                        </button>
                    </form>
                    @else
                    @if($peutStatuerSurReponse)
                    <form x-show="form === 'rejeter-reponse'" x-cloak method="post" action="{{ route('courriers.circuit.rejeter-reponse', $courrier) }}" class="space-y-2 pt-2 border-t border-slate-100">
                        @csrf
                        <p class="text-[11px] text-slate-500">Indiquez ce que la particulière doit corriger.</p>
                        <textarea name="motif_rejet" required rows="3" class="w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900" placeholder="Motif du rejet…">{{ old('motif_rejet') }}</textarea>
                        @error('motif_rejet')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        <button type="button"
                                onclick="flashAlert('Confirmer le rejet de cette réponse ?', this.closest('form'), {icon:'↩️', danger:true, confirmText:'Rejeter', title:'Rejet'})"
                                class="w-full px-3 py-2 rounded-lg bg-amber-600 text-white text-xs font-semibold">Confirmer le rejet</button>
                    </form>
                    @endif
                    @if($peutCreerReponseDirecte)
                    <form x-show="form === 'reponse-directe'" x-cloak method="post" action="{{ route('courriers.creer-reponse', $courrier) }}" enctype="multipart/form-data"
                          x-data="{ confidentiel: false }" class="space-y-2 pt-2 border-t border-slate-100">
                        @csrf
                        <input type="hidden" name="signer_immediatement" value="1">
                        <div>
                            <label class="block text-[11px] font-semibold mb-1">Document de réponse</label>
                            <input type="file" name="document_reponse" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="block w-full text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-slate-700 file:text-white file:font-semibold file:text-xs">
                            @error('document_reponse')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div class="rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-2.5 py-2">
                            <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold">Objet du courrier départ</p>
                            <p class="text-xs text-slate-800 dark:text-slate-100 mt-0.5 leading-snug">{{ $courrier->objetReponseDepartParDefaut() }}</p>
                        </div>
                        <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="reponse_confidentielle" value="1" x-model="confidentiel"> Réponse confidentielle (destinataire = un collaborateur)</label>
                        <template x-if="! confidentiel">
                            <div>
                                @if($courrier->estOrigineInterne())
                                <p class="text-[11px] text-slate-500">Réponse interne vers <strong>{{ $courrier->structureExpediteur?->nom ?? $courrier->expediteur_libelle ?? 'la direction émettrice' }}</strong>.</p>
                                @else
                                <select name="structure_destinataire_id" class="w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900">
                                    <option value="">Destinataire</option>
                                    @foreach($secretariats as $s)<option value="{{ $s->id }}">{{ $s->nom }}</option>@endforeach
                                </select>
                                @error('structure_destinataire_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                @endif
                            </div>
                        </template>
                        <template x-if="confidentiel">
                            <div>
                                <select name="destinataire_agent_id" class="w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900">
                                    <option value="">Collaborateur destinataire</option>
                                    @foreach($agentsOrientation as $ag)<option value="{{ $ag->id }}" title="{{ $ag->name }}">{{ $ag->libelleDestinataireCourrier() }}</option>@endforeach
                                </select>
                                @error('destinataire_agent_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </template>
                        <button type="button"
                                onclick="flashAlert('Créer et signer directement ce courrier départ réponse ?', this.closest('form'), {icon:'✍️', danger:false, confirmText:'Créer et signer', title:'Réponse directe'})"
                                class="w-full px-3 py-2 rounded-lg bg-slate-700 text-white text-xs font-semibold">
                            Créer et signer
                        </button>
                    </form>
                    @endif
                    @endif
                    @endif
                    @endcan

                    @can('transmettre', $courrier)
                    <form x-show="form === 'transmettre'" x-cloak method="post" action="{{ route('courriers.transmettre', $courrier) }}" enctype="multipart/form-data" class="space-y-2 pt-2 border-t border-slate-100">
                        @csrf
                        @if($courrier->estDepart())
                        <p class="text-[11px] text-slate-500">Trace d’envoi après expédition.</p>
                        <select name="vers_structure_id" class="w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900">
                            <option value="">Structure destinataire</option>
                            @foreach($structures as $s)
                            <option value="{{ $s->id }}" @selected($courrier->structure_destinataire_id == $s->id)>{{ $s->nom }}</option>
                            @endforeach
                        </select>
                        <textarea name="commentaire" rows="2" class="w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900" placeholder="Commentaire…"></textarea>
                        <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="accuse_reception" value="1"> Accusé de réception reçu</label>
                        <input type="file" name="accuse_fichier" accept=".pdf,.jpg,.jpeg,.png" class="text-xs w-full">
                        @else
                        <p class="text-[11px] text-slate-500">Transmission interne après orientation.</p>
                        <select name="vers_structure_id" class="w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900">
                            <option value="">Structure destinataire</option>
                            @foreach($structures as $s)<option value="{{ $s->id }}">{{ $s->nom }}</option>@endforeach
                        </select>
                        <textarea name="commentaire" rows="2" class="w-full rounded-lg border px-2.5 py-1.5 text-xs dark:bg-slate-900" placeholder="Commentaire…"></textarea>
                        @endif
                        <button type="button"
                                onclick="flashAlert('Enregistrer cette transmission dans le registre ?', this.closest('form'), {icon:'📋', danger:false, confirmText:'Enregistrer', title:'Transmission'})"
                                class="w-full px-3 py-2 rounded-lg bg-slate-800 text-white text-xs font-semibold">Enregistrer</button>
                    </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</div>
<style>[x-cloak]{display:none!important}</style>
@endsection
