<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeposerPreuvePaiementRequest;
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
use App\Models\StatutDocument;
use App\Models\TypeDocument;
use App\Services\CircuitCourrierMoteurService;
use App\Services\CourrierRetardService;
use App\Services\ParapheurDepartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class CircuitCourrierController extends Controller
{
    public function __construct(
        private readonly CircuitCourrierMoteurService $moteur,
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
                'courriers.circuit.deposer-preuve-paiement'
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
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Instructions enregistrées et transmises pour traitement.');
    }

    public function envoyerCheque(EnvoyerChequeAcRequest $request, Courrier $courrier): RedirectResponse
    {
        if ($request->hasFile('scan_cheque')) {
            $this->attacherScanCheque($courrier, $request->file('scan_cheque'));
        }

        try {
            $this->moteur->envoyerChequeAuDg(
                $courrier->fresh(),
                $request->user(),
                $request->validated('message')
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Chèque transmis au DG pour signature.');
    }

    public function signerCheque(SignerChequeDgRequest $request, Courrier $courrier): RedirectResponse
    {
        $this->attacherPieceCourrier(
            $courrier,
            $request->file('scan_cheque_signe'),
            'Scan chèque signé',
            'Chèque signé par le DG'
        );

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

        return back()->with('success', 'Chèque signé enregistré. Le fournisseur a été notifié pour le recouvrement si demandé.');
    }

    public function deposerPreuvePaiement(DeposerPreuvePaiementRequest $request, Courrier $courrier): RedirectResponse
    {
        $this->attacherPieceCourrier(
            $courrier,
            $request->file('preuve_paiement'),
            'Preuve de paiement',
            'Preuve de paiement fournisseur / prestataire'
        );

        try {
            $this->moteur->deposerPreuvePaiement(
                $courrier->fresh(),
                $request->user(),
                $request->validated('message'),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Preuve de paiement enregistrée — dossier clôturé.');
    }

    public function soumettreReponse(SoumettreReponseCourrierRequest $request, Courrier $courrier): RedirectResponse
    {
        // La confidentialité a déjà été appréciée par le DG/directeur à l'orientation du
        // courrier : la particulière ne peut pas la modifier ni choisir l'agent destinataire.
        $confidentielle = (bool) $courrier->est_confidentiel;

        $typesDisponibles = TypeDocument::query()
            ->whereIn('code', app(ParapheurDepartService::class)->codesTypesDocument())
            ->where('actif', true)
            ->get();
        $typeDocument = $typesDisponibles->firstWhere('code', 'LETTRE') ?? $typesDisponibles->first();

        if (! $typeDocument) {
            return back()->with('error', 'Aucun type de document configuré pour le dépôt de la réponse.');
        }

        $document = app(ParapheurDepartService::class)->deposerPiece(
            $request->user(),
            $request->file('document_reponse'),
            $typeDocument->id
        );

        try {
            $this->moteur->soumettreReponsePourValidation($courrier, $request->user(), [
                'document_reponse_id' => $document->id,
                'reponse_confidentielle' => $confidentielle,
                // Destinataire (structure ou agent) : choisi exclusivement par le DG à la validation.
                'reponse_structure_destinataire_id' => null,
                'destinataire_agent_id' => null,
                'reponse_objet' => $request->input('objet'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Projet de réponse soumis au DG pour validation.');
    }

    public function validerReponse(ValiderReponseCourrierRequest $request, Courrier $courrier): RedirectResponse
    {
        try {
            $this->moteur->validerProjetVersParticuliere(
                $courrier,
                $request->user(),
                $request->input('commentaire')
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Projet validé — la particulière peut maintenant créer le courrier départ en brouillon.');
    }

    public function rejeterReponse(RejeterReponseCourrierRequest $request, Courrier $courrier): RedirectResponse
    {
        try {
            $this->moteur->rejeterReponse($courrier, $request->user(), $request->validated('motif_rejet'));
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Projet de réponse rejeté — la particulière en est informée.');
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
