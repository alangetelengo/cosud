<?php

namespace App\Models;

use App\Services\CircuitCourrierMoteurService;
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
        'expediteur_email',
        'expediteur_telephone',
        'destinataire_libelle',
        'est_expediteur_externe',
        'structure_expediteur_id',
        'structure_destinataire_id',
        'service_demandeur_structure_id',
        'objet',
        'est_confidentiel',
        'orientation_mode',
        'nombre_pieces',
        'numero_archives',
        'observations',
        'instructions_dg',
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
            'circuit_etape_depuis' => 'datetime',
            'dernier_alerte_retard_at' => 'datetime',
        ];
    }

    public function typeCourrier(): BelongsTo
    {
        return $this->belongsTo(TypeCourrier::class);
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

    public function suiviPaiement(): HasOne
    {
        return $this->hasOne(SuiviPaiement::class);
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

    public function numeroRegistreComplet(): string
    {
        return sprintf('%d/%d', $this->numero_registre, $this->numero_registre_annee);
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
        if ($user->aAccesTotal()) {
            return true;
        }

        if ($user->hasRole('responsable_dossiers_prestataires')
            || $user->hasRole('responsable_suivi_depenses')
            || $user->hasRole('agent_comptable')
            || $user->hasRole('caissier')) {
            return true;
        }

        if (($user->gereCourrierSecretariat() || $user->hasRole('particulier_dg'))
            && $this->appartientAuPerimetreSecretariat($user)) {
            return true;
        }

        if ($user->peutSignerCourrierDepart()
            && $this->estDepart()
            && (int) $this->directeur_en_attente_id === (int) $user->id) {
            return true;
        }

        // Réponse confidentielle adressée directement à un agent (pas à une structure) :
        // l'agent destinataire doit pouvoir consulter son courrier départ.
        if ($this->estDepart()
            && $this->destinataire_agent_id
            && (int) $this->destinataire_agent_id === (int) $user->id) {
            return true;
        }

        if ($this->ventilationDestinataires()->where('user_id', $user->id)->exists()) {
            return true;
        }

        if ((int) $this->createur_id === (int) $user->id) {
            return true;
        }

        if ($this->enAttenteReceptionInterne()
            && (int) $this->structure_destinataire_id === (int) $user->structure_id) {
            return $user->gereCourrierSecretariat();
        }

        // Acteur courant du circuit métier (ex. directeur de la structure destinataire
        // sur l’étape d’instruction) : doit pouvoir consulter le courrier pour y agir,
        // même sans lien de secrétariat/ventilation direct.
        if ($this->circuit_etape_actuelle_id
            && app(CircuitCourrierMoteurService::class)->peutAgir($this, $user)) {
            return true;
        }

        return false;
    }

    public function scopeVisibleBy(Builder $query, User $user): Builder
    {
        if ($user->aAccesTotal()) {
            return $query;
        }

        if ($user->hasRole('responsable_dossiers_prestataires')
            || $user->hasRole('responsable_suivi_depenses')
            || $user->hasRole('agent_comptable')
            || $user->hasRole('caissier')) {
            return $query;
        }

        if ($user->gereCourrierSecretariat() || $user->hasRole('particulier_dg')) {
            $structureId = (int) $user->structure_id;

            // Registre / listes : uniquement les courriers de CE secrétariat.
            // Les départs reçus d’une autre direction restent hors liste (page « À réceptionner »
            // + visiblePar pour ouvrir la fiche), puis deviennent une Arrivée après réception.
            return $query->where(function (Builder $q) use ($user, $structureId) {
                $q->where('structure_id', $structureId)
                    ->orWhere('createur_id', $user->id);
            });
        }

        return $query->where(function ($q) use ($user) {
            $q->where('createur_id', $user->id)
                ->orWhere('directeur_en_attente_id', $user->id)
                ->orWhere('destinataire_agent_id', $user->id)
                ->orWhereHas('ventilationDestinataires', fn ($vq) => $vq->where('user_id', $user->id));
        });
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
                'recu' => ['en_parapheur'],
                'en_parapheur' => ['oriente', 'attente_reponse_particuliere'],
                'attente_reponse_particuliere' => ['oriente', 'cloture'],
                'oriente' => ['ventile', 'cloture'],
                'ventile' => ['cloture'],
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
}
