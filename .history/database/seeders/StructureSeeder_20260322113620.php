<?php

namespace Database\Seeders;

use App\Models\Structure;
use Illuminate\Database\Seeder;

class StructureSeeder extends Seeder
{
    public function run(): void
    {
        $created = [];

        // Niveau 1: DG
        // Niveau 2: 6 directions opérationnelles + 3 entités centrales + antennes (selon Statuts ACSI)
        $structures = [
            ['DG', 'Direction Générale', 'direction', null],

            // 6 directions opérationnelles
            ['DING-SI', 'Direction de l\'ingénierie des systèmes d\'information', 'direction', 'DG'],
            ['DDSAIT', 'Direction du développement des systèmes applicatifs et de l\'innovation', 'direction', 'DG'],
            ['DINFRA', 'Direction des infrastructures et de la sécurité des systèmes d\'information', 'direction', 'DG'],
            ['DSUPPORT', 'Direction du support technique et de la formation', 'direction', 'DG'],
            ['DCOM', 'Direction de la communication et de la conduite du changement', 'direction', 'DG'],
            ['DAF', 'Direction administrative et financière', 'direction', 'DG'],

            // Services sous DAF (exemple : chaîne à 3 visas)
            ['SVC-FIN', 'Service des finances', 'service', 'DAF'],

            // 3 entités centrales
            ['SEC-DIR', 'Secrétariat de direction', 'service', 'DG'],
            ['SJUR', 'Service juridique et du contentieux', 'service', 'DG'],
            ['CCG', 'Cellule de contrôle de gestion', 'service', 'DG'],

            // Antennes départementales
            ['ANT', 'Antennes départementales', 'antenne', 'DG'],
        ];

        foreach ($structures as $s) {
            $parentId = isset($s[3], $created[$s[3]]) ? $created[$s[3]] : null;
            $struct = Structure::updateOrCreate(
                ['code' => $s[0]],
                [
                    'parent_id' => $parentId,
                    'nom' => $s[1],
                    'type' => $s[2],
                    'actif' => true,
                ]
            );
            $created[$s[0]] = $struct->id;
        }

        // Supprimer les anciennes structures non présentes dans la nouvelle hiérarchie
        $codes = array_column($structures, 0);
        Structure::whereNotIn('code', $codes)->delete();
    }
}
