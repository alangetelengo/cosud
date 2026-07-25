<?php

namespace App\Console\Commands;

use App\Services\CourrierRetardService;
use Illuminate\Console\Command;

class AlerterCourriersEnRetardCommand extends Command
{
    protected $signature = 'courriers:alerter-retards';

    protected $description = 'Alerte le DG sur les courriers dont l’étape de circuit dépasse le délai configuré';

    public function handle(CourrierRetardService $retardService): int
    {
        $nb = $retardService->alerterRetards();
        $this->info("Alertes retard envoyées : {$nb} (seuil {$retardService->delaiHeures()} h).");

        return self::SUCCESS;
    }
}
