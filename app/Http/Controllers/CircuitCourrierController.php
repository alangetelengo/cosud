<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmerControleDepenseRequest;
use App\Http\Requests\EnregistrerDechargeAcRequest;
use App\Http\Requests\EnvoyerChequeAcRequest;
use App\Http\Requests\InstruireCircuitCourrierRequest;
use App\Http\Requests\RejeterReponseCourrierRequest;
use App\Http\Requests\SignerChequeDgRequest;
use App\Http\Requests\SoumettreReponseCourrierRequest;
use App\Http\Requests\ValiderReponseCourrierRequest;
use App\Models\CircuitCourrier;
use App\Models\CircuitCourrierEtape;
use App\Models\Courrier;
use App\Models\Document;
use App\Models\JournalAudit;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\StatutDocument;
use App\Models\TypeCourrier;
use App\Models\TypeDocument;
use App\Services\CircuitCourrierMoteurService;
use App\Services\CourrierNotificationService;
use App\Services\CourrierNumeroRegistreService;
use App\Services\CourrierRetardService;
use App\Services\CourrierSecretariatService;
use App\Services\CourrierWorkflowService;
use App\Services\ParapheurDepartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class CircuitCourrierController extends Controller
{
    public function __construct(
        private readonly CircuitCourrierMoteurService $moteur,
        private readonly CourrierNumeroRegistreService $numeroService,
        private readonly CourrierWorkflowService $workflowService,
        private readonly CourrierSecretariatService $secretariatService,
        private readonly CourrierNotificationService $courrierNotifications,
    ) {
        $this->middleware(function ($request, $next) {
            if ($request->routeIs(
                'courriers.circuit.avancer',
                'courriers.circuit.relancer',
                'courriers.circuit.instruire',
                'courriers.circuit.soumettre-reponse',
                'courriers.circuit.valider-reponse',
                'courriers.circuit.rejeter-reponse',
                'courriers.circuit.envoyer-cheque',
                'courriers.circuit.signer-cheque',
                'courriers.circuit.deposer-preuve-paiement',
                'courriers.circuit.confirmer-controle-depense'
            )) {
                return $next($request);
            }

            if (! auth()->user()?->hasRole('admin')) {
                abort(403, 'Accès réservé aux administrateurs.');
            }

            return $next($request);
        });
    }

    public function index(): View
    {
        $circuits = CircuitCourrier::withCount(['etapes' => fn ($q) => $q->where('actif', true)])
            ->with(['etapes' => fn ($q) => $q->where('actif', true)->orderBy('ordre')])
            ->orderBy('libelle')
            ->get();

        return view('parametres.circuits-courriers.index', compact('circuits'));
    }

    public function create(): View
    {
        return view('parametres.circuits-courriers.create', [
            'roles' => Role::where('guard_name', 'web')->orderBy('name')->pluck('name'),
            'actions' => $this->actionsDisponibles(),
            'mouvements' => $this->mouvementsDisponibles(),
            'acteurTypes' => $this->acteurTypesDisponibles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validerCircuit($request);

        $circuit = CircuitCourrier::create([
            'code' => $validated['code'],
            'libelle' => $validated['libelle'],
            'description' => $validated['description'] ?? null,
            'sens_initial' => $validated['sens_initial'],
            'actif' => $request->boolean('actif', true),
        ]);

        $this->syncEtapes($circuit, $validated['etapes'] ?? []);

        JournalAudit::log('circuit_courrier.create', 'courriers', [
            'commentaire' => 'Circuit créé : '.$circuit->code,
        ]);

        return redirect()
            ->route('parametres.circuits-courriers.index')
            ->with('success', 'Circuit « '.$circuit->libelle.' » créé.');
    }

    public function edit(CircuitCourrier $circuit_courrier): View
    {
        $circuit_courrier->load(['etapes' => fn ($q) => $q->orderBy('ordre')]);

        return view('parametres.circuits-courriers.edit', [
            'circuit' => $circuit_courrier,
            'roles' => Role::where('guard_name', 'web')->orderBy('name')->pluck('name'),
            'actions' => $this->actionsDisponibles(),
            'mouvements' => $this->mouvementsDisponibles(),
            'acteurTypes' => $this->acteurTypesDisponibles(),
        ]);
    }

    public function update(Request $request, CircuitCourrier $circuit_courrier): RedirectResponse
    {
        $validated = $this->validerCircuit($request, $circuit_courrier);

        $circuit_courrier->update([
            'code' => $validated['code'],
            'libelle' => $validated['libelle'],
            'description' => $validated['description'] ?? null,
            'sens_initial' => $validated['sens_initial'],
            'actif' => $request->boolean('actif', true),
        ]);

        $this->syncEtapes($circuit_courrier, $validated['etapes'] ?? []);

        JournalAudit::log('circuit_courrier.update', 'courriers', [
            'commentaire' => 'Circuit mis à jour : '.$circuit_courrier->code,
        ]);

        return redirect()
            ->route('parametres.circuits-courriers.index')
            ->with('success', 'Circuit mis à jour.');
    }

    public function destroy(CircuitCourrier $circuit_courrier): RedirectResponse
    {
        if ($circuit_courrier->courriers()->exists()) {
            return back()->with('error', 'Impossible de supprimer : des courriers utilisent ce circuit. Désactivez-le plutôt.');
        }

        $circuit_courrier->delete();

        return redirect()
            ->route('parametres.circuits-courriers.index')
            ->with('success', 'Circuit supprimé.');
    }

    public function avancer(Request $request, Courrier $courrier): RedirectResponse
    {
        $this->authorize('view', $courrier);

        $request->validate([
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->moteur->avancer($courrier, $request->user(), $request->input('commentaire'));
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Étape du circuit avancée.');
    }

    public function instruire(InstruireCircuitCourrierRequest $request, Courrier $courrier): RedirectResponse
    {
        try {
            $this->moteur->instruire(
                $courrier,
                $request->user(),
                $request->validated('instructions'),
                $request->validated('agent_confie_id') !== null
                    ? (int) $request->validated('agent_confie_id')
                    : null,
                $request->validated('agent_confie_ids') ?? [],
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Instructions enregistrées et transmises pour traitement.');
    }

    public function envoyerCheque(EnvoyerChequeAcRequest $request, Courrier $courrier): RedirectResponse
    {
        foreach ($this->collecterFichiersUpload($request, 'scans_cheque', 'scan_cheque') as $scan) {
            $this->attacherScanCheque($courrier, $scan);
        }

        try {
            $this->moteur->envoyerChequeAuDg(
                $courrier->fresh(),
                $request->user(),
                $request->validated('message'),
                (float) $request->validated('montant'),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Chèque transmis au DG pour signature.');
    }

    public function signerCheque(SignerChequeDgRequest $request, Courrier $courrier): RedirectResponse
    {
        try {
            $this->moteur->signerChequeDg(
                $courrier->fresh(),
                $request->user(),
                $request->validated('message'),
                $request->boolean('notifier_fournisseur', true),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Signature du chèque confirmée. L’AC peut enregistrer la décharge du bénéficiaire.');
    }

    public function deposerPreuvePaiement(EnregistrerDechargeAcRequest $request, Courrier $courrier): RedirectResponse
    {
        foreach ($this->collecterFichiersUpload($request, 'preuves_paiement', 'preuve_paiement') as $preuve) {
            $this->attacherPieceCourrier(
                $courrier,
                $preuve,
                'Pièce de décharge / paiement',
                'Chèque déchargé / pièce d’identité / justificatif de paiement'
            );
        }

        try {
            $this->moteur->enregistrerDechargeAc(
                $courrier->fresh(),
                $request->user(),
                [
                    'date_decharge' => $request->validated('date_decharge'),
                    'numero_piece' => $request->validated('numero_piece'),
                    'montant' => $request->validated('montant'),
                    'banque' => $request->validated('banque'),
                    'beneficiaire_libelle' => $request->validated('beneficiaire_libelle'),
                    'programmation' => $request->validated('programmation'),
                    'observation' => $request->validated('observation'),
                ],
                $request->validated('message'),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Décharge / paiement enregistré — suivi des dépenses notifié pour contrôle.');
    }

    public function confirmerControleDepense(ConfirmerControleDepenseRequest $request, Courrier $courrier): RedirectResponse
    {
        foreach ($this->collecterFichiersUpload($request, 'pieces_complementaires', 'piece_complementaire') as $piece) {
            $this->attacherPieceCourrier(
                $courrier,
                $piece,
                'Pièce complémentaire (contrôle)',
                'Pièce jointe lors du contrôle suivi des dépenses'
            );
        }

        try {
            $this->moteur->confirmerControleDepense(
                $courrier->fresh(),
                $request->user(),
                $request->validated('message'),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Contrôle confirmé — dossier clôturé.');
    }

    public function soumettreReponse(SoumettreReponseCourrierRequest $request, Courrier $courrier): RedirectResponse
    {
        $typesDisponibles = TypeDocument::query()
            ->whereIn('code', app(ParapheurDepartService::class)->codesTypesDocument())
            ->where('actif', true)
            ->get();
        $typeDocument = $typesDisponibles->firstWhere('code', 'LETTRE') ?? $typesDisponibles->first();

        if (! $typeDocument) {
            return back()->with('error', 'Aucun type de document configuré pour le dépôt de la réponse.');
        }

        $directeur = $this->moteur->resoudreActeurDirecteur($courrier)
            ?? $this->secretariatService->directeurPourSecretariat($request->user()->structurePourValidationHierarchique());

        if (! $directeur) {
            return back()->with('error', 'Aucun directeur / DG trouvé pour la signature.');
        }

        if ($courrier->reponseDepartEnAttenteSignature()) {
            return back()->with('error', 'Un courrier de réponse est déjà en attente de signature.');
        }

        try {
            $reponse = DB::transaction(function () use ($request, $courrier, $typeDocument, $directeur) {
                $document = app(ParapheurDepartService::class)->deposerPiece(
                    $request->user(),
                    $request->file('document_reponse'),
                    $typeDocument->id
                );

                $sensDepart = SensCourrier::where('code', SensCourrier::DEPART)->firstOrFail();
                $statut = StatutCourrier::where('sens_courrier_id', $sensDepart->id)
                    ->where('code', 'transmis_directeur')
                    ->firstOrFail();
                $nums = $this->numeroService->prochainNumero((int) $sensDepart->id);

                $objetDepart = $courrier->objetReponseDepartParDefaut();

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
                    'objet' => $objetDepart,
                    'createur_id' => $request->user()->id,
                    'directeur_en_attente_id' => $directeur->id,
                    'structure_id' => $request->user()->structure_id,
                    'dossier_id' => $courrier->dossier_id,
                ]);

                $reponse->documents()->attach($document->id, ['est_principal' => true]);

                $this->moteur->soumettreDepartPourSignature($courrier, $request->user(), [
                    'document_id' => $document->id,
                    'objet' => $objetDepart,
                    'numero_reponse' => $reponse->numeroRegistreComplet(),
                    'reponse_id' => $reponse->id,
                ]);

                return $reponse;
            });
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('courriers.show', $courrier)
            ->with('success', 'Courrier de réponse n° '.$reponse->numeroRegistreComplet().' transmis au DG pour signature.');
    }

    public function validerReponse(ValiderReponseCourrierRequest $request, Courrier $courrier): RedirectResponse
    {
        $depart = $courrier->reponseDepartEnAttenteSignature();
        if (! $depart) {
            return back()->with('error', 'Aucun courrier de réponse en attente de signature.');
        }

        try {
            DB::transaction(function () use ($request, $courrier, $depart) {
                $this->workflowService->transitionner($depart, 'signe', [
                    'signataire_id' => $request->user()->id,
                    'directeur_en_attente_id' => null,
                ]);

                $this->moteur->signerReponseDepart(
                    $courrier,
                    $depart->fresh(['statutCourrier']),
                    $request->user(),
                    $request->input('commentaire')
                );

                $this->courrierNotifications->notifierCreateur(
                    $depart->fresh(),
                    $request->user(),
                    CourrierNotificationService::VALIDE_POUR_ENVOI
                );
            });
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('courriers.show', $depart->fresh())
            ->with('success', 'Réponse signée — la particulière peut maintenant l’expédier.');
    }

    public function rejeterReponse(RejeterReponseCourrierRequest $request, Courrier $courrier): RedirectResponse
    {
        $depart = $courrier->reponseDepartEnAttenteSignature();
        $motif = $request->validated('motif_rejet');

        try {
            DB::transaction(function () use ($request, $courrier, $depart, $motif) {
                if ($depart) {
                    $this->workflowService->transitionner($depart, 'rejete_directeur', [
                        'motif_rejet' => $motif,
                        'rejete_par_id' => $request->user()->id,
                        'date_rejet' => now(),
                        'directeur_en_attente_id' => null,
                    ]);
                }

                $this->moteur->rejeterReponse($courrier, $request->user(), $motif);
            });
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Réponse rejetée — la particulière en est informée.');
    }

    public function relancer(Request $request, Courrier $courrier): RedirectResponse
    {
        if (! $request->user()->aAccesTotal()) {
            abort(403);
        }

        $request->validate([
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $courrier->circuit_etape_actuelle_id) {
            return back()->with('error', 'Aucun circuit actif à relancer.');
        }

        app(CourrierRetardService::class)->relancer(
            $courrier,
            $request->user(),
            $request->input('commentaire')
        );

        return back()->with('success', 'Relance envoyée au responsable de l’étape.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validerCircuit(Request $request, ?CircuitCourrier $circuit = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('circuit_courriers', 'code')->ignore($circuit?->id),
            ],
            'libelle' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sens_initial' => ['required', 'in:arrivee,depart'],
            'actif' => ['nullable', 'boolean'],
            'etapes' => ['required', 'array', 'min:1'],
            'etapes.*.code' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/'],
            'etapes.*.nom' => ['required', 'string', 'max:200'],
            'etapes.*.acteur_type' => ['required', 'in:role,fonction,secretariat,dg,directeur_destinataire'],
            'etapes.*.acteur_valeur' => ['nullable', 'string', 'max:100'],
            'etapes.*.action' => ['required', 'string', 'max:40'],
            'etapes.*.mouvement' => ['required', 'in:aucun,creer_depart,attendre_arrivee'],
            'etapes.*.instructions_aide' => ['nullable', 'string', 'max:2000'],
            'etapes.*.est_finale' => ['nullable', 'boolean'],
            'etapes.*.notifie_roles' => ['nullable', 'array'],
            'etapes.*.notifie_roles.*' => ['string', 'max:50'],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $etapes
     */
    protected function syncEtapes(CircuitCourrier $circuit, array $etapes): void
    {
        $codes = [];
        foreach (array_values($etapes) as $index => $data) {
            $codes[] = $data['code'];
            CircuitCourrierEtape::updateOrCreate(
                [
                    'circuit_courrier_id' => $circuit->id,
                    'code' => $data['code'],
                ],
                [
                    'ordre' => $index + 1,
                    'nom' => $data['nom'],
                    'acteur_type' => $data['acteur_type'],
                    'acteur_valeur' => $data['acteur_valeur'] ?? null,
                    'action' => $data['action'],
                    'mouvement' => $data['mouvement'] ?? 'aucun',
                    'notifie_roles' => $data['notifie_roles'] ?? [],
                    'instructions_aide' => $data['instructions_aide'] ?? null,
                    'est_finale' => (bool) ($data['est_finale'] ?? false),
                    'actif' => true,
                ]
            );
        }

        CircuitCourrierEtape::query()
            ->where('circuit_courrier_id', $circuit->id)
            ->whereNotIn('code', $codes)
            ->update(['actif' => false]);
    }

    /**
     * @return array<string, string>
     */
    protected function actionsDisponibles(): array
    {
        return [
            'enregistrer' => 'Enregistrer',
            'instruire' => 'Instruire / orienter',
            'traiter' => 'Traiter',
            'transmettre' => 'Transmettre',
            'signer' => 'Signer',
            'valider' => 'Valider / rejeter',
            'cloturer' => 'Clôturer',
            'notifier' => 'Notifier',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function mouvementsDisponibles(): array
    {
        return [
            'aucun' => 'Aucun',
            'creer_depart' => 'Créer un courrier départ',
            'attendre_arrivee' => 'Attendre un courrier arrivée lié',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function acteurTypesDisponibles(): array
    {
        return [
            'secretariat' => 'Secrétariat DG',
            'dg' => 'Directeur Général (uniquement)',
            'directeur_destinataire' => 'Directeur de la structure destinataire (repli sur le DG)',
            'role' => 'Rôle Spatie',
            'fonction' => 'Fonction métier',
        ];
    }

    private function attacherScanCheque(Courrier $courrier, UploadedFile $file): void
    {
        $this->attacherPieceCourrier($courrier, $file, 'Scan chèque', 'Scan du chèque transmis au DG');
    }

    /**
     * @return list<UploadedFile>
     */
    private function collecterFichiersUpload(Request $request, string $cleMultiple, string $cleUnique): array
    {
        $fichiers = [];

        if ($request->hasFile($cleMultiple)) {
            foreach ((array) $request->file($cleMultiple) as $fichier) {
                if ($fichier) {
                    $fichiers[] = $fichier;
                }
            }
        }

        if ($request->hasFile($cleUnique)) {
            $fichiers[] = $request->file($cleUnique);
        }

        return $fichiers;
    }

    private function attacherPieceCourrier(Courrier $courrier, UploadedFile $file, string $titre, string $description): void
    {
        $typeDoc = TypeDocument::query()
            ->whereIn('code', ['COURRIER_IN', 'COURRIER', 'LETTRE'])
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
            'titre' => $titre,
            'description' => $description,
            'statut' => 'brouillon',
            'statut_document_id' => $statut?->id,
            'en_corbeille' => false,
            'confidentiel' => (bool) $courrier->est_confidentiel,
        ]);

        $courrier->documents()->attach($document->id, ['est_principal' => false]);
    }
}
