<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\MesDossiersRacineService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;

/**
 * Crée la racine « Mes dossiers » dès que l’utilisateur a `dossiers.create`
 * (inscription / connexion), sans passer par le formulaire.
 *
 * Idempotent : si la racine existe déjà, rien n’est fait.
 */
class EnsureMesDossiersRacineExists
{
    public function __construct(
        private MesDossiersRacineService $mesDossiersRacine
    ) {}

    public function handleRegistered(Registered $event): void
    {
        $this->ensure($event->user);
    }

    public function handleLogin(Login $event): void
    {
        $this->ensure($event->user);
    }

    private function ensure(?User $user): void
    {
        if (! $user instanceof User || ! $user->exists) {
            return;
        }
        if (! $user->can('dossiers.create')) {
            return;
        }

        try {
            $this->mesDossiersRacine->createDefaultRacinePourCommande($user);
        } catch (\Throwable $e) {
            Log::channel('eged')->warning('Création auto de la racine Mes dossiers impossible', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
