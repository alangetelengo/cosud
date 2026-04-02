<?php

namespace Database\Seeders;

use App\Models\WorkflowEtape;
use Illuminate\Database\Seeder;

class WorkflowPresetSeeder extends Seeder
{
    public function run(): void
    {
        // Nouveau principe retenu : workflow global hiérarchique unique par défaut.
        // Ce seeder ne crée plus de circuits type/projet et désactive ceux historiquement générés ici.
        $legacySuffixes = ['_chef_projet', '_chef_pool', '_chef_service'];
        foreach ($legacySuffixes as $suffix) {
            WorkflowEtape::query()
                ->where('code', 'like', '%'.$suffix)
                ->update([
                    'actif' => false,
                    'workflow_etape_suivante_id' => null,
                    'est_derniere_etape' => true,
                ]);
        }
    }
}

