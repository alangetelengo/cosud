<?php

namespace App\Models;

use App\Services\CourrierVisibiliteService;
use App\Services\FactureRegularisationService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Courrier extends Model
{
    public const ORIGINE_INTERNE = 'interne';

    public const ORIGINE_EXTERNE = 'externe';

    protected $fillable = [
        'sens_courrier_id',
        'type_courrier_id',
        'circuit_courrier_id',
        'circuit_etape_actuelle_id',
        'circuit_etape_depuis',
        'dernier_alerte_retard_at',
        'statut_courrier_id',
        'priorite_courrier_id',
        'parapheur_id',
        'numero_registre',
        'numero_registre_annee',
        'reference',
        'origine',
        'date_reception',
        'date_courrier',
        'numero_fulgurant',
        'expediteur_libelle',
        'fournisseur_prestataire_id',
        'expediteur_email',
        'expediteur_telephone',
        'destinataire_libelle',
        'est_expediteur_externe',
        'structure_expediteur_id',
        'structure_destinataire_id',
        'service_demandeur_structure_id',
        'objet',
        'montant_facture',
        'est_regularisation',
        'regularisation_paiement',
        'regularisation_mode_paiement',
        'regularisation_date_programmation',
        'regularisation_numero_piece',
        'regularisation_banque',
        'regularisation_montant_mensuel',
        'regularisation_nb_mois_impayes',
        'est_confidentiel',
        'orientation_mode',
        'nombre_pieces',
        'numero_archives',
        'observations',
        'instructions_dg',
        'mode_paiement_circuit',
        'delai_execution_jours',
        'agent_confie_id',
        'message_ac',
        'date_orientation',
        'date_expedition',
        'createur_id',
        'signataire_id',
        'directeur_en_attente_id',
        'motif_rejet',
        'rejete_par_id',
        'date_rejet',
        'structure_id',
        'dossier_id',
        'courrier_parent_id',
        'courrier_depart_source_id',
        'courrier_arrivee_lie_id',
        'document_reponse_id',
        'reponse_confidentielle',
        'reponse_structure_destinataire_id',
        'destinataire_agent_id',
        'reponse_objet',
    ];

    public const MODE_PAIEMENT_CHEQUE = 'cheque';

    public const MODE_PAIEMENT_OV = 'ov';

    /** @var list<string> */
    public const MODES_PAIEMENT_CIRCUIT = [
        self::MODE_PAIEMENT_CHEQUE,
        self::MODE_PAIEMENT_OV,
    ];

    protected function casts(): array
    {
        return [
            'date_reception' => 'date',
            'date_courrier' => 'date',
            'date_orientation' => 'datetime',
            'date_expedition' => 'datetime',
            'date_rejet' => 'datetime',
            'est_expediteur_externe' => 'boolean',
            'est_confidentiel' => 'boolean',
            'reponse_confidentielle' => 'boolean',
            'nombre_pieces' => 'integer',
            'montant_facture' => 'decimal:2',
            'est_regularisation' => 'boolean',
            'regularisation_montant_mensuel' => 'decimal:2',
            'regularisation_nb_mois_impayes' => 'integer',
            'regularisation_date_programmation' => 'date',
            'delai_execution_jours' => 'integer',
            'circuit_etape_depuis' => 'datetime',
            'dernier_alerte_retard_at' => 'datetime',
        ];
    }

    public function typeCourrier(): BelongsTo
    {
        return $this->belongsTo(TypeCourrier::class);
    }

    public function estTypeFacture(): bool
    {
        if ($this->relationLoaded('typeCourrier')) {
            return $this->typeCourrier?->code === 'facture';
        }

        return $this->typeCourrier()->where('code', 'facture')->exists();
    }

    public function necessiteChoixModePaiementCircuit(): bool
    {
        $this->loadMissing(['circuit', 'typeCourrier']);

        return $this->circuit?->code === 'facture_prestataire'
            || $this->typeCourrier?->code === 'facture';
    }

    public function estModePaiementOv(): bool
    {
        return $this->mode_paiement_circuit === self::MODE_PAIEMENT_OV;
    }

    public function estModePaiementCheque(): bool
    {
        return $this->mode_paiement_circuit === self::MODE_PAIEMENT_CHEQUE
            || ($this->mode_paiement_circuit === null && $this->necessiteChoixModePaiementCircuit());
    }

    public function libelleModePaiementCircuit(): string
    {
        return match ($this->mode_paiement_circuit) {
            self::MODE_PAIEMENT_OV => 'Ordre de virement',
            self::MODE_PAIEMENT_CHEQUE => 'Chèque',
            default => '—',
        };
    }

    public function circuit(): BelongsTo
    {
        return $this->belongsTo(CircuitCourrier::class, 'circuit_courrier_id');
    }

    public function circuitEtapeActuelle(): BelongsTo
    {
        return $this->belongsTo(CircuitCourrierEtape::class, 'circuit_etape_actuelle_id');
    }

    public function circuitHistoriques(): HasMany
    {
        return $this->hasMany(CircuitCourrierHistorique::class)->orderByDesc('created_at');
    }

    public function sensCourrier(): BelongsTo
    {
        return $this->belongsTo(SensCourrier::class);
    }

    public function statutCourrier(): BelongsTo
    {
        return $this->belongsTo(StatutCourrier::class);
    }

    public function prioriteCourrier(): BelongsTo
    {
        return $this->belongsTo(PrioriteCourrier::class);
    }

    public function parapheur(): BelongsTo
    {
        return $this->belongsTo(Parapheur::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createur_id');
    }

    public function fournisseurPrestataire(): BelongsTo
    {
        return $this->belongsTo(FournisseurPrestataire::class, 'fournisseur_prestataire_id');
    }

    public function signataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signataire_id');
    }

    public function directeurEnAttente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'directeur_en_attente_id');
    }

    public function rejetePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejete_par_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function structureExpediteur(): BelongsTo
    {
        return $this->belongsTo(Structure::class, 'structure_expediteur_id');
    }

    public function structureDestinataire(): BelongsTo
    {
        return $this->belongsTo(Structure::class, 'structure_destinataire_id');
    }

    /**
     * Direction / service demandeur (factures et MAD) — référentiel structures type direction.
     */
    public function serviceDemandeurStructure(): BelongsTo
    {
        return $this->belongsTo(Structure::class, 'service_demandeur_structure_id');
    }

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    public function documentReponse(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_reponse_id');
    }

    public function reponseStructureDestinataire(): BelongsTo
    {
        return $this->belongsTo(Structure::class, 'reponse_structure_destinataire_id');
    }

    /**
     * Agent destinataire direct : soit l'agent proposé (réponse confidentielle en attente de
     * validation sur le courrier arrivée), soit l'agent réellement destinataire d'un courrier
     * départ confidentiel (à la place d'une structure).
     */
    public function destinataireAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinataire_agent_id');
    }

    /**
     * Agent principal à qui le DG a confié le dossier lors de l’instruction (compat. mono).
     */
    public function agentConfie(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_confie_id');
    }

    /**
     * Destinataires auxquels le DG a confié / envoyé le dossier (multi-select).
     */
    public function agentsConfies(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'courrier_agents_confies')
            ->withTimestamps();
    }

    /**
     * Lectures par utilisateur (logique type Gmail : gras tant que non ouvert).
     */
    public function lectures(): HasMany
    {
        return $this->hasMany(CourrierLecture::class);
    }

    public function aEteLuPar(?User $user): bool
    {
        if (! $user) {
            return true;
        }

        if ($this->relationLoaded('lectures')) {
            return $this->lectures->contains(fn (CourrierLecture $l) => (int) $l->user_id === (int) $user->id);
        }

        return $this->lectures()->where('user_id', $user->id)->exists();
    }

    public function marquerLuPar(User $user): void
    {
        CourrierLecture::query()->updateOrCreate(
            [
                'courrier_id' => $this->id,
                'user_id' => $user->id,
            ],
            [
                'lu_at' => now(),
            ]
        );
    }

    /**
     * @return list<string>
     */
    public function libellesAgentsConfies(): array
    {
        $this->loadMissing(['agentsConfies.structure', 'agentConfie.structure']);

        $agents = $this->agentsConfies->isNotEmpty()
            ? $this->agentsConfies
            : collect($this->agentConfie ? [$this->agentConfie] : []);

        return $agents
            ->map(fn (User $u) => $u->libelleDestinataireCourrier())
            ->unique()
            ->values()
            ->all();
    }

    public function courrierParent(): BelongsTo
    {
        return $this->belongsTo(Courrier::class, 'courrier_parent_id');
    }

    public function courrierDepartSource(): BelongsTo
    {
        return $this->belongsTo(Courrier::class, 'courrier_depart_source_id');
    }

    public function courrierArriveeLie(): BelongsTo
    {
        return $this->belongsTo(Courrier::class, 'courrier_arrivee_lie_id');
    }

    public function reponsesDepart(): HasMany
    {
        return $this->hasMany(Courrier::class, 'courrier_parent_id');
    }

    public function reponseDepartEnAttenteSignature(): ?self
    {
        return $this->reponsesDepart()
            ->whereHas('statutCourrier', fn ($q) => $q->where('code', 'transmis_directeur'))
            ->latest('id')
            ->first();
    }

    public function reponseDepartSigneeEnAttenteExpedition(): ?self
    {
        return $this->reponsesDepart()
            ->whereHas('statutCourrier', fn ($q) => $q->where('code', 'signe'))
            ->latest('id')
            ->first();
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'courrier_document')
            ->withPivot('est_principal')
            ->withTimestamps();
    }

    /**
     * Dernier paiement enregistré (compatibilité circuit / affichage courant).
     */
    public function suiviPaiement(): HasOne
    {
        return $this->hasOne(SuiviPaiement::class)->latestOfMany();
    }

    /**
     * Tous les paiements liés à la facture (chèque initial + reliquats).
     */
    public function suiviPaiements(): HasMany
    {
        return $this->hasMany(SuiviPaiement::class)->orderBy('id');
    }

    public function orientations(): HasMany
    {
        return $this->hasMany(CourrierOrientation::class);
    }

    public function orientationNotifies(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'courrier_orientation_notifications')
            ->withTimestamps();
    }

    public function transmissions(): HasMany
    {
        return $this->hasMany(CourrierTransmission::class)->orderByDesc('date_transmission');
    }

    public function ventilationDestinataires(): HasMany
    {
        return $this->hasMany(CourrierVentilationDestinataire::class);
    }

    public function estArrivee(): bool
    {
        return $this->sensCourrier?->code === SensCourrier::ARRIVEE;
    }

    public function estDepart(): bool
    {
        return $this->sensCourrier?->code === SensCourrier::DEPART;
    }

    public function estOrigineInterne(): bool
    {
        return $this->origine === self::ORIGINE_INTERNE;
    }

    public function estOrigineExterne(): bool
    {
        return $this->origine === self::ORIGINE_EXTERNE;
    }

    public function estRegularisation(): bool
    {
        return (bool) $this->est_regularisation;
    }

    public function estRegularisationPayee(): bool
    {
        return $this->estRegularisation()
            && $this->regularisation_paiement === FactureRegularisationService::PAIEMENT_PAYEE;
    }

    public function estRegularisationProgrammee(): bool
    {
        return $this->estRegularisation()
            && $this->regularisation_paiement === FactureRegularisationService::PAIEMENT_PROGRAMMEE;
    }

    public function estRegularisationImpayee(): bool
    {
        return $this->estRegularisation()
            && $this->regularisation_paiement === FactureRegularisationService::PAIEMENT_IMPAYEE;
    }

    public function estRegularisationContratMensuel(): bool
    {
        return $this->estRegularisation()
            && $this->regularisation_paiement === FactureRegularisationService::PAIEMENT_CONTRAT_MENSUEL;
    }

    /**
     * Régularisation encore modifiable / supprimable par Taty (pas encore payée).
     */
    public function regularisationModifiable(): bool
    {
        if (! $this->estRegularisation() || $this->estRegularisationPayee()) {
            return false;
        }

        if ($this->relationLoaded('suiviPaiement')) {
            return $this->suiviPaiement === null;
        }

        return ! $this->suiviPaiement()->exists();
    }

    /**
     * Numéro métier affiché (saisi par le secrétariat), ex. « 192/2026/DAF/SAGP » ou « 45/2026 ».
     * Repli : référence départ auto, puis compteur technique interne.
     */
    public function numeroRegistreComplet(): string
    {
        $saisi = trim((string) ($this->numero_fulgurant ?? ''));
        if ($saisi !== '') {
            return $saisi;
        }

        if ($this->estDepart()) {
            $reference = trim((string) ($this->reference ?? ''));
            if ($reference !== '') {
                return $reference;
            }
        }

        if ($this->numero_registre !== null && $this->numero_registre_annee !== null) {
            return sprintf('%d/%d', $this->numero_registre, $this->numero_registre_annee);
        }

        return '—';
    }

    /**
     * Objet du courrier départ créé en réponse à cette arrivée (registre).
     * Toujours dérivé de l’objet d’arrivée — pas de saisie libre à la création.
     */
    public function objetReponseDepartParDefaut(): string
    {
        $objet = trim((string) $this->objet);

        return $objet !== '' ? 'Réponse — '.$objet : 'Réponse';
    }

    /**
     * Libellé « Date et n° de la réponse » pour le registre papier Arrivée.
     */
    public function libelleReponseRegistre(): ?string
    {
        $reponse = $this->relationLoaded('reponsesDepart')
            ? $this->reponsesDepart->sortBy('date_expedition')->first()
            : $this->reponsesDepart()->orderBy('date_expedition')->first();

        if (! $reponse) {
            return null;
        }

        $date = $reponse->date_expedition?->format('d/m/Y')
            ?? $reponse->date_courrier?->format('d/m/Y');

        $parts = [];
        if ($date) {
            $parts[] = 'le '.$date;
        }
        $parts[] = 'n° '.$reponse->numeroRegistreComplet();

        return implode("\n", $parts);
    }

    public function enAttenteReceptionInterne(): bool
    {
        return $this->estDepart()
            && $this->statutCourrier?->code === 'expedie'
            && $this->courrier_arrivee_lie_id === null
            && $this->structure_destinataire_id !== null;
    }

    public function visiblePar(User $user): bool
    {
        return app(CourrierVisibiliteService::class)->estVisible($this, $user);
    }

    public function scopeVisibleBy(Builder $query, User $user): Builder
    {
        return app(CourrierVisibiliteService::class)->appliquerFiltreListe($query, $user);
    }

    /**
     * Courrier du registre du secrétariat, créé par l’utilisateur, ou départ à réceptionner.
     */
    public function appartientAuPerimetreSecretariat(User $user): bool
    {
        $structureId = (int) $user->structure_id;

        if ($structureId > 0 && (int) $this->structure_id === $structureId) {
            return true;
        }

        if ((int) $this->createur_id === (int) $user->id) {
            return true;
        }

        return $this->enAttenteReceptionInterne()
            && (int) $this->structure_destinataire_id === $structureId;
    }

    /**
     * Un projet de réponse (document + destinataire proposé) a été soumis par la particulière
     * et attend la validation (ou le rejet) du DG — cf. étape « validation_reponse_dg ».
     */
    public function aUnProjetReponseEnAttente(): bool
    {
        return (bool) $this->document_reponse_id;
    }

    public function peutCorrigerEnregistrement(User $user): bool
    {
        if ($this->estDepart()) {
            if (! in_array($this->statutCourrier?->code, ['brouillon', 'rejete_directeur'], true)) {
                return false;
            }

            return $user->aAccesTotal()
                || $user->gereCourrierSecretariat()
                || $user->hasRole('particulier_dg');
        }

        if (! $this->estArrivee()) {
            return false;
        }

        if (in_array($this->statutCourrier?->code, ['cloture', 'archive', 'annule'], true)) {
            return false;
        }

        return $user->aAccesTotal()
            || $user->hasRole('particulier_dg')
            || $user->hasRole('responsable_dossiers_prestataires');
    }

    public function peutAnnulerEnregistrement(User $user): bool
    {
        if (in_array($this->statutCourrier?->code, ['cloture', 'archive', 'annule'], true)) {
            return false;
        }

        if ($this->estArrivee()) {
            if (! $this->peutTransitionnerVers('annule')) {
                return false;
            }

            if (! $user->can('courriers.edit')) {
                return false;
            }

            return $user->aAccesTotal()
                || $user->hasRole('particulier_dg');
        }

        if ($this->estDepart()) {
            $code = $this->statutCourrier?->code ?? '';

            if ($code === 'transmis_directeur') {
                return $user->can('courriers.rejeter')
                    && (int) $this->directeur_en_attente_id === (int) $user->id;
            }

            if (in_array($code, ['brouillon', 'rejete_directeur'], true)) {
                return $user->can('courriers.edit')
                    && $user->gereCourrierSecretariat()
                    && (int) $this->structure_id === (int) $user->structure_id;
            }
        }

        return false;
    }

    public function peutSupprimerEnregistrement(): bool
    {
        if ($this->est_regularisation) {
            return false;
        }

        if ($this->courrier_parent_id !== null) {
            return false;
        }

        if ($this->reponsesDepart()->exists()) {
            return false;
        }

        if ($this->suiviPaiements()->exists()) {
            return false;
        }

        if (self::query()->where('courrier_arrivee_lie_id', $this->id)->exists()) {
            return false;
        }

        if ($this->estArrivee()) {
            return in_array($this->statutCourrier?->code, ['recu', 'annule'], true);
        }

        if ($this->estDepart()) {
            return in_array($this->statutCourrier?->code, ['brouillon', 'rejete_directeur', 'annule'], true);
        }

        return false;
    }

    public function peutSupprimerEnregistrementPar(User $user): bool
    {
        if (! $this->peutSupprimerEnregistrement()) {
            return false;
        }

        if ($this->estArrivee()) {
            return $user->aAccesTotal()
                || $user->hasRole('particulier_dg');
        }

        if ($this->estDepart()) {
            return $user->aAccesTotal()
                || $user->gereCourrierSecretariat()
                || $user->hasRole('particulier_dg');
        }

        return false;
    }

    public function motifAnnulationRequis(): bool
    {
        if ($this->estDepart() && $this->statutCourrier?->code === 'transmis_directeur') {
            return true;
        }

        return $this->estArrivee()
            && in_array($this->statutCourrier?->code, ['oriente', 'ventile'], true);
    }

    public function cleFormulaireAnnulation(): ?string
    {
        if ($this->estArrivee()) {
            return 'annuler-arrivee';
        }

        if ($this->estDepart()) {
            return match ($this->statutCourrier?->code) {
                'transmis_directeur' => 'annuler-directeur',
                'brouillon', 'rejete_directeur' => 'annuler-brouillon',
                default => null,
            };
        }

        return null;
    }

    public function peutEnregistrerTransmission(): bool
    {
        $code = $this->statutCourrier?->code ?? '';

        // Départ : l’expédition clôt les actions ; plus de « Transmission / trace d’envoi ».
        if ($this->estDepart()) {
            return false;
        }

        return in_array($code, ['oriente', 'ventile'], true);
    }

    public function aAccuseReceptionEnregistre(): bool
    {
        return $this->transmissions()
            ->where('accuse_reception', true)
            ->exists();
    }

    public function peutEtreArchive(): bool
    {
        $code = $this->statutCourrier?->code ?? '';

        // Départ : infos registre saisies à l’expédition — plus d’action « Archiver » après envoi.
        if ($this->estDepart()) {
            return $code === 'reception_refusee';
        }

        return $code === 'ventile';
    }

    public function peutTransitionnerVers(string $codeStatut): bool
    {
        $transitions = $this->estArrivee()
            ? [
                'recu' => ['en_parapheur', 'annule'],
                'en_parapheur' => ['oriente', 'attente_reponse_particuliere', 'annule'],
                'attente_reponse_particuliere' => ['oriente', 'cloture', 'annule'],
                'oriente' => ['ventile', 'cloture', 'annule'],
                'ventile' => ['cloture', 'annule'],
                'annule' => [],
            ]
            : [
                'brouillon' => ['transmis_directeur', 'annule'],
                'transmis_directeur' => ['signe', 'rejete_directeur', 'annule'],
                'rejete_directeur' => ['brouillon', 'transmis_directeur', 'annule'],
                'signe' => ['expedie'],
                'expedie' => ['archive', 'reception_refusee'],
                'reception_refusee' => ['brouillon'],
                'annule' => [],
            ];

        $actuel = $this->statutCourrier?->code ?? '';

        return in_array($codeStatut, $transitions[$actuel] ?? [], true);
    }

    /**
     * Échéance de traitement calculée depuis la date d’orientation + délai en jours (facultatif).
     */
    public function dateEcheanceExecution(): ?CarbonInterface
    {
        if ($this->delai_execution_jours === null || $this->date_orientation === null) {
            return null;
        }

        return $this->date_orientation->copy()->startOfDay()->addDays((int) $this->delai_execution_jours);
    }

    public function libelleDelaiExecution(): ?string
    {
        if ($this->delai_execution_jours === null) {
            return null;
        }

        $jours = (int) $this->delai_execution_jours;
        $libelle = $jours.' jour'.($jours > 1 ? 's' : '');
        $echeance = $this->dateEcheanceExecution();

        if ($echeance) {
            $libelle .= ' (échéance '.$echeance->format('d/m/Y').')';
        }

        return $libelle;
    }
}
