<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowEtape extends Model
{
    protected $fillable = [
        'nom',
        'code',
        'ordre',
        'type_document_id',
        'projet_dossier_id',
        'structure_scope_id',
        'role_requis',
        'fonction_requise_id',
        'validateur_id',
        'workflow_etape_suivante_id',
        'est_derniere_etape',
        'validation_hierarchique',
        'destinataire_libre',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'est_derniere_etape' => 'boolean',
            'validation_hierarchique' => 'boolean',
            'destinataire_libre' => 'boolean',
            'actif' => 'boolean',
        ];
    }

    public function typeDocument(): BelongsTo
    {
        return $this->belongsTo(TypeDocument::class);
    }

    public function projetDossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class, 'projet_dossier_id');
    }

    public function structureScope(): BelongsTo
    {
        return $this->belongsTo(Structure::class, 'structure_scope_id');
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validateur_id');
    }

    public function fonctionRequise(): BelongsTo
    {
        return $this->belongsTo(Fonction::class, 'fonction_requise_id');
    }

    public function etapeSuivante(): BelongsTo
    {
        return $this->belongsTo(WorkflowEtape::class, 'workflow_etape_suivante_id');
    }

    public function documentValidations(): HasMany
    {
        return $this->hasMany(DocumentValidation::class);
    }

    public function peutValider(User $user, ?Document $document = null): bool
    {
        if ($this->destinataire_libre && $document) {
            return (int) $document->workflow_validateur_id === (int) $user->id;
        }
        if ($this->validation_hierarchique && $document) {
            // Mode hiérarchique : par défaut on attend l'utilisateur stocké dans le document.
            // Optionnel : si un rôle est configuré (role_requis), on autorise aussi tout utilisateur
            // disposant de ce rôle (ex. chef adjoint autorisé à valider à la place du chef de projet).
            if ((int) $document->workflow_validateur_id === (int) $user->id) {
                return true;
            }

            return $this->role_requis ? $user->hasRole($this->role_requis) : false;
        }
        if ($this->validateur_id) {
            return (int) $this->validateur_id === (int) $user->id;
        }
        if ($this->fonction_requise_id) {
            return $user->structures()
                ->wherePivot('fonction_id', $this->fonction_requise_id)
                ->wherePivotNull('date_fin')
                ->exists();
        }
        if ($this->role_requis) {
            if (! $user->hasRole($this->role_requis)) {
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * Première étape du workflow selon la priorité :
     * service/structure > type de document > global.
     */
    public static function premiereEtapePour(?int $typeDocumentId, ?int $dossierId = null): ?self
    {
        $structureScopeId = static::resoudreStructureScopeIdDepuisDossier($dossierId);
        if ($structureScopeId) {
            $etapeService = static::query()
                ->where('actif', true)
                ->where('structure_scope_id', $structureScopeId)
                ->orderBy('ordre')
                ->first();
            if ($etapeService) {
                return $etapeService;
            }
        }

        if ($typeDocumentId) {
            $etape = static::query()
                ->where('actif', true)
                ->whereNull('projet_dossier_id')
                ->whereNull('structure_scope_id')
                ->where('type_document_id', $typeDocumentId)
                ->orderBy('ordre')
                ->first();
            if ($etape) {
                return $etape;
            }
        }

        return static::query()
            ->where('actif', true)
            ->whereNull('projet_dossier_id')
            ->whereNull('structure_scope_id')
            ->whereNull('type_document_id')
            ->orderBy('ordre')
            ->first();
    }

    /**
     * Contexte d’affichage pour l’envoi en validation : priorité service, puis type, puis global.
     *
     * @return array{source: 'service'|'type'|'global'|null, premiere_etape: ?self, premiere_validation_hierarchique: bool, premiere_destinataire_libre: bool, etapes_libelles: list<string>, type_libelle: ?string, service_nom: ?string}
     */
    public static function contexteEnvoiPourType(?int $typeDocumentId, ?string $typeLibelle = null, ?int $dossierId = null): array
    {
        $premiere = static::premiereEtapePour($typeDocumentId, $dossierId);
        $source = null;
        if ($premiere) {
            if ($premiere->structure_scope_id !== null) {
                $source = 'service';
            } else {
                $source = $premiere->type_document_id !== null ? 'type' : 'global';
            }
        }

        return [
            'source' => $source,
            'premiere_etape' => $premiere,
            'premiere_validation_hierarchique' => (bool) ($premiere?->validation_hierarchique),
            'premiere_destinataire_libre' => (bool) ($premiere?->destinataire_libre),
            'etapes_libelles' => $premiere ? static::chaineLibellesDepuisPremiere($premiere) : [],
            'type_libelle' => $typeLibelle,
            'service_nom' => $premiere?->structureScope?->nom,
        ];
    }

    private static function resoudreStructureScopeIdDepuisDossier(?int $dossierId): ?int
    {
        if (! $dossierId) {
            return null;
        }

        $current = Dossier::query()
            ->select(['id', 'parent_id', 'structure_id'])
            ->find($dossierId);

        while ($current) {
            if ($current->structure_id) {
                return (int) $current->structure_id;
            }

            $current = $current->parent_id
                ? Dossier::query()->select(['id', 'parent_id', 'structure_id'])->find($current->parent_id)
                : null;
        }

        return null;
    }

    /**
     * Libellés des étapes en suivant workflow_etape_suivante_id (à partir de la première étape du circuit résolu).
     *
     * @return list<string>
     */
    public static function chaineLibellesDepuisPremiere(self $premiere): array
    {
        $out = [];
        $seen = [];
        $e = $premiere;
        while ($e && ! isset($seen[$e->id])) {
            $seen[$e->id] = true;
            $out[] = $e->nom;
            if ($e->workflow_etape_suivante_id) {
                $e = static::query()->find($e->workflow_etape_suivante_id);
            } else {
                break;
            }
        }

        return $out;
    }
}
