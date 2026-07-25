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

    /**
     * Sous-dossier dédié au parapheur départ sous « Mes dossiers » (créé si absent).
     * Assure aussi la racine personnelle si elle n’existe pas encore.
     */
    public function ensureSousDossierParapheurDepart(User $user): Dossier
    {
        $racine = $this->createDefaultRacinePourCommande($user);
        $nom = (string) config('ged.parapheur_depart.dossier_nom', 'Courriers départ');

        $existant = Dossier::query()
            ->where('parent_id', $racine->id)
            ->where('nom', $nom)
            ->where('actif', true)
            ->first();

        if ($existant) {
            return $existant;
        }

        $ordre = (int) (Dossier::where('parent_id', $racine->id)->max('ordre') ?? -1) + 1;

        return Dossier::create([
            'parent_id' => $racine->id,
            'nom' => $nom,
            'code' => $this->genererCodeUniqueSousDossier($user, 'PARAPH-DEP'),
            'description' => 'Pièces de rédaction pour les courriers départ (parapheur).',
            'confidentiel' => false,
            'notify_sms' => false,
            'actif' => true,
            'ordre' => $ordre,
            'structure_id' => $user->structure_id,
            'createur_id' => $user->id,
            'proprietaire_id' => $user->id,
        ]);
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

    private function genererCodeUniqueSousDossier(User $user, string $prefix): string
    {
        $code = $prefix.'-'.$user->id;
        $base = $code;
        $i = 0;
        while (Dossier::where('code', $code)->exists()) {
            $i++;
            $code = $base.'-'.$i;
        }

        return $code;
    }
}
