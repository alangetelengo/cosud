<?php

namespace Database\Seeders;

use App\Models\Structure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StructureSeeder extends Seeder
{
    /**
     * Anciens codes de structure renommés/fusionnés dans la nouvelle hiérarchie.
     * Les dépendances (utilisateurs, dossiers, courriers…) sont réaffectées vers le nouveau
     * code avant suppression de l'ancien, afin d'éviter toute perte de données lors d'un
     * re-seed sur une base déjà utilisée.
     *
     * @var array<string, string>
     */
    private const CODES_RENOMMES = [
        'SVC-FIN' => 'SVC-DAF-FIN',
    ];

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
            ['DAF', 'DIRECTION ADMINISTRATIVE ET FINANCIERE', 'direction', 'DG'],
            ['DAC', 'DIRECTION DE L\'AGENCE COMPTABLE', 'direction', 'DG'],

            // Services (noms alignés sur les libService du document)
            ['SVC-DDI-DEVINT', 'SCE SYSTEME D\'INFO & EXPL PROD', 'service', 'DDSAIT'],
            ['SVC-DDI-BDD', 'SCE FORMATION & BASE DE DONNEE', 'service', 'DDSAIT'],
            ['SVC-DDI-MAINT', 'SCE MAINT & RESEAU', 'service', 'DDSAIT'],
            ['SVC-DDI-VEILLE', 'SERVICE DE LA VEILLE ET DE L\'INNOVATION', 'service', 'DDSAIT'],

            // Services sous DAF (art. 38 — direction administrative et financière)
            ['SVC-DAF-RH', 'SERVICE DES RESSOURCES HUMAINES', 'service', 'DAF'],
            ['SVC-DAF-APPRO', 'SERVICE DES APPROVISIONNEMENTS ET DU PATRIMOINE', 'service', 'DAF'],
            ['SVC-DAF-BUDGET', 'SERVICE DU BUDGET', 'service', 'DAF'],
            ['SVC-DAF-FIN', 'SERVICE DES FINANCES', 'service', 'DAF'],
            ['SVC-DAF-DOC', 'SERVICE DE LA DOCUMENTATION ET DE L\'ARCHIVAGE', 'service', 'DAF'],

            // Secrétariats de direction
            ['SEC-DIR', 'SECRÉTARIAT DE LA DIRECTION GÉNÉRALE', 'secretariat', 'DG'],
            ['SEC-DING-SI', 'SECRÉTARIAT DIR. ING. SI', 'secretariat', 'DING-SI'],
            ['SEC-DDSAIT', 'SECRÉTARIAT DIR. DDSAIT', 'secretariat', 'DDSAIT'],
            ['SEC-DINFRA', 'SECRÉTARIAT DIR. INFRA.', 'secretariat', 'DINFRA'],
            ['SEC-DSUPPORT', 'SECRÉTARIAT DIR. SUPPORT', 'secretariat', 'DSUPPORT'],
            ['SEC-DCOM', 'SECRÉTARIAT DIR. COMMUNICATION', 'secretariat', 'DCOM'],
            ['SEC-DAF', 'SECRÉTARIAT DIR. DAF', 'secretariat', 'DAF'],
            ['SEC-DAC', 'SECRÉTARIAT DIR. DAC', 'secretariat', 'DAC'],
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

        $this->migrerCodesRenommes($created);
    }

    /**
     * Réaffecte les dépendances des anciens codes vers leur remplaçant puis supprime
     * l'ancienne structure. Ne touche jamais aux structures absentes de {@see self::CODES_RENOMMES}
     * (par exemple celles créées par d'autres seeders ou ajoutées manuellement).
     *
     * @param  array<string, int>  $created
     */
    private function migrerCodesRenommes(array $created): void
    {
        // Tables dépendantes de structures.id à réaffecter avant suppression de l'ancien code.
        $colonnesParTable = [
            'structures' => ['parent_id'],
            'users' => ['structure_id'],
            'user_structure' => ['structure_id'],
            'dossiers' => ['structure_id'],
            'courriers' => ['structure_id', 'structure_expediteur_id', 'structure_destinataire_id', 'reponse_structure_destinataire_id'],
            'courrier_orientations' => ['structure_id'],
            'courrier_transmissions' => ['de_structure_id', 'vers_structure_id'],
            'courrier_ventilation_destinataires' => ['structure_id'],
            'workflow_etapes' => ['structure_scope_id'],
        ];

        foreach (self::CODES_RENOMMES as $ancienCode => $nouveauCode) {
            $ancienne = Structure::where('code', $ancienCode)->first();
            if (! $ancienne) {
                continue;
            }

            $nouvelId = $created[$nouveauCode] ?? Structure::where('code', $nouveauCode)->value('id');
            if (! $nouvelId) {
                continue;
            }

            foreach ($colonnesParTable as $table => $colonnes) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                foreach ($colonnes as $colonne) {
                    if (! Schema::hasColumn($table, $colonne)) {
                        continue;
                    }
                    DB::table($table)->where($colonne, $ancienne->id)->update([$colonne => $nouvelId]);
                }
            }

            $ancienne->delete();
        }
    }
}
