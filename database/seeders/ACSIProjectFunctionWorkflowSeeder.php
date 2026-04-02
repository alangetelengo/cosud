<?php

namespace Database\Seeders;

use App\Models\WorkflowEtape;
use Illuminate\Database\Seeder;

class ACSIProjectFunctionWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        // Nouveau principe retenu : pas de circuit service/projet injecté au seed.
        // On désactive les circuits historiques "acsi_service_*".
        WorkflowEtape::query()
            ->where('code', 'like', 'acsi_service_%')
            ->update([
                'actif' => false,
                'workflow_etape_suivante_id' => null,
                'est_derniere_etape' => true,
            ]);
    }
}

