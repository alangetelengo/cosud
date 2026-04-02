<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TypeDossierSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'administratif', 'libelle' => 'Administratif', 'couleur_defaut' => '#3B82F6'],
            ['code' => 'finance', 'libelle' => 'Finance', 'couleur_defaut' => '#10B981'],
            ['code' => 'projet', 'libelle' => 'Projet', 'couleur_defaut' => '#8B5CF6'],
            ['code' => 'ingenierie_si', 'libelle' => 'Ingénierie SI', 'couleur_defaut' => '#6366F1'],
            ['code' => 'support', 'libelle' => 'Support et formation', 'couleur_defaut' => '#14B8A6'],
            ['code' => 'client', 'libelle' => 'Client', 'couleur_defaut' => '#F59E0B'],
            ['code' => 'operation', 'libelle' => 'Opérationnel', 'couleur_defaut' => '#06B6D4'],
            ['code' => 'archive', 'libelle' => 'Archive', 'couleur_defaut' => '#6B7280'],
            ['code' => 'confidentiel', 'libelle' => 'Confidentiel', 'couleur_defaut' => '#EF4444'],
        ];

        $now = now();
        foreach ($types as $type) {
            $row = array_merge($type, ['actif' => true, 'updated_at' => $now]);
            if (! DB::table('type_dossiers')->where('code', $type['code'])->exists()) {
                $row['created_at'] = $now;
            }
            DB::table('type_dossiers')->updateOrInsert(['code' => $type['code']], $row);
        }
    }
}
