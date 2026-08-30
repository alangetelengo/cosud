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

    /** Rôles SEC-DIR / circuit DG qui réutilisent le dossier classé (hors AC / caissier DAC). */
    private const ROLES_PARTAGE_DIRECTION = [
        'secretaire_direction',
        'particulier_dg',
        'particulier_ac',
        'responsable_dossiers_prestataires',
        'responsable_suivi_depenses',
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
     * Classe une facture dans le dossier du fournisseur (1 fiche référentiel = 1 dossier).
     */
    public function classerFactureFournisseur(Courrier $courrier, User $user): Dossier
    {
        if ($courrier->typeCourrier?->code !== 'facture') {
            throw ValidationException::withMessages([
                'mode' => 'Le classement automatique est réservé aux factures.',
            ]);
        }

        $courrier->loadMissing('fournisseurPrestataire.dossier');

        $fiche = $courrier->fournisseurPrestataire;
        $ficheDossier = $fiche?->dossier;

        if ($fiche?->dossier_id) {
            if (! $ficheDossier || ! $ficheDossier->actif) {
                throw ValidationException::withMessages([
                    'nom_dossier' => 'Le dossier référentiel de cette fiche fournisseur est introuvable ou inactif.',
                ]);
            }

            if (! $this->peutClasserDans($user, $ficheDossier)) {
                throw ValidationException::withMessages([
                    'nom_dossier' => 'Cette fiche est déjà liée au dossier « '.$ficheDossier->nom.' ». Demandez un partage avec droit d’écriture au propriétaire du dossier.',
                ]);
            }

            return $this->classer($courrier, $user, [
                'mode' => 'existant',
                'dossier_id' => $ficheDossier->id,
            ]);
        }

        if ($fiche instanceof FournisseurPrestataire) {
            return $this->classerFactureAvecFicheSansDossier($courrier, $user, $fiche);
        }

        $suggere = $this->suggererDossier($user, $courrier);
        if ($suggere) {
            return $this->classer($courrier, $user, [
                'mode' => 'existant',
                'dossier_id' => $suggere->id,
            ]);
        }

        $nom = trim((string) ($courrier->expediteur_libelle ?? ''));
        if ($nom === '') {
            throw ValidationException::withMessages([
                'nom_dossier' => 'Indiquez le fournisseur ou rattachez une fiche référentiel avant le classement.',
            ]);
        }

        return $this->classer($courrier, $user, [
            'mode' => 'nouveau',
            'nom_dossier' => $nom,
        ]);
    }

    /**
     * Dossier cible pour l’affichage UI (facture) — pas de correspondance partielle si fiche référentiel.
     */
    public function dossierCibleAffichageFacture(Courrier $courrier, User $user): ?Dossier
    {
        if ($courrier->typeCourrier?->code !== 'facture') {
            return null;
        }

        $courrier->loadMissing('fournisseurPrestataire.dossier');

        $ficheDossier = $courrier->fournisseurPrestataire?->dossier;
        if ($ficheDossier?->actif) {
            return $ficheDossier;
        }

        $fiche = $courrier->fournisseurPrestataire;
        if ($fiche instanceof FournisseurPrestataire) {
            $nom = trim($fiche->nom);

            return $nom !== '' ? $this->trouverDossierEcritureParNomExact($user, $nom) : null;
        }

        return $this->suggererDossier($user, $courrier);
    }

    private function classerFactureAvecFicheSansDossier(Courrier $courrier, User $user, FournisseurPrestataire $fiche): Dossier
    {
        $nom = trim($fiche->nom);
        if ($nom === '') {
            throw ValidationException::withMessages([
                'nom_dossier' => 'La fiche référentiel n’a pas de nom exploitable pour créer le dossier.',
            ]);
        }

        $exact = $this->trouverDossierEcritureParNomExact($user, $nom);
        if ($exact) {
            return $this->classer($courrier, $user, [
                'mode' => 'existant',
                'dossier_id' => $exact->id,
            ]);
        }

        return $this->classer($courrier, $user, [
            'mode' => 'nouveau',
            'nom_dossier' => $nom,
        ]);
    }

    private function trouverDossierEcritureParNomExact(User $user, string $nom): ?Dossier
    {
        $nom = trim($nom);
        if ($nom === '') {
            return null;
        }

        return $this->requeteDossiersEcriturePotentielle($user)
            ->whereRaw('LOWER(nom) = ?', [mb_strtolower($nom)])
            ->orderBy('nom')
            ->limit(30)
            ->get()
            ->first(fn (Dossier $d) => $this->peutClasserDans($user, $d));
    }

    public function estFactureClasseeCanoniquement(Courrier $courrier): bool
    {
        if ($courrier->typeCourrier?->code !== 'facture' || ! $courrier->dossier_id) {
            return false;
        }

        $courrier->loadMissing('fournisseurPrestataire');

        $ficheDossierId = $courrier->fournisseurPrestataire?->dossier_id;
        if (! $ficheDossierId) {
            return false;
        }

        return (int) $courrier->dossier_id === (int) $ficheDossierId;
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
     * Retire les partages auto de classement pour l’AC et le caissier (hors périmètre SEC-DIR).
     *
     * @return list<array{id: int, dossier_id: int, user_id: int, user_email: string|null, dossier_nom: string|null}>
     */
    public function listerPartagesAutoAcCaissier(): array
    {
        return DossierPartage::query()
            ->with(['user:id,email', 'dossier:id,nom'])
            ->where('commentaire', self::COMMENTAIRE_PARTAGE_AUTO)
            ->whereHas('user', function ($query): void {
                $query->role(['agent_comptable', 'caissier']);
            })
            ->orderBy('dossier_id')
            ->get()
            ->map(fn (DossierPartage $partage): array => [
                'id' => (int) $partage->id,
                'dossier_id' => (int) $partage->dossier_id,
                'user_id' => (int) $partage->user_id,
                'user_email' => $partage->user?->email,
                'dossier_nom' => $partage->dossier?->nom,
            ])
            ->all();
    }

    public function retirerPartagesAutoAcCaissier(): int
    {
        $ids = collect($this->listerPartagesAutoAcCaissier())->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        $supprimes = DossierPartage::query()->whereIn('id', $ids)->delete();

        Log::channel('cosud')->info('Retrait partages auto AC/caissier après classement courrier', [
            'nb' => $supprimes,
            'partage_ids' => $ids->all(),
        ]);

        return (int) $supprimes;
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

        if ($fiche->dossier_id && (int) $fiche->dossier_id !== (int) $dossier->id) {
            return;
        }

        if ((int) $fiche->dossier_id === (int) $dossier->id) {
            return;
        }

        $fiche->update(['dossier_id' => $dossier->id]);
    }
}
