<?php

namespace App\Models;

use App\Policies\DocumentPolicy;
use App\Services\CircuitCourrierMoteurService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    protected $fillable = [
        'type_document_id',
        'dossier_id',
        'user_id',
        'code',
        'nom_original',
        'chemin',
        'extension',
        'taille_octets',
        'titre',
        'description',
        'reference',
        'mots_cles',
        'confidentiel',
        'empreinte',
        'mime_type',
        'statut',
        'en_corbeille',
        'date_suppression',
        'createur_id',
        'modificateur_id',
        'proprietaire_id',
        'statut_document_id',
        'workflow_etape_actuelle_id',
        'workflow_validateur_id',
        'workflow_destinataire_id',
        'workflow_validation_chain',
        'workflow_etape_index',
    ];

    protected function casts(): array
    {
        return [
            'confidentiel' => 'boolean',
            'en_corbeille' => 'boolean',
            'date_suppression' => 'datetime',
            'workflow_validation_chain' => 'array',
        ];
    }

    public function typeDocument(): BelongsTo
    {
        return $this->belongsTo(TypeDocument::class);
    }

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createur_id');
    }

    public function modificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modificateur_id');
    }

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proprietaire_id');
    }

    public function statutDocument(): BelongsTo
    {
        return $this->belongsTo(StatutDocument::class);
    }

    public function workflowEtapeActuelle(): BelongsTo
    {
        return $this->belongsTo(WorkflowEtape::class, 'workflow_etape_actuelle_id');
    }

    public function workflowValidateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'workflow_validateur_id');
    }

    public function workflowDestinataires(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'document_workflow_destinataires')->withTimestamps();
    }

    public function courriers(): BelongsToMany
    {
        return $this->belongsToMany(Courrier::class, 'courrier_document')
            ->withPivot('est_principal')
            ->withTimestamps();
    }

    public function validations(): HasMany
    {
        return $this->hasMany(DocumentValidation::class)->orderByDesc('created_at');
    }

    /** Motif du dernier rejet (pour affichage au propriétaire). */
    public function dernierMotifRejet(): ?string
    {
        $rejet = $this->validations
            ->where('action', DocumentValidation::ACTION_REJET)
            ->first();

        return $rejet?->commentaire;
    }

    public function versions(): HasMany
    {
        return $this->hasMany(VersionDocument::class)->orderByDesc('numero');
    }

    public function versionActuelle(): HasOne
    {
        return $this->hasOne(VersionDocument::class)->where('est_actuel', true);
    }

    public function metadonnees(): HasMany
    {
        return $this->hasMany(MetadonneeDocument::class)->orderBy('ordre_affichage');
    }

    public function historiques(): HasMany
    {
        return $this->hasMany(HistoriqueDocument::class)->orderByDesc('created_at');
    }

    public function scopeHorsCorbeille($query)
    {
        return $query->where('en_corbeille', false);
    }

    /**
     * Documents visibles : aligné sur la visibilité du dossier (voir {@see Dossier::scopeVisibleBy}).
     *
     * Le groupe `$accesLegitime` doit rester synchronisé avec {@see self::accesConfidentielAutorise()} :
     * c'est la même liste de cas (propriétaire/créateur/déposant, ventilation courrier, directeur en
     * attente de signature, agent confié par le DG, acteur de l’étape circuit) qui lève la
     * restriction de confidentialité pour un accès individuel ({@see self::visiblePar()}) et
     * pour ce scope de liste/recherche.
     */
    public function scopeVisibleBy($query, User $user)
    {
        if ($user->aAccesTotal()) {
            return $query;
        }

        $peutVoirConfidentiel = $user->can('dossiers.view-confidentiel');
        $idsArbrePerso = Dossier::idsPourArbrePersonnel((int) $user->id);
        $idsVentiles = CourrierVentilationDestinataire::query()
            ->where('user_id', $user->id)
            ->whereNotNull('document_id')
            ->pluck('document_id');
        $idsCourrierDepartDirecteur = self::idsPourDirecteurEnAttenteSignature($user);
        $idsAgentConfie = self::idsPourAgentConfie($user);
        $idsActeurCircuit = self::idsPourActeurCircuitCourant($user);

        $accesLegitime = function ($q) use ($user, $idsVentiles, $idsCourrierDepartDirecteur, $idsAgentConfie, $idsActeurCircuit) {
            $q->where('proprietaire_id', $user->id)
                ->orWhere('createur_id', $user->id)
                ->orWhere('user_id', $user->id)
                ->orWhereIn('id', $idsVentiles)
                ->orWhereIn('id', $idsCourrierDepartDirecteur)
                ->orWhereIn('id', $idsAgentConfie)
                ->orWhereIn('id', $idsActeurCircuit);
        };

        return $query
            ->where(function ($q) use ($user, $idsArbrePerso, $accesLegitime) {
                $q->where($accesLegitime)
                    ->orWhere(function ($sub) use ($user) {
                        $sub->where('statut', 'en_attente')
                            ->where('workflow_validateur_id', $user->id);
                    })
                    ->orWhere(function ($sub) use ($user) {
                        $sub->where('statut', 'en_attente')
                            ->where('workflow_destinataire_id', $user->id);
                    })
                    ->orWhere(function ($sub) use ($user) {
                        $sub->where('statut', 'en_attente')
                            ->whereHas('workflowDestinataires', function ($wq) use ($user) {
                                $wq->where('users.id', $user->id);
                            });
                    })
                    ->orWhereHas('dossier', function ($dq) use ($user, $idsArbrePerso) {
                        $dq->where(function ($sub) use ($user, $idsArbrePerso) {
                            $sub->where('proprietaire_id', $user->id)
                                ->orWhere('createur_id', $user->id)
                                ->orWhereHas('partages', function ($pq) use ($user) {
                                    $pq->where('user_id', $user->id)
                                        ->where('droits_lecture', true)
                                        ->where(function ($exp) {
                                            $exp->whereNull('date_expiration')->orWhere('date_expiration', '>', now());
                                        });
                                });
                            if ($idsArbrePerso !== []) {
                                $sub->orWhereIn('id', $idsArbrePerso);
                            }
                        });
                    });
            })
            ->when(! $peutVoirConfidentiel, function ($q) use ($accesLegitime) {
                $q->where(function ($sub) use ($accesLegitime) {
                    $sub->where('confidentiel', false)->orWhere($accesLegitime);
                });
            });
    }

    /**
     * IDs des documents joints à un courrier départ transmis au directeur `$user` pour signature
     * (voir {@see self::visibleViaCourrierDepartPourDirecteur()} pour la version « un document »).
     */
    protected static function idsPourDirecteurEnAttenteSignature(User $user): Collection
    {
        if (! $user->peutSignerCourrierDepart()) {
            return collect();
        }

        return self::query()
            ->whereHas('courriers', function ($cq) use ($user) {
                $cq->where('directeur_en_attente_id', $user->id)
                    ->whereHas('sensCourrier', fn ($q) => $q->where('code', SensCourrier::DEPART))
                    ->whereHas('statutCourrier', fn ($q) => $q->where('code', 'transmis_directeur'));
            })
            ->pluck('id');
    }

    /** Vérifie si l'utilisateur peut voir ce document (dossier visible + confidentialité). */
    public function visiblePar(User $user): bool
    {
        if ($user->aAccesTotal()) {
            return true;
        }
        if ($this->accesConfidentielAutorise($user)) {
            return true;
        }
        if ($this->statut === 'en_attente' && (int) ($this->workflow_validateur_id ?? 0) === (int) $user->id) {
            if ($this->confidentiel && ! $user->can('dossiers.view-confidentiel')) {
                return false;
            }

            return true;
        }
        if ($this->statut === 'en_attente' && (int) ($this->workflow_destinataire_id ?? 0) === (int) $user->id) {
            if ($this->confidentiel && ! $user->can('dossiers.view-confidentiel')) {
                return false;
            }

            return true;
        }
        if ($this->statut === 'en_attente' && $this->workflowDestinataires()->whereKey($user->id)->exists()) {
            if ($this->confidentiel && ! $user->can('dossiers.view-confidentiel')) {
                return false;
            }

            return true;
        }
        $autoriseViaDossier = false;
        if ($this->dossier_id) {
            $dossier = $this->relationLoaded('dossier') ? $this->dossier : $this->dossier()->first();
            if ($dossier) {
                $autoriseViaDossier =
                    (int) $dossier->proprietaire_id === (int) $user->id
                    || (int) $dossier->createur_id === (int) $user->id
                    || in_array((int) $dossier->id, Dossier::idsPourArbrePersonnel((int) $user->id), true)
                    || $dossier->partages()
                        ->where('user_id', $user->id)
                        ->where('droits_lecture', true)
                        ->where(function ($q) {
                            $q->whereNull('date_expiration')->orWhere('date_expiration', '>', now());
                        })
                        ->exists();
            }
            if ($dossier && $autoriseViaDossier) {
                if ($this->confidentiel && ! $user->can('dossiers.view-confidentiel')) {
                    return false;
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Cas d'accès légitimes qui lèvent la restriction générale de confidentialité, même sans
     * la permission `dossiers.view-confidentiel` : propriétaire/créateur/déposant du document,
     * destinataire d'une ventilation courrier ciblée, directeur devant signer le courrier
     * départ auquel la pièce est jointe, ou agent à qui le DG a confié le dossier. Utilisé à
     * la fois par {@see self::visiblePar()} et par {@see DocumentPolicy::view()} pour rester cohérent.
     */
    public function accesConfidentielAutorise(User $user): bool
    {
        $lieAuDocument = $this->proprietaire_id === $user->id
            || $this->createur_id === $user->id
            || $this->user_id === $user->id;
        if ($lieAuDocument) {
            return true;
        }

        if (CourrierVentilationDestinataire::query()
            ->where('document_id', $this->id)
            ->where('user_id', $user->id)
            ->exists()) {
            return true;
        }

        if ($this->visibleViaCourrierAgentConfie($user)) {
            return true;
        }

        if ($this->visibleViaCourrierActeurCircuit($user)) {
            return true;
        }

        return $this->visibleViaCourrierDepartPourDirecteur($user);
    }

    /**
     * Pièce jointe à un courrier dont le DG a confié le traitement à `$user`.
     */
    public function visibleViaCourrierAgentConfie(User $user): bool
    {
        return Courrier::query()
            ->where('agent_confie_id', $user->id)
            ->whereHas('documents', fn ($q) => $q->where('documents.id', $this->id))
            ->exists();
    }

    /**
     * Pièce jointe à un courrier dont `$user` est l’acteur de l’étape circuit en cours.
     */
    public function visibleViaCourrierActeurCircuit(User $user): bool
    {
        $courriers = Courrier::query()
            ->whereNotNull('circuit_etape_actuelle_id')
            ->whereHas('documents', fn ($q) => $q->where('documents.id', $this->id))
            ->with('circuitEtapeActuelle')
            ->get();

        if ($courriers->isEmpty()) {
            return false;
        }

        $moteur = app(CircuitCourrierMoteurService::class);

        foreach ($courriers as $courrier) {
            $etape = $courrier->circuitEtapeActuelle;
            if ($etape && $moteur->userCorrespondActeur($user, $etape, $courrier)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pièce jointe à un courrier départ transmis au directeur pour validation.
     */
    public function visibleViaCourrierDepartPourDirecteur(User $user): bool
    {
        if (! $user->peutSignerCourrierDepart()) {
            return false;
        }

        return Courrier::query()
            ->where('directeur_en_attente_id', $user->id)
            ->whereHas('sensCourrier', fn ($q) => $q->where('code', SensCourrier::DEPART))
            ->whereHas('statutCourrier', fn ($q) => $q->where('code', 'transmis_directeur'))
            ->whereHas('documents', fn ($q) => $q->where('documents.id', $this->id))
            ->exists();
    }

    /**
     * IDs des documents joints à un courrier confié à `$user` par le DG
     * (voir {@see self::visibleViaCourrierAgentConfie()}).
     */
    protected static function idsPourAgentConfie(User $user): Collection
    {
        return self::query()
            ->whereHas('courriers', fn ($cq) => $cq->where('agent_confie_id', $user->id))
            ->pluck('id');
    }

    /**
     * IDs des documents joints à un courrier dont `$user` est l’acteur de l’étape en cours.
     */
    protected static function idsPourActeurCircuitCourant(User $user): Collection
    {
        $moteur = app(CircuitCourrierMoteurService::class);

        $ids = Courrier::query()
            ->whereNotNull('circuit_etape_actuelle_id')
            ->with('circuitEtapeActuelle')
            ->get()
            ->filter(function (Courrier $courrier) use ($user, $moteur) {
                $etape = $courrier->circuitEtapeActuelle;

                return $etape && $moteur->userCorrespondActeur($user, $etape, $courrier);
            })
            ->pluck('id');

        if ($ids->isEmpty()) {
            return collect();
        }

        return self::query()
            ->whereHas('courriers', fn ($cq) => $cq->whereIn('courriers.id', $ids))
            ->pluck('id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::url($this->chemin);
    }

    public function getTailleFormateeAttribute(): string
    {
        $octets = $this->taille_octets;
        if ($octets >= 1048576) {
            return number_format($octets / 1048576, 2, ',', ' ').' Mo';
        }
        if ($octets >= 1024) {
            return number_format($octets / 1024, 2, ',', ' ').' Ko';
        }

        return $octets.' o';
    }
}
