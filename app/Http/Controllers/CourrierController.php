<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnnulerCourrierDepartRequest;
use App\Http\Requests\ArchiverCourrierRequest;
use App\Http\Requests\CreerReponseCourrierRequest;
use App\Http\Requests\ExpedierCourrierDepartRequest;
use App\Http\Requests\OrienterCourrierRequest;
use App\Http\Requests\RefuserReceptionInterneRequest;
use App\Http\Requests\RejeterDepartCourrierRequest;
use App\Http\Requests\StoreCourrierRequest;
use App\Http\Requests\TransmettreCourrierRequest;
use App\Http\Requests\UpdateCourrierArriveeRequest;
use App\Http\Requests\UpdateCourrierDepartRequest;
use App\Http\Requests\VentilerCourrierRequest;
use App\Models\Courrier;
use App\Models\CourrierTransmission;
use App\Models\CourrierVentilationDestinataire;
use App\Models\Document;
use App\Models\JournalAudit;
use App\Models\Parapheur;
use App\Models\PrioriteCourrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\StatutDocument;
use App\Models\Structure;
use App\Models\TypeCourrier;
use App\Models\TypeDocument;
use App\Models\User;
use App\Services\CircuitCourrierMoteurService;
use App\Services\CourrierFilService;
use App\Services\CourrierNotificationService;
use App\Services\CourrierNumeroRegistreService;
use App\Services\CourrierOrientationService;
use App\Services\CourrierSecretariatService;
use App\Services\CourrierWorkflowService;
use App\Services\ParapheurDepartService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class CourrierController extends Controller
{
    public function __construct(
        private readonly CourrierNumeroRegistreService $numeroService,
        private readonly CourrierWorkflowService $workflowService,
        private readonly CourrierSecretariatService $secretariatService,
        private readonly ParapheurDepartService $parapheurDepartService,
        private readonly CourrierNotificationService $courrierNotifications,
        private readonly CourrierFilService $filService,
        private readonly CircuitCourrierMoteurService $circuitMoteur,
        private readonly CourrierOrientationService $orientationService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Courrier::class);

        $sensCode = $request->get('sens', 'arrivee');
        $sens = SensCourrier::where('code', $sensCode)->firstOrFail();

        $query = Courrier::query()
            ->visibleBy(auth()->user())
            ->where('sens_courrier_id', $sens->id)
            ->with(['statutCourrier', 'typeCourrier', 'prioriteCourrier', 'createur', 'structureDestinataire'])
            ->latest();

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('objet', 'like', "%{$q}%")
                    ->orWhere('expediteur_libelle', 'like', "%{$q}%")
                    ->orWhere('destinataire_libelle', 'like', "%{$q}%")
                    ->orWhere('numero_fulgurant', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%");
            });
        }

        $courriers = $query->paginate(20)->withQueryString();

        return view('courriers.index', compact('courriers', 'sens', 'sensCode'));
    }

    public function aRecevoir(Request $request)
    {
        $this->authorize('viewAny', Courrier::class);

        $user = auth()->user();
        $sensDepart = SensCourrier::where('code', SensCourrier::DEPART)->firstOrFail();

        $courriers = Courrier::query()
            ->where('sens_courrier_id', $sensDepart->id)
            ->where('structure_destinataire_id', $user->structure_id)
            ->whereNull('courrier_arrivee_lie_id')
            ->whereHas('statutCourrier', fn ($q) => $q->where('code', 'expedie'))
            ->with(['statutCourrier', 'createur', 'structure', 'structureDestinataire', 'documents'])
            ->latest()
            ->paginate(20);

        return view('courriers.a-recevoir', compact('courriers'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Courrier::class);

        $sensCode = $request->get('sens', 'arrivee');
        $sens = SensCourrier::where('code', $sensCode)->firstOrFail();
        $types = TypeCourrier::where('actif', true)->with('circuit')->orderBy('libelle')->get();
        $priorites = PrioriteCourrier::where('actif', true)->orderBy('ordre')->get();
        $secretariats = Structure::secretariatsDirections()->get();
        $directions = Structure::query()
            ->where('actif', true)
            ->where('type', 'direction')
            ->orderBy('nom')
            ->get();
        $documentsParapheur = $sensCode === 'depart'
            ? $this->parapheurDepartService->queryEligiblePour(auth()->user())->limit(100)->get()
            : collect();
        $typesDocumentParapheur = $sensCode === 'depart'
            ? $this->parapheurDepartService->typesDocumentPourDepot()
            : collect();

        return view('courriers.create', compact(
            'sens', 'sensCode', 'types', 'priorites', 'secretariats', 'directions',
            'documentsParapheur', 'typesDocumentParapheur',
        ));
    }

    public function store(StoreCourrierRequest $request)
    {
        $sens = SensCourrier::where('code', $request->sens)->firstOrFail();
        $nums = $this->numeroService->prochainNumero((int) $sens->id);
        $statut = $this->workflowService->statutInitialPourSens((int) $sens->id);

        $destinataire = $request->structure_destinataire_id
            ? Structure::find($request->structure_destinataire_id)
            : null;

        $courrier = Courrier::create([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => $request->type_courrier_id,
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => $request->priorite_courrier_id,
            'numero_registre' => $nums['numero_registre'],
            'numero_registre_annee' => $nums['numero_registre_annee'],
            'reference' => $sens->code === SensCourrier::DEPART
                ? $this->numeroService->genererReferenceDepart()
                : $request->reference,
            'origine' => $sens->code === SensCourrier::ARRIVEE ? Courrier::ORIGINE_EXTERNE : Courrier::ORIGINE_INTERNE,
            'date_reception' => $request->date_reception ?? ($sens->code === SensCourrier::ARRIVEE ? now()->toDateString() : null),
            'date_courrier' => $request->date_courrier,
            'numero_fulgurant' => $request->numero_fulgurant,
            'expediteur_libelle' => $request->expediteur_libelle,
            'expediteur_email' => $request->expediteur_email,
            'expediteur_telephone' => $request->expediteur_telephone,
            'destinataire_libelle' => $destinataire?->nom ?? $request->destinataire_libelle,
            'est_expediteur_externe' => $request->boolean('est_expediteur_externe', $sens->code === SensCourrier::ARRIVEE),
            'structure_expediteur_id' => $request->structure_expediteur_id,
            'structure_destinataire_id' => $request->structure_destinataire_id,
            'service_demandeur_structure_id' => $request->service_demandeur_structure_id,
            'objet' => $request->objet,
            'createur_id' => auth()->id(),
            'structure_id' => auth()->user()->structure_id,
        ]);

        if ($sens->code === SensCourrier::ARRIVEE && $request->hasFile('fichier')) {
            $this->attacherDocument($courrier, $request->file('fichier'), $sens->code, true);
        }

        if ($sens->code === SensCourrier::DEPART) {
            $nouveauxIds = $this->deposerPiecesParapheur($request);
            $idsSelection = array_merge($request->input('document_ids', []), $nouveauxIds);
            $this->attacherDocumentsParapheur($courrier, $idsSelection);
        }

        $this->circuitMoteur->demarrer($courrier->fresh(['sensCourrier']), null, $request->user());

        $this->auditerCourrier('courrier.create', $courrier, ['sens' => $sens->code]);

        return redirect()
            ->route('courriers.show', $courrier)
            ->with('success', 'Courrier enregistré — n° '.$courrier->numeroRegistreComplet());
    }

    public function show(Courrier $courrier)
    {
        $this->authorize('view', $courrier);

        if ($courrier->circuit_etape_actuelle_id && auth()->user()) {
            $courrier = $this->circuitMoteur->assurerEtapesAutomatiques($courrier, auth()->user());
        }

        $courrier->load([
            'sensCourrier', 'statutCourrier', 'typeCourrier', 'prioriteCourrier', 'parapheur',
            'createur', 'signataire', 'directeurEnAttente', 'rejetePar',
            'documents', 'orientations.structure', 'orientations.orientePar', 'orientations.destinataireUser',
            'orientationNotifies',
            'transmissions.deUser', 'transmissions.versUser', 'transmissions.versStructure',
            'ventilationDestinataires.user', 'ventilationDestinataires.document',
            'dossier', 'structure', 'structureDestinataire', 'structureExpediteur', 'serviceDemandeurStructure',
            'courrierParent', 'courrierDepartSource', 'courrierArriveeLie', 'reponsesDepart.documents', 'reponsesDepart.statutCourrier',
            'circuit', 'circuitEtapeActuelle', 'circuitHistoriques.etape', 'circuitHistoriques.user',
            'documentReponse', 'reponseStructureDestinataire', 'destinataireAgent', 'agentConfie', 'suiviPaiement',
        ]);
        $structures = Structure::where('actif', true)->orderBy('nom')->get();
        $directions = Structure::query()
            ->where('actif', true)
            ->where('type', 'direction')
            ->orderBy('nom')
            ->get();
        $secretariats = Structure::secretariatsDirections()->get();
        $structureEmettriceId = (int) ($courrier->structure_id
            ?? auth()->user()->structurePourValidationHierarchique()?->id
            ?? 0);
        if ($courrier->estDepart()
            && $courrier->statutCourrier?->code === 'signe'
            && $structureEmettriceId > 0) {
            $secretariats = $secretariats->reject(
                fn (Structure $s) => (int) $s->id === $structureEmettriceId
            )->values();
        }
        $utilisateursVentilation = User::query()
            ->where('actif', true)
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name']);
        $agentsOrientation = User::query()
            ->where('actif', true)
            ->orderBy('name')
            ->limit(300)
            ->get(['id', 'name', 'email']);

        $directeurValidation = null;
        $directionEmettrice = null;
        if ($courrier->estDepart() && in_array($courrier->statutCourrier?->code, ['brouillon', 'rejete_directeur'], true)) {
            $structureEmettrice = $courrier->structure ?? auth()->user()->structurePourValidationHierarchique();
            $directionEmettrice = $structureEmettrice?->directionGestionCourrier();
            $directeurValidation = $this->secretariatService->directeurPourSecretariat($structureEmettrice);
        }

        $filRacine = $this->filService->racine($courrier);
        $filCourriers = $this->filService->courriersDuFil($courrier);
        $filHistorique = $this->filService->construireHistorique($courrier);

        return view('courriers.show', compact(
            'courrier', 'structures', 'directions', 'secretariats', 'utilisateursVentilation', 'agentsOrientation',
            'directeurValidation', 'directionEmettrice',
            'filRacine', 'filCourriers', 'filHistorique',
        ));
    }

    public function edit(Courrier $courrier)
    {
        $this->authorize('corriger', $courrier);

        $types = TypeCourrier::where('actif', true)->orderBy('libelle')->get();
        $priorites = PrioriteCourrier::where('actif', true)->orderBy('ordre')->get();

        return view('courriers.edit', compact('courrier', 'types', 'priorites'));
    }

    public function update(Courrier $courrier)
    {
        $this->authorize('corriger', $courrier);

        if ($courrier->estArrivee()) {
            return app()->call([$this, 'updateArrivee'], ['courrier' => $courrier]);
        }

        return app()->call([$this, 'updateDepart'], ['courrier' => $courrier]);
    }

    public function updateArrivee(UpdateCourrierArriveeRequest $request, Courrier $courrier)
    {
        $courrier->update([
            'objet' => $request->objet,
            'type_courrier_id' => $request->type_courrier_id,
            'priorite_courrier_id' => $request->priorite_courrier_id,
            'date_reception' => $request->date_reception,
            'date_courrier' => $request->date_courrier,
            'expediteur_libelle' => $request->expediteur_libelle,
            'expediteur_email' => $request->expediteur_email,
            'expediteur_telephone' => $request->expediteur_telephone,
            'numero_fulgurant' => $request->numero_fulgurant,
            'reference' => $request->reference,
            'nombre_pieces' => $request->nombre_pieces,
            'numero_archives' => $request->numero_archives,
            'observations' => $request->observations,
        ]);

        $this->auditerCourrier('courrier.update', $courrier->fresh(), [
            'sens' => 'arrivee',
            'correction' => true,
        ]);

        return redirect()
            ->route('courriers.show', $courrier)
            ->with('success', 'Enregistrement du courrier corrigé.');
    }

    public function updateDepart(UpdateCourrierDepartRequest $request, Courrier $courrier)
    {
        $courrier->update([
            'objet' => $request->objet,
            'type_courrier_id' => $request->type_courrier_id,
            'priorite_courrier_id' => $request->priorite_courrier_id,
            'date_courrier' => $request->date_courrier,
        ]);

        if ($courrier->statutCourrier?->code === 'rejete_directeur') {
            $this->workflowService->transitionner($courrier, 'brouillon', [
                'motif_rejet' => null,
                'rejete_par_id' => null,
                'date_rejet' => null,
            ]);
        }

        $this->auditerCourrier('courrier.update', $courrier);

        return redirect()
            ->route('courriers.show', $courrier)
            ->with('success', 'Courrier mis à jour — vous pouvez le transmettre au directeur.');
    }

    public function mettreEnParapheur(Courrier $courrier)
    {
        $this->authorize('update', $courrier);
        $parapheur = Parapheur::query()
            ->where('sens_courrier_id', $courrier->sens_courrier_id)
            ->where('actif', true)
            ->first();

        $this->workflowService->transitionner($courrier, 'en_parapheur', [
            'parapheur_id' => $parapheur?->id,
        ]);

        $courrier = $courrier->fresh(['structure', 'statutCourrier']);
        $this->courrierNotifications->notifierMiseEnParapheur($courrier, auth()->user());
        $this->auditerCourrier('courrier.parapheur', $courrier);

        return back()->with('success', 'Courrier placé en parapheur. Le directeur de direction a été informé.');
    }

    public function orienter(OrienterCourrierRequest $request, Courrier $courrier)
    {
        $this->orientationService->appliquer($courrier, $request->user(), [
            'orientation_mode' => $request->input('orientation_mode'),
            'instructions_dg' => $request->input('instructions_dg'),
            'est_confidentiel' => $request->boolean('est_confidentiel'),
            'destinataire_type' => $request->input('destinataire_type'),
            'direction_id' => $request->filled('direction_id') ? $request->integer('direction_id') : null,
            'notify_user_ids' => $request->input('notify_user_ids', []),
        ]);

        if ($request->input('orientation_mode') === OrienterCourrierRequest::MODE_VIA_PARTICULIERE) {
            return back()->with('success', 'Instructions transmises à la particulière pour préparer l’élément de réponse.');
        }

        return back()->with('success', 'Courrier orienté. Les destinataires ont été notifiés.');
    }

    public function ventiler(VentilerCourrierRequest $request, Courrier $courrier)
    {
        $documentIdsCourrier = $courrier->documents()->pluck('documents.id')->all();

        foreach ($request->input('ventilations', []) as $ligne) {
            $documentId = (int) $ligne['document_id'];
            if (! in_array($documentId, $documentIdsCourrier, true)) {
                continue;
            }

            CourrierVentilationDestinataire::updateOrCreate(
                [
                    'courrier_id' => $courrier->id,
                    'user_id' => (int) $ligne['user_id'],
                    'document_id' => $documentId,
                ],
                ['structure_id' => $ligne['structure_id'] ?? null]
            );
        }

        $this->workflowService->transitionner($courrier, 'ventile');

        $this->auditerCourrier('courrier.ventiler', $courrier->fresh());

        return back()->with('success', 'Courrier ventilé — accès limité à la pièce transmise.');
    }

    public function cloturer(Courrier $courrier)
    {
        $this->authorize('update', $courrier);

        if (! $courrier->estArrivee()) {
            return back()->with('error', 'Seuls les courriers arrivée peuvent être clôturés manuellement.');
        }

        // Clôture manuelle réservée aux dossiers déjà traités côté statut
        // (ex. classé sans suite après orientation/ventilation). Les réponses
        // avec départ sont clôturées automatiquement à l’expédition.
        if (! $courrier->peutTransitionnerVers('cloture')) {
            return back()->with('error', 'Ce dossier ne peut pas encore être clôturé manuellement. Expédiez d’abord le courrier réponse, ou orientez/ventilez le dossier.');
        }

        $this->workflowService->transitionner($courrier, 'cloture');

        return back()->with('success', 'Courrier arrivée clôturé.');
    }

    public function transmettreAuDirecteur(Courrier $courrier)
    {
        $this->authorize('transmettreAuDirecteur', $courrier);

        $structure = $courrier->structure ?? auth()->user()->structurePourValidationHierarchique();
        $directeur = $this->secretariatService->directeurPourSecretariat($structure);

        if (! $directeur) {
            return back()->with('error', 'Aucun directeur trouvé pour ce secrétariat.');
        }

        if ((int) $directeur->id === (int) auth()->id()) {
            return back()->with('error', 'Vous ne pouvez pas vous transmettre ce courrier à vous-même.');
        }

        $this->workflowService->transitionner($courrier, 'transmis_directeur', [
            'directeur_en_attente_id' => $directeur->id,
            'motif_rejet' => null,
            'rejete_par_id' => null,
            'date_rejet' => null,
        ]);

        $this->courrierNotifications->notifier(
            $directeur,
            $courrier->fresh(),
            auth()->user(),
            CourrierNotificationService::TRANSMISSION_DIRECTEUR
        );

        return back()->with('success', 'Courrier transmis à '.$directeur->name.' pour validation.');
    }

    public function signer(Courrier $courrier)
    {
        $this->authorize('signer', $courrier);

        $this->workflowService->transitionner($courrier, 'signe', [
            'signataire_id' => auth()->id(),
            'directeur_en_attente_id' => null,
        ]);

        $courrier = $courrier->fresh(['courrierParent.circuitEtapeActuelle', 'statutCourrier']);
        if ($courrier->courrierParent
            && $courrier->courrierParent->circuitEtapeActuelle?->code === 'validation_reponse_dg') {
            try {
                $this->circuitMoteur->signerReponseDepart(
                    $courrier->courrierParent,
                    $courrier,
                    auth()->user()
                );
            } catch (\InvalidArgumentException) {
                // Signature du départ OK ; le circuit arrivée sera rattrapé si besoin.
            }
        }

        $this->courrierNotifications->notifierCreateur(
            $courrier,
            auth()->user(),
            CourrierNotificationService::VALIDE_POUR_ENVOI
        );

        return back()->with('success', 'Courrier signé — le secrétariat / la particulière peut l\'expédier.');
    }

    public function rejeterDepart(RejeterDepartCourrierRequest $request, Courrier $courrier)
    {
        $this->workflowService->transitionner($courrier, 'rejete_directeur', [
            'motif_rejet' => $request->motif_rejet,
            'rejete_par_id' => auth()->id(),
            'date_rejet' => now(),
        ]);

        $this->courrierNotifications->notifierCreateur(
            $courrier->fresh(),
            auth()->user(),
            CourrierNotificationService::RENVOI_CORRECTION,
            $request->motif_rejet
        );

        return back()->with('success', 'Courrier renvoyé au secrétariat pour correction.');
    }

    public function annulerDepart(AnnulerCourrierDepartRequest $request, Courrier $courrier)
    {
        $acteur = auth()->user();
        $etaitChezDirecteur = $courrier->statutCourrier?->code === 'transmis_directeur';

        $this->workflowService->transitionner($courrier, 'annule', [
            'motif_rejet' => $request->motif_annulation,
            'rejete_par_id' => $acteur->id,
            'date_rejet' => now(),
        ]);

        if ($etaitChezDirecteur) {
            $this->courrierNotifications->notifierCreateur(
                $courrier->fresh(),
                $acteur,
                CourrierNotificationService::ANNULATION,
                $request->motif_annulation
            );
        }

        $this->auditerCourrier('courrier.annule', $courrier);

        return redirect()
            ->route('courriers.index', ['sens' => 'depart'])
            ->with('success', 'Courrier annulé.');
    }

    public function expedierVersSecretariat(ExpedierCourrierDepartRequest $request, Courrier $courrier)
    {
        // Réponse confidentielle adressée directement à un agent : destinataire déjà figé,
        // pas de secrétariat à notifier — l'agent verra le courrier via sa ventilation/visibilité.
        if ($courrier->destinataire_agent_id) {
            $courrier->update([
                'numero_archives' => $request->input('numero_archives'),
                'observations' => $request->input('observations'),
            ]);

            $this->workflowService->transitionner($courrier, 'expedie', [
                'date_expedition' => now(),
            ]);

            $courrier = $courrier->fresh(['destinataireAgent', 'courrierParent.statutCourrier', 'courrierParent.sensCourrier', 'courrierParent.circuitEtapeActuelle']);
            $this->workflowService->cloturerArriveeLieeApresExpedition($courrier);

            if ($courrier->courrier_parent_id && $courrier->courrierParent) {
                $this->circuitMoteur->completerApresExpeditionReponse(
                    $courrier->courrierParent,
                    $request->user(),
                    'Réponse n° '.$courrier->numeroRegistreComplet().' expédiée.'
                );
            }

            if ($courrier->destinataireAgent) {
                $this->courrierNotifications->notifier(
                    $courrier->destinataireAgent,
                    $courrier,
                    auth()->user(),
                    CourrierNotificationService::EXPEDITION
                );
            }

            return back()->with('success', 'Courrier expédié — le collaborateur destinataire en est informé.');
        }

        $destinataire = Structure::findOrFail($request->structure_destinataire_id);

        $courrier->update([
            'structure_destinataire_id' => $destinataire->id,
            'destinataire_libelle' => $destinataire->nom,
            'numero_archives' => $request->input('numero_archives'),
            'observations' => $request->input('observations'),
        ]);

        $this->workflowService->transitionner($courrier, 'expedie', [
            'date_expedition' => now(),
        ]);

        $courrier = $courrier->fresh(['structureDestinataire', 'courrierParent.statutCourrier', 'courrierParent.sensCourrier', 'courrierParent.circuitEtapeActuelle']);
        $this->workflowService->cloturerArriveeLieeApresExpedition($courrier);

        if ($courrier->courrier_parent_id && $courrier->courrierParent) {
            $this->circuitMoteur->completerApresExpeditionReponse(
                $courrier->courrierParent,
                $request->user(),
                'Réponse n° '.$courrier->numeroRegistreComplet().' expédiée.'
            );
        }

        $libelle = $courrier->structureDestinataire?->nom ?? 'destinataire';

        $this->courrierNotifications->notifierSecretariatStructure(
            $courrier->structureDestinataire,
            $courrier,
            auth()->user(),
            CourrierNotificationService::EXPEDITION
        );

        return back()->with('success', "Courrier expédié vers {$libelle}.");
    }

    public function accepterReceptionInterne(Courrier $courrier)
    {
        $this->authorize('recevoir', $courrier);

        $arrivee = $this->secretariatService->creerArriveeDepuisDepart($courrier, auth()->user());

        $this->auditerCourrier('courrier.reception_interne', $arrivee, [
            'depart_id' => $courrier->id,
        ]);

        return redirect()
            ->route('courriers.show', $arrivee)
            ->with('success', 'Courrier interne réceptionné — n° '.$arrivee->numeroRegistreComplet());
    }

    public function refuserReceptionInterne(RefuserReceptionInterneRequest $request, Courrier $courrier)
    {
        $this->workflowService->transitionner($courrier, 'reception_refusee', [
            'motif_rejet' => $request->motif_rejet,
            'rejete_par_id' => auth()->id(),
            'date_rejet' => now(),
        ]);

        $this->courrierNotifications->notifierCreateur(
            $courrier->fresh(),
            auth()->user(),
            CourrierNotificationService::RECEPTION_REFUSEE,
            $request->motif_rejet
        );

        return back()->with('success', 'Réception refusée — le secrétariat émetteur en sera informé.');
    }

    /**
     * Création du courrier départ réponse.
     * - Override DG (`signer_immediatement`) : créé directement au statut « Signé ».
     * - Sans circuit : chemin historique.
     * - Chemin A : via soumettre-reponse (création départ) + valider-reponse (signature).
     */
    public function creerReponse(CreerReponseCourrierRequest $request, Courrier $courrier)
    {
        if (! $courrier->circuit_courrier_id) {
            return $this->creerReponseSansCircuit($request, $courrier);
        }

        $sensDepart = SensCourrier::where('code', SensCourrier::DEPART)->firstOrFail();
        $signerImmediatement = $request->boolean('signer_immediatement')
            && ($request->user()->aAccesTotal() || $request->user()->hasRole('admin'));

        if (! $signerImmediatement) {
            return back()->with('error', 'Utilisez « Transmettre pour signature » pour préparer la réponse.');
        }

        $statut = StatutCourrier::where('sens_courrier_id', $sensDepart->id)->where('code', 'signe')->firstOrFail();
        $nums = $this->numeroService->prochainNumero((int) $sensDepart->id);

        $confidentielle = $request->boolean('reponse_confidentielle', (bool) $courrier->reponse_confidentielle);

        $structureDestinataireId = null;
        $agentDestinataireId = null;
        $destinataireLibelle = null;

        if ($confidentielle) {
            $agentDestinataireId = (int) $request->input('destinataire_agent_id');
            $destinataireLibelle = User::findOrFail($agentDestinataireId)->name;
        } else {
            $structureDestinataireId = $courrier->estOrigineInterne()
                ? $courrier->structure_expediteur_id
                : (int) $request->input('structure_destinataire_id');
            $destinataire = Structure::findOrFail($structureDestinataireId);
            $destinataireLibelle = $destinataire->nom;
        }

        $reponse = Courrier::create([
            'sens_courrier_id' => $sensDepart->id,
            'type_courrier_id' => TypeCourrier::where('code', 'reponse')->value('id'),
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => $courrier->priorite_courrier_id,
            'numero_registre' => $nums['numero_registre'],
            'numero_registre_annee' => $nums['numero_registre_annee'],
            'reference' => $this->numeroService->genererReferenceDepart(),
            'origine' => $courrier->estOrigineInterne() ? Courrier::ORIGINE_INTERNE : Courrier::ORIGINE_EXTERNE,
            'courrier_parent_id' => $courrier->id,
            'date_courrier' => now()->toDateString(),
            'objet' => $courrier->objetReponseDepartParDefaut(),
            'structure_destinataire_id' => $structureDestinataireId,
            'destinataire_agent_id' => $agentDestinataireId,
            'destinataire_libelle' => $destinataireLibelle,
            'createur_id' => auth()->id(),
            'signataire_id' => $request->user()->id,
            'structure_id' => auth()->user()->structure_id,
            'dossier_id' => $courrier->dossier_id,
        ]);

        if ($request->hasFile('document_reponse')) {
            $this->attacherDocument($reponse, $request->file('document_reponse'), SensCourrier::DEPART, true);
        }

        $this->auditerCourrier('courrier.creer_reponse', $reponse, [
            'courrier_parent_id' => $courrier->id,
            'mode' => 'signe',
        ]);

        $courrier->forceFill([
            'document_reponse_id' => null,
            'reponse_confidentielle' => false,
            'reponse_structure_destinataire_id' => null,
            'destinataire_agent_id' => null,
            'reponse_objet' => null,
        ])->save();

        $this->circuitMoteur->terminerApresReponseDirecte(
            $courrier->fresh(['circuitEtapeActuelle']),
            $request->user(),
            'Courrier réponse créé et signé — n° '.$reponse->numeroRegistreComplet()
        );

        return redirect()
            ->route('courriers.show', $reponse)
            ->with('success', 'Courrier départ réponse créé et signé — prêt à être expédié. Lié au fil du courrier n° '.$courrier->numeroRegistreComplet().'.');
    }

    /**
     * Chemin historique (courrier sans circuit métier attaché) : sélection de pièces déjà
     * présentes dans le parapheur départ, création au statut initial (brouillon).
     */
    private function creerReponseSansCircuit(CreerReponseCourrierRequest $request, Courrier $courrier)
    {
        $sensDepart = SensCourrier::where('code', SensCourrier::DEPART)->firstOrFail();
        $statut = $this->workflowService->statutInitialPourSens((int) $sensDepart->id);
        $nums = $this->numeroService->prochainNumero((int) $sensDepart->id);

        $structureDestinataireId = $courrier->estOrigineInterne()
            ? $courrier->structure_expediteur_id
            : (int) $request->structure_destinataire_id;

        $destinataire = Structure::findOrFail($structureDestinataireId);

        $reponse = Courrier::create([
            'sens_courrier_id' => $sensDepart->id,
            'type_courrier_id' => TypeCourrier::where('code', 'reponse')->value('id'),
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => $courrier->priorite_courrier_id,
            'numero_registre' => $nums['numero_registre'],
            'numero_registre_annee' => $nums['numero_registre_annee'],
            'reference' => $this->numeroService->genererReferenceDepart(),
            'origine' => $courrier->estOrigineInterne() ? Courrier::ORIGINE_INTERNE : Courrier::ORIGINE_EXTERNE,
            'courrier_parent_id' => $courrier->id,
            'date_courrier' => now()->toDateString(),
            'objet' => $courrier->objetReponseDepartParDefaut(),
            'structure_destinataire_id' => $destinataire->id,
            'destinataire_libelle' => $destinataire->nom,
            'createur_id' => auth()->id(),
            'structure_id' => auth()->user()->structure_id,
            'dossier_id' => $courrier->dossier_id,
        ]);

        $this->attacherDocumentsParapheur($reponse, $request->input('document_ids', []));

        $this->auditerCourrier('courrier.creer_reponse', $reponse, [
            'courrier_parent_id' => $courrier->id,
        ]);

        $this->circuitMoteur->completerApresCreationDepart(
            $courrier->fresh(['circuitEtapeActuelle']),
            $request->user(),
            'Courrier réponse créé — n° '.$reponse->numeroRegistreComplet()
        );

        return redirect()
            ->route('courriers.show', $courrier)
            ->with('success', 'Courrier départ réponse créé — lié au fil du courrier n° '.$courrier->numeroRegistreComplet().'.');
    }

    public function archiverCourrier(ArchiverCourrierRequest $request, Courrier $courrier)
    {
        if (! $courrier->peutEtreArchive()) {
            return back()->with('error', 'Le courrier ne peut pas être archivé à ce stade du circuit.');
        }

        $courrier->update([
            'nombre_pieces' => $request->validated('nombre_pieces'),
            'numero_archives' => $request->validated('numero_archives'),
            'observations' => $request->validated('observations'),
            'dossier_id' => $request->validated('dossier_id') ?? $courrier->dossier_id,
        ]);

        if ($courrier->estDepart()) {
            $this->workflowService->transitionner($courrier, 'archive');
        } else {
            $this->workflowService->transitionner($courrier, 'cloture');
        }

        return back()->with('success', 'Courrier archivé. Les informations du registre ont été mises à jour.');
    }

    public function transmettre(TransmettreCourrierRequest $request, Courrier $courrier)
    {
        $accuseChemin = null;
        if ($request->hasFile('accuse_fichier')) {
            $accuseChemin = $request->file('accuse_fichier')->store('courriers/accuses', 'public');
        }

        CourrierTransmission::create([
            'courrier_id' => $courrier->id,
            'de_structure_id' => auth()->user()->structure_id,
            'vers_structure_id' => $request->vers_structure_id,
            'de_user_id' => auth()->id(),
            'vers_user_id' => $request->vers_user_id,
            'date_transmission' => now(),
            'accuse_reception' => $request->boolean('accuse_reception'),
            'accuse_chemin' => $accuseChemin,
            'commentaire' => $request->commentaire,
        ]);

        $this->auditerCourrier('courrier.transmettre', $courrier, [
            'vers_structure_id' => $request->vers_structure_id,
        ]);

        return back()->with('success', 'Transmission enregistrée dans le registre.');
    }

    /**
     * @return list<int>
     */
    private function deposerPiecesParapheur(StoreCourrierRequest $request): array
    {
        if (! $request->hasFile('nouveaux_fichiers')) {
            return [];
        }

        $typeId = (int) $request->input('nouveau_type_document_id');
        $ids = [];

        foreach ($request->file('nouveaux_fichiers') as $file) {
            $document = $this->parapheurDepartService->deposerPiece(auth()->user(), $file, $typeId);
            $ids[] = $document->id;
        }

        return $ids;
    }

    /**
     * @param  list<int>  $documentIds
     */
    private function attacherDocumentsParapheur(Courrier $courrier, array $documentIds): void
    {
        $user = auth()->user();

        foreach (array_unique($documentIds) as $documentId) {
            $document = Document::find($documentId);
            if (! $document || ! $this->parapheurDepartService->estEligible($document, $user)) {
                continue;
            }
            if (! $courrier->documents()->where('documents.id', $document->id)->exists()) {
                $courrier->documents()->attach($document->id, ['est_principal' => false]);
            }
        }
    }

    private function attacherDocument(Courrier $courrier, UploadedFile $file, string $sensCode, bool $principal = false): void
    {
        $typeDoc = TypeDocument::query()
            ->whereIn('code', $sensCode === SensCourrier::ARRIVEE ? ['COURRIER_IN', 'COURRIER'] : ['COURRIER_OUT', 'COURRIER'])
            ->where('actif', true)
            ->orderByRaw("CASE WHEN code LIKE 'COURRIER_%' THEN 0 ELSE 1 END")
            ->first();

        if (! $typeDoc) {
            return;
        }

        $statut = StatutDocument::query()->where('code', 'brouillon')->first();
        $chemin = $file->store('documents/courriers', 'public');

        $document = Document::create([
            'type_document_id' => $typeDoc->id,
            'user_id' => auth()->id(),
            'createur_id' => auth()->id(),
            'proprietaire_id' => auth()->id(),
            'dossier_id' => $courrier->dossier_id,
            'nom_original' => $file->getClientOriginalName(),
            'chemin' => $chemin,
            'extension' => $file->getClientOriginalExtension(),
            'taille_octets' => $file->getSize(),
            'statut' => 'brouillon',
            'statut_document_id' => $statut?->id,
            'en_corbeille' => false,
        ]);

        $courrier->documents()->attach($document->id, ['est_principal' => $principal]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function auditerCourrier(string $action, Courrier $courrier, array $extra = []): void
    {
        JournalAudit::log($action, 'courriers', [
            'commentaire' => json_encode(array_merge(['courrier_id' => $courrier->id], $extra)),
        ]);
    }
}
