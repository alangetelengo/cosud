<?php

namespace Database\Seeders;

use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ACSIUsersTestSeeder extends Seeder
{
    public function run(): void
    {
        // Seeder minimal : uniquement quelques comptes pour tester
        // (le reste sera fait via l’interface admin).
        $password = Hash::make('password');

        $agents = [
            [
                'prenom' => 'aline',
                'nom' => 'Test Aline',
                'email' => 'aline@acsi.cg',
                'structure_code' => 'SVC-DAF-FIN',
                'roles' => ['utilisateur'],
                'pivot_role' => 'Agent',
            ],
            [
                'prenom' => 'bruno',
                'nom' => 'Test Bruno',
                'email' => 'bruno@acsi.cg',
                'structure_code' => 'SVC-DDI-DEVINT',
                'roles' => ['chef_projet'],
                'pivot_role' => 'Chef de projet',
            ],
            [
                'prenom' => 'carla',
                'nom' => 'Test Carla',
                'email' => 'carla@acsi.cg',
                'structure_code' => 'SVC-DDI-DEVINT',
                'roles' => ['chef_centre'],
                'pivot_role' => 'Chef de centre',
            ],
            [
                'prenom' => 'daniel',
                'nom' => 'Test Daniel',
                'email' => 'daniel@acsi.cg',
                'structure_code' => 'SVC-DDI-VEILLE',
                'roles' => ['chef_service'],
                'pivot_role' => 'Chef de service',
            ],
            [
                'prenom' => 'elodie',
                'nom' => 'Test Elodie',
                'email' => 'elodie@acsi.cg',
                'structure_code' => 'SVC-DDI-BDD',
                'roles' => ['utilisateur'],
                'pivot_role' => 'Agent',
            ],
            [
                'prenom' => 'fabrice',
                'nom' => 'Test Fabrice',
                'email' => 'fabrice@acsi.cg',
                'structure_code' => 'SVC-DDI-MAINT',
                'roles' => ['chef_projet'],
                'pivot_role' => 'Chef de projet',
            ],
            [
                'prenom' => 'gisele',
                'nom' => 'Test Gisele',
                'email' => 'gisele@acsi.cg',
                'structure_code' => 'SVC-DDI-MAINT',
                'roles' => ['chef_centre'],
                'pivot_role' => 'Chef de centre',
            ],
            [
                'prenom' => 'hugo',
                'nom' => 'Test Hugo',
                'email' => 'hugo@acsi.cg',
                'structure_code' => 'SVC-DAF-FIN',
                'roles' => ['chef_service'],
                'pivot_role' => 'Chef de service',
            ],
            [
                'prenom' => 'irene',
                'nom' => 'Test Irene',
                'email' => 'irene@acsi.cg',
                'structure_code' => 'DAF',
                'roles' => ['utilisateur'],
                'pivot_role' => 'Agent',
            ],
            [
                'prenom' => 'julien',
                'nom' => 'Test Julien',
                'email' => 'julien@acsi.cg',
                'structure_code' => 'ANT',
                'roles' => ['utilisateur'],
                'pivot_role' => 'Agent',
            ],
        ];

        foreach ($agents as $a) {
            $structure = Structure::query()
                ->where('code', $a['structure_code'])
                ->where('actif', true)
                ->first();

            if (! $structure) {
                continue;
            }

            $user = User::query()->updateOrCreate(
                ['email' => $a['email']],
                [
                    'name' => $a['nom'],
                    'password' => $password,
                    'structure_id' => $structure->id,
                    'actif' => true,
                    // Pas d’email_pro (volontairement : tu as demandé “pas email” pour tous).
                ]
            );

            $user->syncRoles($a['roles']);

            // Ajout d’une affectation structure pour que le périmètre plan de classement
            // soit cohérent (via user_structure.fonction_id).
            $user->structures()->syncWithoutDetaching([
                $structure->id => [
                    'role' => $a['pivot_role'],
                    'fonction_id' => $structure->fonction_id,
                    'date_affectation' => now(),
                    'date_fin' => null,
                ],
            ]);
        }
    }
}
