<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_professionnel',
        'telephone',
        'password',
        'structure_id',
        'actif',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google2fa_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
            'google2fa_enabled' => 'boolean',
            'recovery_codes' => 'array',
            'two_factor_verified_at' => 'datetime',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return (bool) ($this->google2fa_enabled ?? false);
    }

    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }

        return $codes;
    }

    public function getRecoveryCodes(): array
    {
        return $this->recovery_codes ?? [];
    }

    public function useRecoveryCode(string $code): bool
    {
        $codes = $this->recovery_codes ?? [];
        $index = array_search($code, $codes);
        if ($index === false) {
            return false;
        }
        array_splice($codes, $index, 1);
        $this->recovery_codes = $codes;
        $this->save();

        return true;
    }

    public function disableTwoFactor(): void
    {
        $this->forceFill([
            'google2fa_secret' => null,
            'google2fa_enabled' => false,
            'recovery_codes' => null,
            'two_factor_verified_at' => null,
        ])->save();
    }

    public function enableTwoFactor(string $secret, array $recoveryCodes): void
    {
        $this->forceFill([
            'google2fa_secret' => encrypt($secret),
            'google2fa_enabled' => true,
            'recovery_codes' => $recoveryCodes,
            'two_factor_verified_at' => null,
        ])->save();
    }

    public function getTwoFactorSecret(): ?string
    {
        if (empty($this->google2fa_secret)) {
            return null;
        }
        try {
            return decrypt($this->google2fa_secret);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function verifyTwoFactorCode(string $code): bool
    {
        $secret = $this->getTwoFactorSecret();
        if (! $secret) {
            return false;
        }
        $google2fa = new Google2FA;

        return $google2fa->verifyKey($secret, $code);
    }

    public function markTwoFactorVerified(): void
    {
        $this->forceFill(['two_factor_verified_at' => now()])->save();
    }

    public function routeNotificationForVonage(): ?string
    {
        return $this->telephone;
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function structures(): BelongsToMany
    {
        return $this->belongsToMany(Structure::class, 'user_structure')
            ->withPivot('role', 'fonction_id', 'date_affectation', 'date_fin')
            ->withTimestamps();
    }

    /**
     * Structure utilisée pour calculer la chaîne de validation hiérarchique des documents.
     * Priorité : `users.structure_id` ; sinon première affectation ouverte sur le pivot (ordre stable par nom).
     */
    public function structurePourValidationHierarchique(): ?Structure
    {
        if ($this->structure_id) {
            $s = $this->structure()->first();
            if ($s) {
                return $s;
            }
        }

        return $this->structures()
            ->wherePivotNull('date_fin')
            ->orderBy('structures.nom')
            ->first();
    }

    /** Admin ou DG : accès total. */
    public function aAccesTotal(): bool
    {
        return $this->hasRole('admin') || $this->hasRole('dg');
    }

    /** IDs des structures dont l'utilisateur est responsable (fonction métier alignée ou ancien responsable_id). */
    public function structureIdsGerees(): array
    {
        $ids = [];

        foreach (Structure::where('responsable_id', $this->id)->get() as $s) {
            $ids = array_merge($ids, $s->idsAvecDescendants());
        }

        $structureIds = DB::table('user_structure')
            ->join('structures', 'structures.id', '=', 'user_structure.structure_id')
            ->where('user_structure.user_id', $this->id)
            ->whereNull('user_structure.date_fin')
            ->whereNotNull('structures.fonction_id')
            ->whereColumn('user_structure.fonction_id', 'structures.fonction_id')
            ->pluck('structures.id');

        foreach ($structureIds as $sid) {
            $s = Structure::find($sid);
            if ($s) {
                $ids = array_merge($ids, $s->idsAvecDescendants());
            }
        }

        return array_values(array_unique($ids));
    }

    /** Vérifie si l'utilisateur peut voir le contenu d'un autre user (créateur) via la hiérarchie. */
    public function voitStructureDe(?int $createurStructureId): bool
    {
        if (! $createurStructureId) {
            return false;
        }
        $ids = $this->structureIdsGerees();

        return in_array($createurStructureId, $ids, true);
    }

    /**
     * Structures pour étendre la visibilité (collègues + périmètre géré) lorsque la permission
     * documents.view-hierarchique est accordée.
     *
     * @return list<int>
     */
    public function structureIdsVueHierarchique(): array
    {
        // Inclut aussi les ancêtres : un responsable de service doit pouvoir voir les racines
        // du plan assignées à sa direction parente.
        $baseIds = array_values(array_filter(array_unique(array_merge(
            $this->structure_id ? [(int) $this->structure_id] : [],
            $this->structureIdsGerees()
        ))));

        $ids = [];
        foreach ($baseIds as $id) {
            $currentId = (int) $id;
            while ($currentId > 0) {
                $ids[] = $currentId;
                $parentId = Structure::find($currentId)?->parent_id;
                $currentId = $parentId ? (int) $parentId : 0;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Périmètre structurel pour le plan de classement (affectation + structures gérées / titulaire).
     *
     * @return list<int>
     */
    public function structureIdsPérimètrePlanClassement(): array
    {
        return $this->structureIdsVueHierarchique();
    }

    /**
     * Voir les branches organisationnelles du plan sans partage explicite sur chaque dossier :
     * permission Spatie `documents.view-hierarchique` (y compris via le rôle directeur) ou
     * acteur avec au moins une structure gérée (titulaire / responsable).
     */
    public function aVisibiliteElargiePlanOrganisation(): bool
    {
        return $this->can('documents.view-hierarchique') || ! empty($this->structureIdsGerees());
    }

    public function dossierFavoris(): BelongsToMany
    {
        return $this->belongsToMany(Dossier::class, 'dossier_favoris')
            ->withTimestamps();
    }

    /** Racine de l’espace personnel (`racine_utilisateur_id`), créée par l’utilisateur via le formulaire « Nouveau dossier ». */
    public function dossierRacineMesDossiers(): HasOne
    {
        return $this->hasOne(Dossier::class, 'racine_utilisateur_id');
    }

    /**
     * Peut gérer les partages sur un dossier de direction (plan organisationnel) :
     * permission `dossiers.share-direction` + titulaire de validation de la structure du dépôt,
     * ou affectation « Directeur » sur cette structure si aucun titulaire n’est résolu.
     */
    public function peutPartagerDossierDirection(Dossier $dossier): bool
    {
        if (! $this->can('dossiers.share-direction')) {
            return false;
        }
        $sid = $dossier->structure_id_depot;
        if (! $sid) {
            return false;
        }
        $structure = Structure::find($sid);
        if (! $structure) {
            return false;
        }
        $titulaire = $structure->titulaireValidationActuel();
        if ($titulaire) {
            return (int) $titulaire->id === (int) $this->id;
        }

        return $this->structures()
            ->where('structures.id', $sid)
            ->wherePivotNull('date_fin')
            ->where('user_structure.role', 'like', '%Directeur%')
            ->exists();
    }
}
