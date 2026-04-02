<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatutDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $statuts = [
            ['code' => 'brouillon', 'libelle' => 'Déposé', 'est_initial' => true, 'est_final' => false, 'ordre' => 1],
            ['code' => 'en_attente', 'libelle' => 'En attente', 'est_initial' => false, 'est_final' => false, 'ordre' => 2],
            ['code' => 'valide', 'libelle' => 'Validé', 'est_initial' => false, 'est_final' => false, 'ordre' => 3],
            ['code' => 'rejete', 'libelle' => 'Rejeté', 'est_initial' => false, 'est_final' => false, 'ordre' => 4],
            ['code' => 'archive', 'libelle' => 'Archivé', 'est_initial' => false, 'est_final' => true, 'ordre' => 5],
        ];

        $now = now();
        foreach ($statuts as $statut) {
            $row = array_merge($statut, ['actif' => true, 'updated_at' => $now]);
            if (! DB::table('statut_documents')->where('code', $statut['code'])->exists()) {
                $row['created_at'] = $now;
            }
            DB::table('statut_documents')->updateOrInsert(['code' => $statut['code']], $row);
        }
    }
}
