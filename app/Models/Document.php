<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function validations(): \Illuminate\Database\Eloquent\Relations\HasMany
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

    public function versions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VersionDocument::class)->orderByDesc('numero');
    }

    public function versionActuelle(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(VersionDocument::class)->where('est_actuel', true);
    }

    public function metadonnees(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MetadonneeDocument::class)->orderBy('ordre_affichage');
    }

    public function historiques(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HistoriqueDocument::class)->orderByDesc('created_at');
    }

    public function scopeHorsCorbeille($query)
    {
        return $query->where('en_corbeille', false);
    }

    /**
     * Documents visibles : aligné sur la visibilité du dossier (voir {@see Dossier::scopeVisibleBy}).
     */
    public function scopeVisibleBy($query, \App\Models\User $user)
    {
        if ($user->aAccesTotal()) {
            return $query;
        }

        $peutVoirConfidentiel = $user->can('dossiers.view-confidentiel');
        $idsArbrePerso = \App\Models\Dossier::idsPourArbrePersonnel((int) $user->id);

        return $query
            ->where(function ($q) use ($user, $idsArbrePerso) {
                $q->where('proprietaire_id', $user->id)
                    ->orWhere('createur_id', $user->id)
                    ->orWhere('user_id', $user->id)
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
            ->where(function ($q) use ($user, $peutVoirConfidentiel) {
                if ($peutVoirConfidentiel) {
                    $q->whereRaw('1 = 1');
                } else {
                    $q->where(function ($sub) use ($user) {
                        $sub->where('confidentiel', false)
                            ->orWhere('proprietaire_id', $user->id)
                            ->orWhere('createur_id', $user->id)
                            ->orWhere('user_id', $user->id);
                    });
                }
            });
    }

    /** Vérifie si l'utilisateur peut voir ce document (dossier visible + confidentialité). */
    public function visiblePar(\App\Models\User $user): bool
    {
        if ($user->aAccesTotal()) {
            return true;
        }
        $lieAuDocument = $this->proprietaire_id === $user->id
            || $this->createur_id === $user->id
            || $this->user_id === $user->id;
        if ($lieAuDocument) {
            return true;
        }
        $autoriseViaDossier = false;
        if ($this->dossier_id) {
            $dossier = $this->relationLoaded('dossier') ? $this->dossier : $this->dossier()->first();
            if ($dossier) {
                $autoriseViaDossier =
                    (int) $dossier->proprietaire_id === (int) $user->id
                    || (int) $dossier->createur_id === (int) $user->id
                    || in_array((int) $dossier->id, \App\Models\Dossier::idsPourArbrePersonnel((int) $user->id), true)
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

    public function getUrlAttribute(): string
    {
        return Storage::url($this->chemin);
    }

    public function getTailleFormateeAttribute(): string
    {
        $octets = $this->taille_octets;
        if ($octets >= 1048576) {
            return number_format($octets / 1048576, 2, ',', ' ') . ' Mo';
        }
        if ($octets >= 1024) {
            return number_format($octets / 1024, 2, ',', ' ') . ' Ko';
        }
        return $octets . ' o';
    }
}
