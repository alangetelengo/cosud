<?php

namespace App\Console\Commands;

use App\Services\CourrierClassementDossierService;
use Illuminate\Console\Command;

class RetirerPartagesAutoAcCaissierCommand extends Command
{
    protected $signature = 'dossiers:retirer-partages-auto-ac-caissier
                            {--dry-run : Lister sans supprimer}
                            {--force : Supprimer sans confirmation}';

    protected $description = 'Retire les partages automatiques (classement courrier) des agents comptables et caissiers sur les dossiers fournisseurs.';

    public function handle(CourrierClassementDossierService $classement): int
    {
        $partages = $classement->listerPartagesAutoAcCaissier();

        if ($partages === []) {
            $this->info('Aucun partage auto AC/caissier à retirer.');

            return self::SUCCESS;
        }

        $this->table(
            ['Partage', 'Dossier', 'Utilisateur'],
            collect($partages)->map(fn (array $ligne): array => [
                $ligne['id'],
                $ligne['dossier_nom'].' (#'.$ligne['dossier_id'].')',
                ($ligne['user_email'] ?? 'user #'.$ligne['user_id']),
            ])->all()
        );

        if ($this->option('dry-run')) {
            $this->warn(count($partages).' partage(s) seraient retirés (dry-run).');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Retirer ces '.count($partages).' partage(s) ?', true)) {
            $this->comment('Opération annulée.');

            return self::SUCCESS;
        }

        $supprimes = $classement->retirerPartagesAutoAcCaissier();
        $this->info($supprimes.' partage(s) retiré(s).');

        return self::SUCCESS;
    }
}
