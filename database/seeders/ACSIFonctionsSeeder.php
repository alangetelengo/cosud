<?php

namespace Database\Seeders;

use App\Models\Fonction;
use App\Models\Structure;
use Illuminate\Database\Seeder;

class ACSIFonctionsSeeder extends Seeder
{
    public function run(): void
    {
        // Fonctions utiles à la validation hiérarchique (structure.fonction_id)
        // + fonctions “réelles” visibles dans le document ACSI (libFonction).
        $rows = [
            ['code' => 'directeur_general', 'libelle' => 'DIRECTEUR GENERAL'],
            ['code' => 'directeur_direction', 'libelle' => 'DIRECTEUR (DIRECTION)'],
            ['code' => 'chef_service', 'libelle' => 'CHEF SERVICE'],
            ['code' => 'chef_section', 'libelle' => 'CHEF DE SECTION'],
            ['code' => 'chef_projet', 'libelle' => 'CHEF DE PROJET'],
            ['code' => 'chef_projet_adjoint', 'libelle' => 'CHEF DE PROJET ADJOINT'],
            ['code' => 'chef_application', 'libelle' => "CHEF D'APPLICATION"],
            ['code' => 'directeur_central', 'libelle' => 'DIRECTEUR CENTRAL'],
            ['code' => 'directeur_departemental', 'libelle' => 'DIRECTEUR DEPARTEMENTAL'],
        ];

        $byCode = [];
        foreach ($rows as $row) {
            $f = Fonction::updateOrCreate(
                ['code' => $row['code']],
                [
                    'libelle' => $row['libelle'],
                    'description' => null,
                    'actif' => true,
                ]
            );
            $byCode[$row['code']] = $f;
        }

        // Application ACSI : titularité hiérarchique simplifiée
        // DG -> directeur_general ; directions -> directeur_direction ; services -> chef_service.
        $dgId = $byCode['directeur_general']->id;
        $dirId = $byCode['directeur_direction']->id;
        $svcId = $byCode['chef_service']->id;

        Structure::where('code', 'DG')->update(['fonction_id' => $dgId, 'role_technique' => 'dg']);

        Structure::where('code', '!=', 'DG')
            ->where('type', 'direction')
            ->update(['fonction_id' => $dirId, 'role_technique' => null]);

        Structure::where('type', 'service')
            ->update(['fonction_id' => $svcId, 'role_technique' => null]);
    }
}

