<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Structure extends Model
{
    protected $fillable = [
        'parent_id',
        'nom',
        'code',
        'type',
        'adresse',
        'telephone',
        'email',
        'actif',
        'responsable_id',
        'fonction_id',
        'role_technique',
    ];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Structure::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Structure::class, 'parent_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function fonction(): BelongsTo
    {
        return $this->belongsTo(Fonction::class, 'fonction_id');
    }

    /**
     * Titulaire actuel de la validation pour cette structure : affectation (user_structure)
     * dont la fonction correspond à celle exigée par la structure, affectation active,
     * et filtre optionnel sur le rôle applicatif (Spatie).
     *
     * Retombe sur responsable_id (ancien modèle) si aucune fonction n’est configurée.
     */
    public function titulaireValidationActuel(): ?User
    {
        if ($this->fonction_id) {
            $users = User::query()
                ->whereHas('structures', function ($q) {
                    $q->where('structures.id', $this->id)
                        ->where('user_structure.fonction_id', $this->fonction_id)
                        ->whereNull('user_structure.date_fin');
                })
                ->orderBy('users.id')
                ->get();

            if ($this->role_technique) {
                $users = $users->filter(fn (User $u) => $u->hasRole($this->role_technique));
            }

            $titulaire = $users->first();
            if ($titulaire) {
                return $titulaire;
            }

            // Fallback manuel : utile quand le pivot n'est pas encore aligné.
            if ($this->responsable_id) {
                return $this->responsable;
            }

            return null;
        }

        if ($this->responsable_id) {
            return $this->responsable;
        }

        return null;
    }

    /** IDs de cette structure + toutes les structures descendantes (récursif). */
    public function idsAvecDescendants(): array
    {
        $ids = [$this->id];
        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->idsAvecDescendants());
        }

        return $ids;
    }

    /** Structure DG (Direction Générale) - racine de l'arborescence. */
    public static function dg(): ?self
    {
        return static::where('code', 'DG')->first();
    }

    /** Secrétariats de direction ACSI (destinataires courrier départ interne). */
    public static function secretariatsDirections(): Builder
    {
        return static::query()
            ->where('actif', true)
            ->where(function ($q) {
                $q->where('type', 'secretariat')
                    ->orWhere('code', 'like', 'SEC-%')
                    ->orWhereRaw("UPPER(nom) LIKE '%SECRET%'");
            })
            ->orderBy('nom');
    }

    /**
     * Directions et antennes départementales pouvant être « service demandeur »
     * (facture, MAD, etc.).
     */
    public static function servicesDemandeurs(): Builder
    {
        return static::query()
            ->where('actif', true)
            ->whereIn('type', ['direction', 'antenne'])
            ->orderBy('nom');
    }

    /**
     * Directions destinataires d’une orientation DG (hors antennes).
     */
    public static function directionsOrientation(): Builder
    {
        return static::query()
            ->where('actif', true)
            ->where('type', 'direction')
            ->orderBy('nom');
    }

    public function estSecretariatDirection(): bool
    {
        if ($this->type === 'secretariat') {
            return true;
        }
        if (str_starts_with((string) $this->code, 'SEC-')) {
            return true;
        }

        return str_contains(mb_strtoupper((string) $this->nom, 'UTF-8'), 'SECRET');
    }

    public function estDirection(): bool
    {
        return in_array($this->type, ['direction', 'antenne'], true);
    }

    /**
     * Direction de rattachement pour le circuit courrier départ (validation directeur).
     * Secrétariat → direction parente ; direction → elle-même ; service → remonte l’arborescence.
     */
    public function directionGestionCourrier(): ?self
    {
        if ($this->estDirection()) {
            return $this;
        }

        if ($this->estSecretariatDirection()) {
            $parent = $this->parent;

            return $parent?->estDirection() ? $parent : $parent?->directionGestionCourrier();
        }

        $courant = $this->parent;
        while ($courant) {
            if ($courant->estDirection()) {
                return $courant;
            }
            $courant = $courant->parent;
        }

        return null;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_structure')
            ->withPivot('role', 'fonction_id', 'date_affectation', 'date_fin')
            ->withTimestamps();
    }
}
