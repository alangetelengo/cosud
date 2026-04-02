<?php

namespace App\Console\Commands;

use App\Models\Dossier;
use App\Models\User;
use App\Services\MesDossiersRacineService;
use Illuminate\Console\Command;

class EnsureRacinesMesDossiersCommand extends Command
{
    protected $signature = 'dossiers:ensure-racines-mes-dossiers';

    protected $description = 'Crée la racine « Mes dossiers » (nom par défaut) pour chaque utilisateur qui ne l’a pas — utile en migration ; en production les comptes la créent via le formulaire.';

    public function handle(MesDossiersRacineService $service): int
    {
        $count = 0;
        foreach (User::query()->cursor() as $user) {
            $before = Dossier::where('racine_utilisateur_id', $user->id)->exists();
            $service->createDefaultRacinePourCommande($user);
            if (! $before) {
                $count++;
            }
        }
        $this->info($count.' racine(s) « Mes dossiers » créée(s).');

        return self::SUCCESS;
    }
}
