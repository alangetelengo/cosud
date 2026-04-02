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
        // Niveau 2: directions + services (noms alignés sur le document ACSI)
        $structures = [
            ['DG', 'DIRECTION GENERALE', 'direction', null],

            // Directions (correspondent aux libDirection du document)
            ['DING-SI', 'DIRECTION DE L\'INGENIERIE DES SYSTEMES D\'INFORM.', 'direction', 'DG'],
            ['DDSAIT', 'DIRECTION DEV. DES SYS. APPL. ET INNOV. TECH.', 'direction', 'DG'],
            ['DINFRA', 'DIRECTION INFRA. ET DE LA SECU. DES SYS. D\'INF.', 'direction', 'DG'],
            ['DSUPPORT', 'DIRECTION DU SUPPORT TECH. ET DE LA FORM.', 'direction', 'DG'],
            ['DCOM', 'DIRECTION DE LA COMM. ET DE LA COND. DU CHANG.', 'direction', 'DG'],
            ['DAF', 'DIRECTION FINANCIERE ET COMPTABLE', 'direction', 'DG'],

            // Services (noms alignés sur les libService du document)
            ['SVC-DDI-DEVINT', 'SCE SYSTEME D\'INFO & EXPL PROD', 'service', 'DDSAIT'],
            ['SVC-DDI-BDD', 'SCE FORMATION & BASE DE DONNEE', 'service', 'DDSAIT'],
            ['SVC-DDI-MAINT', 'SCE MAINT & RESEAU', 'service', 'DDSAIT'],
            ['SVC-DDI-VEILLE', 'SERVICE DE LA VEILLE ET DE L\'INNOVATION', 'service', 'DDSAIT'],

            // Services sous DAF
            ['SVC-FIN', 'SCE COMPTABILITES', 'service', 'DAF'],

            // Entités centrales
            ['SEC-DIR', 'SECRET. PART. DG.', 'service', 'DG'],
            ['SJUR', 'DTION ADM. & PERS.', 'service', 'DG'],
            ['CCG', 'CTLE DE GESTION', 'service', 'DG'],

            // Antennes départementales
            ['ANT', 'DIRECTION DEPARTEMENTALE DE POINTE NOIRE', 'antenne', 'DG'],
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
