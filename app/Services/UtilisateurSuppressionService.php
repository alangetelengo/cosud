<?php

namespace App\Services;

use App\Models\Courrier;
use App\Models\Moratoire;
use App\Models\SuiviPaiement;
use App\Models\User;
use InvalidArgumentException;

/**
 * Suppression d’utilisateur sans détruire les données métier liées (courriers, etc.).
 */
class UtilisateurSuppressionService
{
    /**
     * @return list<string>
     */
    public function raisonsBlocage(User $user): array
    {
        $raisons = [];

        $nbCourriers = Courrier::query()->where('createur_id', $user->id)->count();
        if ($nbCourriers > 0) {
            $raisons[] = $nbCourriers.' courrier(s) créé(s) par ce compte';
        }

        $nbSuivis = SuiviPaiement::query()->where('etabli_par_id', $user->id)->count();
        if ($nbSuivis > 0) {
            $raisons[] = $nbSuivis.' ligne(s) de suivi de paiement établie(s)';
        }

        $nbMoratoires = Moratoire::query()->where('created_by', $user->id)->count();
        if ($nbMoratoires > 0) {
            $raisons[] = $nbMoratoires.' moratoire(s) créé(s)';
        }

        return $raisons;
    }

    public function peutSupprimer(User $user): bool
    {
        return $this->raisonsBlocage($user) === [];
    }

    public function assertPeutSupprimer(User $user): void
    {
        $raisons = $this->raisonsBlocage($user);
        if ($raisons !== []) {
            throw new InvalidArgumentException(
                'Impossible de supprimer ce compte : '.implode(' ; ', $raisons)
                .'. Désactivez le compte (case « Compte actif ») pour conserver l’historique COSUD.'
            );
        }
    }

    public function supprimer(User $user): void
    {
        $this->assertPeutSupprimer($user);
        $user->delete();
    }
}
