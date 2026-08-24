<?php

namespace App\Services;

use App\Models\Courrier;
use App\Models\Dossier;
use App\Models\JournalAudit;
use App\Models\SuiviPaiement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourrierClassementDossierService
{
    private const LIMITE_SELECTEUR = 400;

    public function __construct(
        private readonly MesDossiersRacineService $mesDossiersRacine,
    ) {}

    /**
     * Dossiers dans lesquels l’utilisateur peut classer une facture (dépôt / écriture).
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

        // Filtrer l’écriture en PHP, puis limiter : le préfiltre SQL évite de couper
        // trop tôt des dossiers réellement accessibles (ex. Mes dossiers / propriétaire).
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
                ? $this->creerDossierFournisseur($user, $data, $courrier)
                : $this->resoudreDossierExistant($user, $data);

            $avant = $courrier->dossier_id;

            $courrier->update(['dossier_id' => $dossier->id]);

            $courrier->documents()->each(function ($document) use ($dossier) {
                $document->update(['dossier_id' => $dossier->id]);
            });

            SuiviPaiement::query()
                ->where('courrier_id', $courrier->id)
                ->update(['dossier_id' => $dossier->id]);

            JournalAudit::log('courrier.classer_dossier', 'courriers', [
                'commentaire' => json_encode([
                    'courrier_id' => $courrier->id,
                    'dossier_id' => $dossier->id,
                    'dossier_avant_id' => $avant,
                    'mode' => $mode,
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
     * Préfiltre SQL des dossiers où l’utilisateur a typiquement un droit d’écriture
     * (propriétaire, créateur, partage écriture, arbre « Mes dossiers »).
     *
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
    private function creerDossierFournisseur(User $user, array $data, Courrier $courrier): Dossier
    {
        $nom = trim((string) ($data['nom_dossier'] ?? ''));
        if ($nom === '') {
            $nom = trim((string) ($courrier->expediteur_libelle ?? ''));
        }
        if ($nom === '') {
            throw ValidationException::withMessages([
                'nom_dossier' => 'Indiquez le nom du dossier fournisseur.',
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

        $ordre = (int) (Dossier::query()->where('parent_id', $parent->id)->max('ordre') ?? -1) + 1;

        return Dossier::create([
            'parent_id' => $parent->id,
            'nom' => $nom,
            'description' => 'Dossier fournisseur / prestataire (classement factures).',
            'actif' => true,
            'ordre' => $ordre,
            'structure_id' => $parent->structure_id ?? $user->structure_id,
            'createur_id' => $user->id,
            'proprietaire_id' => $user->id,
            'confidentiel' => false,
            'notify_sms' => false,
        ]);
    }
}
