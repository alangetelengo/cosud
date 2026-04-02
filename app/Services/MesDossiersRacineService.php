<?php

namespace App\Services;

use App\Models\Dossier;
use App\Models\User;

class MesDossiersRacineService
{
    /** Racine personnelle existante (une par utilisateur), ou null si l’utilisateur ne l’a pas encore créée. */
    public function find(User $user): ?Dossier
    {
        return Dossier::where('racine_utilisateur_id', $user->id)->first();
    }

    /**
     * Création explicite de la racine « espace personnel » (parent_id null) par l’utilisateur.
     * Un seul dossier avec racine_utilisateur_id par utilisateur (contrainte DB).
     */
    public function createPersonnelRoot(User $user, string $nom, ?string $description, ?int $typeDossierId, ?string $typeString, bool $confidentiel): Dossier
    {
        if ($this->find($user) !== null) {
            throw new \RuntimeException('Cet utilisateur a déjà une racine personnelle.');
        }

        $code = $this->genererCodeUniqueRacine($user);

        return Dossier::create([
            'parent_id' => null,
            'type_dossier_id' => $typeDossierId,
            'nom' => $nom,
            'code' => $code,
            'type' => $typeString,
            'description' => $description,
            'confidentiel' => $confidentiel,
            'notify_sms' => false,
            'actif' => true,
            'ordre' => 9990,
            'structure_id' => $user->structure_id,
            'createur_id' => $user->id,
            'proprietaire_id' => $user->id,
            'racine_utilisateur_id' => $user->id,
        ]);
    }

    /**
     * Crée une racine intitulée « Mes dossiers » si absente.
     * Utilisé par la commande artisan, les listeners d’auth (inscription / connexion), ou la migration.
     */
    public function createDefaultRacinePourCommande(User $user): Dossier
    {
        $existing = $this->find($user);
        if ($existing) {
            return $existing;
        }

        return $this->createPersonnelRoot(
            $user,
            'Mes dossiers',
            'Espace personnel — sous-dossiers créés par l’utilisateur.',
            null,
            null,
            false
        );
    }

    private function genererCodeUniqueRacine(User $user): string
    {
        $code = 'MES-'.$user->id;
        $base = $code;
        $i = 0;
        while (Dossier::where('code', $code)->exists()) {
            $i++;
            $code = $base.'-'.$i;
        }

        return $code;
    }
}
