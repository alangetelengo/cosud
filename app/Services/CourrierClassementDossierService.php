<?php

namespace App\Services;

use App\Models\Courrier;
use App\Models\Dossier;
use App\Models\DossierPartage;
use App\Models\FournisseurPrestataire;
use App\Models\JournalAudit;
use App\Models\SuiviPaiement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class CourrierClassementDossierService
{
    private const LIMITE_SELECTEUR = 400;

    /** Rôles de la direction / circuit qui doivent pouvoir réutiliser le dossier classé. */
    private const ROLES_PARTAGE_DIRECTION = [
        'secretaire_direction',
        'particulier_dg',
        'particulier_ac',
        'responsable_dossiers_prestataires',
        'responsable_suivi_depenses',
        'agent_comptable',
        'caissier',
    ];

    public const COMMENTAIRE_PARTAGE_AUTO = 'cosud:auto-classement-direction';

    public function __construct(
        private readonly MesDossiersRacineService $mesDossiersRacine,
    ) {}

    /**
     * Dossiers dans lesquels l’utilisateur peut classer (dépôt / écriture).
     *
     * @return Collection<int, Dossier>
     */
    public function dossiersCiblesPour(User $user, ?string $suggestionNom = null): Collection
    {
        $needle = $suggestionNom !== null ? mb_strtolower(trim($suggestionNom)) : '';

        $query = $this->requeteDossiersEcriturePotentielle($user)
            ->with(['parent']);

        if ($needle !== '') {
            $query->orderByRaw(
                'CASE WHEN LOWER(nom) = ? THEN 0 WHEN LOWER(nom) LIKE ? THEN 1 ELSE 2 END',
                [$needle, '%'.$needle.'%']
            );
        }

        $dossiers = $query
            ->orderBy('nom')
            ->limit(self::LIMITE_SELECTEUR * 3)
            ->get()
            ->filter(fn (Dossier $d) => $this->peutClasserDans($user, $d))
            ->take(self::LIMITE_SELECTEUR)
            ->values();

        if ($needle !== '') {
            $dossiers = $dossiers->sortByDesc(function (Dossier $d) use ($needle) {
                $nom = mb_strtolower((string) $d->nom);

                return $nom === $needle ? 2 : (str_contains($nom, $needle) || str_contains($needle, $nom) ? 1 : 0);
            })->values();
        }

        return $dossiers;
    }

    public function suggererDossier(User $user, Courrier $courrier): ?Dossier
    {
        $courrier->loadMissing('fournisseurPrestataire.dossier');

        $ficheDossier = $courrier->fournisseurPrestataire?->dossier;
        if ($ficheDossier && $ficheDossier->actif && $this->peutClasserDans($user, $ficheDossier)) {
            return $ficheDossier;
        }

        $libelle = trim((string) ($courrier->expediteur_libelle ?? ''));
        if ($libelle === '') {
            return null;
        }

        $exact = $this->requeteDossiersEcriturePotentielle($user)
            ->whereRaw('LOWER(nom) = ?', [mb_strtolower($libelle)])
            ->orderBy('nom')
            ->limit(30)
            ->get()
            ->first(fn (Dossier $d) => $this->peutClasserDans($user, $d));

        if ($exact) {
            return $exact;
        }

        return $this->dossiersCiblesPour($user, $libelle)->first(function (Dossier $d) use ($libelle) {
            return strcasecmp((string) $d->nom, $libelle) === 0
                || str_contains(mb_strtolower((string) $d->nom), mb_strtolower($libelle));
        });
    }

    /**
     * @param  array{mode: string, dossier_id?: int|null, nom_dossier?: string|null, parent_id?: int|null}  $data
     */
    public function classer(Courrier $courrier, User $user, array $data): Dossier
    {
        $mode = $data['mode'] ?? 'existant';

        return DB::transaction(function () use ($courrier, $user, $data, $mode) {
            $dossier = $mode === 'nouveau'
                ? $this->creerDossierClassement($user, $data, $courrier)
                : $this->resoudreDossierExistant($user, $data);

            $avant = $courrier->dossier_id;

            $courrier->update(['dossier_id' => $dossier->id]);

            $courrier->documents()->each(function ($document) use ($dossier) {
                $document->update(['dossier_id' => $dossier->id]);
            });

            SuiviPaiement::query()
                ->where('courrier_id', $courrier->id)
                ->update(['dossier_id' => $dossier->id]);

            $this->synchroniserFicheFournisseur($courrier, $dossier);
            $this->partagerAvecDirection($dossier, $user);

            JournalAudit::log('courrier.classer_dossier', 'courriers', [
                'commentaire' => json_encode([
                    'courrier_id' => $courrier->id,
                    'dossier_id' => $dossier->id,
                    'dossier_avant_id' => $avant,
                    'mode' => $mode,
                    'type' => $courrier->typeCourrier?->code,
                ]),
            ]);

            return $dossier->fresh();
        });
    }

    public function peutClasserDans(User $user, Dossier $dossier): bool
    {
        if (! $dossier->actif || ! $dossier->visiblePar($user)) {
            return false;
        }

        return $dossier->peuxDeposer($user) || $dossier->utilisateurADroitEcritureContenu($user);
    }

    /**
     * Accorde lecture + écriture aux rôles direction / circuit (réutilisation du même dossier).
     */
    public function partagerAvecDirection(Dossier $dossier, User $acteur): void
    {
        $userIds = collect();
        foreach (self::ROLES_PARTAGE_DIRECTION as $roleName) {
            if (! Role::query()->where('name', $roleName)->where('guard_name', 'web')->exists()) {
                continue;
            }
            $userIds = $userIds->merge(
                User::role($roleName)
                    ->where('actif', true)
                    ->pluck('id')
            );
        }

        $userIds = $userIds
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn (int $id) => $id === (int) $acteur->id || $id === (int) $dossier->proprietaire_id)
            ->values();

        foreach ($userIds as $userId) {
            $existant = DossierPartage::query()
                ->where('dossier_id', $dossier->id)
                ->where('user_id', $userId)
                ->first();

            if ($existant) {
                $existant->update([
                    'droits_lecture' => true,
                    'droits_ecriture' => true,
                    'date_expiration' => null,
                    'commentaire' => $existant->commentaire ?: self::COMMENTAIRE_PARTAGE_AUTO,
                ]);

                continue;
            }

            DossierPartage::create([
                'dossier_id' => $dossier->id,
                'user_id' => $userId,
                'partage_par_id' => $acteur->id,
                'droits_lecture' => true,
                'droits_ecriture' => true,
                'droits_suppression' => false,
                'propager_aux_sous_dossiers' => false,
                'date_expiration' => null,
                'commentaire' => self::COMMENTAIRE_PARTAGE_AUTO,
            ]);
        }

        Log::channel('cosud')->info('Partage direction auto après classement courrier', [
            'dossier_id' => $dossier->id,
            'acteur_id' => $acteur->id,
            'beneficiaires' => $userIds->all(),
        ]);
    }

    /**
     * @return Builder<Dossier>
     */
    private function requeteDossiersEcriturePotentielle(User $user): Builder
    {
        $idsPerso = Dossier::idsPourArbrePersonnel($user->id);

        return Dossier::query()
            ->where('actif', true)
            ->where(function ($q) use ($user, $idsPerso) {
                $q->where('proprietaire_id', $user->id)
                    ->orWhere('createur_id', $user->id)
                    ->orWhereHas('partages', function ($pq) use ($user) {
                        $pq->where('user_id', $user->id)
                            ->where('droits_ecriture', true)
                            ->where(function ($exp) {
                                $exp->whereNull('date_expiration')->orWhere('date_expiration', '>', now());
                            });
                    });
                if ($idsPerso !== []) {
                    $q->orWhereIn('id', $idsPerso);
                }
            });
    }

    /**
     * @param  array{dossier_id?: int|null}  $data
     */
    private function resoudreDossierExistant(User $user, array $data): Dossier
    {
        $dossierId = (int) ($data['dossier_id'] ?? 0);
        $dossier = Dossier::query()->where('actif', true)->find($dossierId);

        if (! $dossier || ! $this->peutClasserDans($user, $dossier)) {
            throw ValidationException::withMessages([
                'dossier_id' => $dossier && $dossier->visiblePar($user)
                    ? 'Vous n’avez pas le droit d’écrire dans ce dossier.'
                    : 'Dossier introuvable ou non accessible.',
            ]);
        }

        return $dossier;
    }

    /**
     * @param  array{nom_dossier?: string|null, parent_id?: int|null}  $data
     */
    private function creerDossierClassement(User $user, array $data, Courrier $courrier): Dossier
    {
        $nom = trim((string) ($data['nom_dossier'] ?? ''));
        if ($nom === '') {
            $nom = trim((string) ($courrier->expediteur_libelle ?? ''));
        }
        if ($nom === '') {
            throw ValidationException::withMessages([
                'nom_dossier' => 'Indiquez le nom du dossier.',
            ]);
        }

        $parentId = isset($data['parent_id']) && $data['parent_id'] !== null && $data['parent_id'] !== ''
            ? (int) $data['parent_id']
            : null;

        if ($parentId === null) {
            $parent = $this->mesDossiersRacine->createDefaultRacinePourCommande($user);
        } else {
            $parent = Dossier::query()->where('actif', true)->find($parentId);
            if (! $parent || ! $parent->visiblePar($user)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Dossier parent introuvable ou non accessible.',
                ]);
            }
            if (! $parent->peuxDeposer($user) && ! $parent->utilisateurADroitEcritureContenu($user)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Vous ne pouvez pas créer un sous-dossier ici.',
                ]);
            }
        }

        $existant = Dossier::query()
            ->where('parent_id', $parent->id)
            ->where('actif', true)
            ->whereRaw('LOWER(nom) = ?', [mb_strtolower($nom)])
            ->first();

        if ($existant) {
            if (! $this->peutClasserDans($user, $existant)) {
                throw ValidationException::withMessages([
                    'nom_dossier' => 'Un dossier portant ce nom existe déjà, mais vous n’avez pas le droit d’y classer.',
                ]);
            }

            return $existant;
        }

        $estFacture = $courrier->typeCourrier?->code === 'facture';
        $ordre = (int) (Dossier::query()->where('parent_id', $parent->id)->max('ordre') ?? -1) + 1;

        return Dossier::create([
            'parent_id' => $parent->id,
            'nom' => $nom,
            'description' => $estFacture
                ? 'Dossier fournisseur / prestataire (classement factures).'
                : 'Dossier de classement courrier — partagé direction.',
            'actif' => true,
            'ordre' => $ordre,
            'structure_id' => $parent->structure_id ?? $user->structure_id,
            'createur_id' => $user->id,
            'proprietaire_id' => $user->id,
            'confidentiel' => false,
            'notify_sms' => false,
        ]);
    }

    private function synchroniserFicheFournisseur(Courrier $courrier, Dossier $dossier): void
    {
        if ($courrier->typeCourrier?->code !== 'facture') {
            return;
        }

        $courrier->loadMissing('fournisseurPrestataire');
        $fiche = $courrier->fournisseurPrestataire;
        if (! $fiche instanceof FournisseurPrestataire) {
            return;
        }

        if ((int) $fiche->dossier_id === (int) $dossier->id) {
            return;
        }

        $fiche->update(['dossier_id' => $dossier->id]);
    }
}
