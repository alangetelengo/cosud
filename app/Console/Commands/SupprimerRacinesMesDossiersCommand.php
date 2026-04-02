<?php

namespace App\Console\Commands;

use App\Models\Dossier;
use Illuminate\Console\Command;

class SupprimerRacinesMesDossiersCommand extends Command
{
    protected $signature = 'dossiers:supprimer-racines-mes-dossiers {--force : Exécuter sans demander de confirmation}';

    protected $description = 'Supprime toutes les racines « Mes dossiers » et leurs sous-dossiers (documents détachés : dossier_id mis à null)';

    public function handle(): int
    {
        $racines = Dossier::query()->whereNotNull('racine_utilisateur_id')->get();
        if ($racines->isEmpty()) {
            $this->info('Aucune racine « Mes dossiers » à supprimer.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm($racines->count().' racine(s) et tout leur contenu seront supprimés. Continuer ?', false)) {
            $this->warn('Annulé.');

            return self::FAILURE;
        }

        $deleted = 0;
        foreach ($racines as $racine) {
            $this->deleteSubtree($racine);
            $deleted++;
        }

        $this->info($deleted.' racine(s) supprimée(s).');

        return self::SUCCESS;
    }

    private function deleteSubtree(Dossier $dossier): void
    {
        $children = Dossier::query()->where('parent_id', $dossier->id)->get();
        foreach ($children as $child) {
            $this->deleteSubtree($child);
        }
        $dossier->delete();
    }
}
