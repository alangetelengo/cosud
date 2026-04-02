<?php

namespace Database\Seeders;

use App\Models\Fonction;
use App\Models\Structure;
use Illuminate\Database\Seeder;

class FonctionSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'code' => 'directeur_general',
                'libelle' => 'Directeur général',
                'description' => 'Tête de l’organisme ; validation ultime dans la chaîne hiérarchique.',
            ],
            [
                'code' => 'directeur_direction',
                'libelle' => 'Directeur de direction',
                'description' => 'Pilotage d’une direction opérationnelle ou équivalent.',
            ],
            [
                'code' => 'chef_centre',
                'libelle' => 'Chef de centre',
                'description' => 'Responsable d’un centre (étape intermédiaire du workflow ACSI).',
            ],
            [
                'code' => 'chef_service',
                'libelle' => 'Chef de service',
                'description' => 'Responsable d’un service ou d’une cellule rattachée à une direction.',
            ],
            [
                'code' => 'chef_projet',
                'libelle' => 'Chef de projet',
                'description' => 'Pilotage d’un projet transversal ou d’un lot fonctionnel.',
            ],
            [
                'code' => 'chef_pool',
                'libelle' => 'Chef de pool',
                'description' => 'Responsable d’un pool de ressources (compétences, production, support).',
            ],
        ];

        $byCode = [];
        foreach ($rows as $row) {
            $f = Fonction::updateOrCreate(
                ['code' => $row['code']],
                array_merge($row, ['actif' => true])
            );
            $byCode[$row['code']] = $f;
        }

        $dg = $byCode['directeur_general']->id;
        $dd = $byCode['directeur_direction']->id;
        $cc = $byCode['chef_centre']->id;
        $cs = $byCode['chef_service']->id;

        Structure::where('code', 'DG')->update([
            'fonction_id' => $dg,
            'role_technique' => 'dg',
        ]);

        $directions = ['DING-SI', 'DDSAIT', 'DINFRA', 'DSUPPORT', 'DCOM', 'DAF', 'ANT'];
        Structure::whereIn('code', $directions)->update([
            'fonction_id' => $dd,
            'role_technique' => null,
        ]);

        Structure::whereIn('code', ['SVC-FIN', 'SEC-DIR', 'SJUR', 'CCG', 'SVC-DDI-DEVINT', 'SVC-DDI-BDD', 'SVC-DDI-MAINT', 'SVC-DDI-VEILLE'])->update([
            'fonction_id' => $cs,
            'role_technique' => null,
        ]);
    }
}
