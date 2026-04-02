<?php

namespace App\Console\Commands;

use App\Models\Dossier;
use Illuminate\Console\Command;

class NormaliserRacinesOrgCommand extends Command
{
    protected $signature = 'dossiers:normaliser-racines-org';

    protected $description = 'Met parent_id à null pour tous les dossiers déjà marqués est_racine_org (cohérence base)';

    public function handle(): int
    {
        $n = Dossier::query()
            ->where('est_racine_org', true)
            ->whereNotNull('parent_id')
            ->update(['parent_id' => null]);

        $this->info($n.' dossier(s) mis à jour.');

        return self::SUCCESS;
    }
}
