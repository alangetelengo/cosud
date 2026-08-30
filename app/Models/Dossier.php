<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Dossier extends Model
{
    protected $fillable = [
        'parent_id',
        'type_dossier_id',
        'nom',
        'code',
        'type',
        'description',
        'confidentiel',
        'notify_sms',
        'actif',
        'ordre',
        'createur_id',
        'proprietaire_id',
        'structure_id',
        /** Dossier racine du plan pour une direction / service (même niveau que les autres racines seedées). */
        'est_racine_org',
        'racine_utilisateur_id',
        'niveau_confidentialite',
        'capacite_max_documents',
        'taille_max_octets',
        'couleur',
        'icone',
        'archivage_automatique',
        'duree_conservation_annees',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'confidentiel' => 'boolean',
            'notify_sms' => 'boolean',
            'actif' => 'boolean',
            'archivage_automatique' => 'boolean',
            'est_racine_org' => 'boolean',
        ];
    }

    public function typeDossier(): BelongsTo
    {
        return $this->belongsTo(TypeDossier::class);
    }

    public function isImportant(): bool
    {
        return $this->confidentiel || $this->notify_sms;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Dossier::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Dossier::class, 'parent_id')->where('actif', true)->orderBy('ordre')->orderBy('nom');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createur_id');
    }

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proprietaire_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    /** Utilisateur dont ce dossier est la racine « Mes dossiers » (null pour les dossiers normaux). */
    public function utilisateurRacine(): BelongsTo
    {
        return $this->belongsTo(User::class, 'racine_utilisateur_id');
    }

    /** Structure du dossier pour le dépôt (celle du dossier ou du premier ancêtre). */
    public function getStructureIdDepotAttribute(): ?int
    {
        if ($this->structure_id) {
            return $this->structure_id;
        }
        $current = $this->parent;
        while ($current) {
            if ($current->structure_id) {
                return $current->structure_id;
            }
            $current = $current->parent;
        }

        return null;
    }

    /** Dossier sous « Mes dossiers » (racine ou descendant d’une racine `racine_utilisateur_id`). */
    public function estDansArbrePersonnelMesDossiers(): bool
    {
        $current = $this;
        while ($current) {
            if ($current->racine_utilisateur_id !== null) {
                return true;
            }
            $current = $current->parent;
        }

        return false;
    }

    /**
     * Dossier du plan organisationnel rattaché à une direction (pas un service / antenne).
     * Le partage est réservé au titulaire de validation de cette structure + permission `dossiers.share-direction`.
     */
    public function estSoumisReglePartageTitulaireDirection(): bool
    {
        if ($this->estDansArbrePersonnelMesDossiers()) {
            return false;
        }
        $sid = $this->structure_id_depot;
        if (! $sid) {
            return false;
        }
        $structure = Structure::find($sid);
        if (! $structure) {
            return false;
        }

        return $structure->type === 'direction';
    }

    /** @var array<int, list<int>> */
    protected static array $cacheIdsArbrePersonnel = [];

    /** @var array<int, list<int>> */
    protected static array $cacheIdsAutresPerso = [];

    /**
     * IDs de tous les dossiers de l’arbre « Mes dossiers » d’un utilisateur (racine + descendants).
     *
     * @return list<int>
     */
    public static function idsPourArbrePersonnel(int $userId): array
    {
        if (array_key_exists($userId, self::$cacheIdsArbrePersonnel)) {
            return self::$cacheIdsArbrePersonnel[$userId];
        }
        $ids = [];
        $queue = static::query()->where('racine_utilisateur_id', $userId)->pluck('id')->all();
        while ($queue !== []) {
            $id = (int) array_shift($queue);
            $ids[] = $id;
            foreach (static::query()->where('parent_id', $id)->pluck('id') as $cid) {
                $queue[] = (int) $cid;
            }
        }
        self::$cacheIdsArbrePersonnel[$userId] = array_values(array_unique($ids));

        return self::$cacheIdsArbrePersonnel[$userId];
    }

    /**
     * Sous-dossiers de l’arbre « Mes dossiers » (hors racine) : dossiers fournisseurs / prestataires.
     *
     * @return list<int>
     */
    public static function idsDossiersFournisseursPrestatairesPour(int $userId): array
    {
        unset(self::$cacheIdsArbrePersonnel[$userId]);

        $idsArbre = self::idsPourArbrePersonnel($userId);
        if ($idsArbre === []) {
            return [];
        }

        return self::query()
            ->whereIn('id', $idsArbre)
            ->whereNull('racine_utilisateur_id')
            ->where('actif', true)
            ->orderBy('nom')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * IDs des dossiers situés dans l’arbre personnel d’un autre utilisateur (à exclure du voir « par structure »).
     *
     * @return list<int>
     */
    public static function idsPourArbresPersonnelsAutresQue(int $userId): array
    {
        if (array_key_exists($userId, self::$cacheIdsAutresPerso)) {
            return self::$cacheIdsAutresPerso[$userId];
        }
        $ids = [];
        $ownerIds = static::query()
            ->whereNotNull('racine_utilisateur_id')
            ->where('racine_utilisateur_id', '!=', $userId)
            ->distinct()
            ->pluck('racine_utilisateur_id');
        foreach ($ownerIds as $oid) {
            $ids = array_merge($ids, static::idsPourArbrePersonnel((int) $oid));
        }
        self::$cacheIdsAutresPerso[$userId] = array_values(array_unique($ids));

        return self::$cacheIdsAutresPerso[$userId];
    }

    protected static function booted(): void
    {
        static::saving(static function (Dossier $dossier) {
            if ($dossier->est_racine_org) {
                $dossier->parent_id = null;
            }
        });
        static::saved(static function () {
            self::$cacheIdsArbrePersonnel = [];
            self::$cacheIdsAutresPerso = [];
        });
        static::deleted(static function () {
            self::$cacheIdsArbrePersonnel = [];
            self::$cacheIdsAutresPerso = [];
        });
    }

    public function partages(): HasMany
    {
        return $this->hasMany(DossierPartage::class);
    }

    /**
     * Peut ajouter du contenu (documents, sous-dossiers) : accès total, propriétaire, créateur,
     * ou partage avec droits d’écriture non expiré.
     */
    public function utilisateurADroitEcritureContenu(User $user): bool
    {
        if ($user->aAccesTotal()) {
            return true;
        }
        if ((int) $this->proprietaire_id === (int) $user->id || (int) $this->createur_id === (int) $user->id) {
            return true;
        }

        return $this->partages()
            ->where('user_id', $user->id)
            ->where('droits_ecriture', true)
            ->where(fn ($q) => $q->whereNull('date_expiration')->orWhere('date_expiration', '>', now()))
            ->exists();
    }

    /** Vérifie si l'utilisateur peut déposer un document dans ce dossier. */
    public function peuxDeposer(User $user): bool
    {
        if ($user->aAccesTotal()) {
            return true;
        }
        if ($this->proprietaire_id === $user->id || $this->createur_id === $user->id) {
            return true;
        }
        if ($this->partages()
            ->where('user_id', $user->id)
            ->where('droits_ecriture', true)
            ->where(fn ($q) => $q->whereNull('date_expiration')->orWhere('date_expiration', '>', now()))
            ->exists()) {
            return true;
        }
        if (in_array((int) $this->id, static::idsPourArbresPersonnelsAutresQue($user->id), true)) {
            return false;
        }
        $structureIdDepot = $this->structure_id ?? $this->structure_id_depot;
        if (! $structureIdDepot) {
            return false;
        }
        $allowedIds = array_filter(array_unique(array_merge(
            [$user->structure_id],
            $user->structureIdsGerees()
        )));
        if (empty($allowedIds) || ! in_array($structureIdDepot, $allowedIds, true)) {
            return false;
        }
        $createurIsAdmin = $this->createur && $this->createur->aAccesTotal();
        $proprioIsAdmin = $this->proprietaire && $this->proprietaire->aAccesTotal();

        return $createurIsAdmin || $proprioIsAdmin;
    }

    /** Dossier lié à l’utilisateur : propriétaire, créateur, partage, ou sous-arbre « Mes dossiers ». */
    public function estLieA(User $user): bool
    {
        if ($this->proprietaire_id === $user->id || $this->createur_id === $user->id) {
            return true;
        }
        if (in_array((int) $this->id, static::idsPourArbrePersonnel($user->id), true)) {
            return true;
        }

        return $this->partages()
            ->where('user_id', $user->id)
            ->where('droits_lecture', true)
            ->where(fn ($q) => $q->whereNull('date_expiration')->orWhere('date_expiration', '>', now()))
            ->exists();
    }

    /** Dernière date d'activité (documents). */
    public function getDerniereActiviteAttribute(): ?Carbon
    {
        return $this->documents()->max('created_at');
    }

    public function getCheminCompletAttribute(): string
    {
        $parents = [];
        $current = $this;
        while ($current) {
            array_unshift($parents, $current->nom);
            $current = $current->parent;
        }

        return implode(' / ', $parents);
    }

    /** Ancêtres du dossier (de la racine au parent direct), pour le fil d'Ariane. */
    public function cheminAncetres(): Collection
    {
        $ancetres = collect();
        $current = $this->parent;
        while ($current) {
            $ancetres->prepend($current);
            $current = $current->parent;
        }

        return $ancetres;
    }

    /** Scope : dossiers dans lesquels l'utilisateur peut déposer (restriction par direction + partages). */
    public function scopeDepositableBy($query, User $user)
    {
        if ($user->aAccesTotal()) {
            return $query;
        }
        $allowedStructureIds = array_filter(array_unique(array_merge(
            [$user->structure_id],
            $user->structureIdsGerees()
        )));

        return $query->where(function ($q) use ($user, $allowedStructureIds) {
            $q->where('proprietaire_id', $user->id)
                ->orWhere('createur_id', $user->id)
                ->orWhereHas('partages', function ($pq) use ($user) {
                    $pq->where('user_id', $user->id)
                        ->where('droits_ecriture', true)
                        ->where(fn ($exp) => $exp->whereNull('date_expiration')->orWhere('date_expiration', '>', now()));
                });
            if (! empty($allowedStructureIds)) {
                $q->orWhere(function ($sub) use ($allowedStructureIds) {
                    $sub->whereIn('structure_id', $allowedStructureIds)
                        ->where(function ($org) {
                            $org->whereHas('createur', fn ($c) => $c->whereHas('roles', fn ($r) => $r->whereIn('name', ['admin', 'dg'])))
                                ->orWhereHas('proprietaire', fn ($p) => $p->whereHas('roles', fn ($r) => $r->whereIn('name', ['admin', 'dg'])));
                        });
                });
            }
        });
    }

    /**
     * Scope : dossiers visibles selon le rôle et les partages (aligné sur {@see visiblePar}).
     */
    public function scopeVisibleBy($query, User $user)
    {
        if ($user->aAccesTotal()) {
            return $query;
        }

        $idsPerso = static::idsPourArbrePersonnel($user->id);
        $idsStructure = $user->structureIdsPérimètrePlanClassement();
        $élargi = $user->aVisibiliteElargiePlanOrganisation();
        $idsAutresPerso = static::idsPourArbresPersonnelsAutresQue($user->id);

        return $query->where(function ($q) use ($user, $idsPerso, $idsStructure, $élargi, $idsAutresPerso) {
            $q->where('proprietaire_id', $user->id)
                ->orWhere('createur_id', $user->id)
                ->orWhereHas('partages', function ($pq) use ($user) {
                    $pq->where('user_id', $user->id)
                        ->where('droits_lecture', true)
                        ->where(function ($exp) {
                            $exp->whereNull('date_expiration')->orWhere('date_expiration', '>', now());
                        });
                });
            if ($idsPerso !== []) {
                $q->orWhereIn('id', $idsPerso);
            }
            if ($élargi && $idsStructure !== [] && ! $user->hasRole('responsable_suivi_depenses')) {
                $exclus = array_merge($idsAutresPerso);
                $q->orWhere(function ($sub) use ($idsStructure, $exclus) {
                    $sub->whereIn('structure_id', $idsStructure);
                    if ($exclus !== []) {
                        $sub->whereNotIn('id', $exclus);
                    }
                });
            }
        });
    }

    /**
     * Visibilité plan de classement :
     * — admin / DG : tout ;
     * — propriétaire / créateur, partage lecture ;
     * — arbre « Mes dossiers » du connecté ;
     * — acteurs à périmètre élargi (directeur + titulaires de structures gérées) : dossiers org. dont la structure
     *   est dans leur périmètre, sauf arbres personnels d’autres utilisateurs ;
     * — sinon : pas d’accès au seul fait du rattachement structure / créateur (hors partage).
     */
    public function visiblePar(User $user): bool
    {
        if ($user->aAccesTotal()) {
            return true;
        }
        if ($this->proprietaire_id === $user->id || $this->createur_id === $user->id) {
            return true;
        }
        if ($this->partages()
            ->where('user_id', $user->id)
            ->where('droits_lecture', true)
            ->where(function ($q) {
                $q->whereNull('date_expiration')->orWhere('date_expiration', '>', now());
            })
            ->exists()) {
            return true;
        }
        if (in_array((int) $this->id, static::idsPourArbrePersonnel($user->id), true)) {
            return true;
        }

        // Eleni (suivi dépenses) : uniquement ses dossiers / arbre perso — pas le plan org. prestataires.
        if ($user->hasRole('responsable_suivi_depenses')) {
            return false;
        }

        if ($user->aVisibiliteElargiePlanOrganisation()) {
            $sid = $this->structure_id ?? $this->structure_id_depot;
            if ($sid && in_array((int) $sid, $user->structureIdsPérimètrePlanClassement(), true)) {
                if (! $this->estDansArbrePersonnelMesDossiers()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getNiveauAttribute(): int
    {
        $niveau = 0;
        $current = $this;
        while ($current->parent_id) {
            $niveau++;
            $current = $current->parent;
        }

        return $niveau;
    }

    /** Indique si ce dossier est la racine « Mes dossiers » donnée ou un de ses descendants. */
    public static function estSousRacineMesDossiers(self $noeud, self $racine): bool
    {
        if ((int) $noeud->id === (int) $racine->id) {
            return true;
        }
        $current = $noeud->parent;
        while ($current) {
            if ((int) $current->id === (int) $racine->id) {
                return true;
            }
            $current = $current->parent;
        }

        return false;
    }
}
