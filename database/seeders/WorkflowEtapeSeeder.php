<?php

namespace Database\Seeders;

use App\Models\WorkflowEtape;
use Illuminate\Database\Seeder;

/**
 * Étape de workflow minimale pour une base neuve. Des circuits multi-étapes se créent
 * depuis Paramètres → Workflow → Créer un circuit.
 */
class WorkflowEtapeSeeder extends Seeder
{
    public function run(): void
    {
        WorkflowEtape::updateOrCreate(
            ['code' => 'validation_responsable'],
            [
                'nom' => 'Validation par le responsable hiérarchique',
                'ordre' => 1,
                'type_document_id' => null,
                'role_requis' => null,
                'validateur_id' => null,
                'workflow_etape_suivante_id' => null,
                'est_derniere_etape' => true,
                'validation_hierarchique' => true,
                'actif' => true,
            ]
        );
    }
}
