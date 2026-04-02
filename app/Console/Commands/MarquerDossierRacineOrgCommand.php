<?php

namespace App\Console\Commands;

use App\Models\Dossier;
use Illuminate\Console\Command;

class MarquerDossierRacineOrgCommand extends Command
{
    protected $signature = 'dossiers:marquer-racine-org {dossier : ID du dossier à promouvoir en racine de plan (même niveau que les autres racines de la direction)}';

    protected $description = 'Marque un dossier comme racine organisationnelle et détache du parent (parent_id → null)';

    public function handle(): int
    {
        $id = (int) $this->argument('dossier');
        $dossier = Dossier::query()->find($id);
        if (! $dossier) {
            $this->error("Dossier #{$id} introuvable.");

            return self::FAILURE;
        }
        if ($dossier->racine_utilisateur_id !== null) {
            $this->error('Les dossiers « Mes dossiers » ne peuvent pas être marqués comme racine org.');

            return self::FAILURE;
        }
        $dossier->est_racine_org = true;
        $dossier->save();
        $this->info("Dossier « {$dossier->nom} » (#{$dossier->id}) est maintenant une racine de plan (parent_id = null).");

        return self::SUCCESS;
    }
}
