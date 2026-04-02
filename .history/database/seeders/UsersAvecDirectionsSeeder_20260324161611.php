<?php

namespace Database\Seeders;

use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersAvecDirectionsSeeder extends Seeder
{
    public function run(): void
    {
        $structures = Structure::all()->keyBy('code');

        $users = [
            [
                'name' => 'Administrateur GED',
                'email' => 'admin@acsi.cg',
                'structure_code' => 'DAF',
                'role' => 'admin',
                'pivot_role' => 'Agent',
            ],
            [
                'name' => 'Directeur Général',
                'email' => 'dg@acsi.cg',
                'telephone' => '+242061234567',
                'structure_code' => 'DG',
                'role' => 'dg',
                'pivot_role' => 'Directeur',
            ],
            [
                'name' => 'Directeur Ingénierie SI',
                'email' => 'dir.ing@acsi.cg',
                'structure_code' => 'DING-SI',
                'role' => 'utilisateur',
                'pivot_role' => 'Directeur',
            ],
            [
                'name' => 'Directeur Développement',
                'email' => 'dir.ddsait@acsi.cg',
                'structure_code' => 'DDSAIT',
                'role' => 'utilisateur',
                'pivot_role' => 'Directeur',
            ],
            [
                'name' => 'Ingénieur DDSAIT',
                'email' => 'ingenieur.ddsait@acsi.cg',
                'structure_code' => 'DDSAIT',
                'role' => 'utilisateur',
                'pivot_role' => 'Agent',
            ],
            [
                'name' => 'Directeur Infrastructures',
                'email' => 'dir.infra@acsi.cg',
                'structure_code' => 'DINFRA',
                'role' => 'utilisateur',
                'pivot_role' => 'Directeur',
            ],
            [
                'name' => 'Directeur Support et Formation',
                'email' => 'dir.support@acsi.cg',
                'structure_code' => 'DSUPPORT',
                'role' => 'utilisateur',
                'pivot_role' => 'Directeur',
            ],
            [
                'name' => 'Directeur Communication',
                'email' => 'dir.com@acsi.cg',
                'structure_code' => 'DCOM',
                'role' => 'utilisateur',
                'pivot_role' => 'Directeur',
            ],
            [
                'name' => 'Directeur Administratif et Financier',
                'email' => 'dir.af@acsi.cg',
                'structure_code' => 'DAF',
                'role' => 'utilisateur',
                'pivot_role' => 'Directeur',
            ],
            [
                'name' => 'Chef Secrétariat',
                'email' => 'chef.sec@acsi.cg',
                'structure_code' => 'SEC-DIR',
                'role' => 'utilisateur',
                'pivot_role' => 'Chef de service',
            ],
            [
                'name' => 'Chef Service Juridique',
                'email' => 'chef.jur@acsi.cg',
                'structure_code' => 'SJUR',
                'role' => 'utilisateur',
                'pivot_role' => 'Chef de service',
            ],
            [
                'name' => 'Contrôleur de gestion',
                'email' => 'controleur@acsi.cg',
                'structure_code' => 'CCG',
                'role' => 'utilisateur',
                'pivot_role' => 'Contrôleur',
            ],
            [
                'name' => 'Responsable Antennes',
                'email' => 'resp.antennes@acsi.cg',
                'structure_code' => 'ANT',
                'role' => 'utilisateur',
                'pivot_role' => 'Responsable',
            ],
            [
                'name' => 'Chef Service Finances',
                'email' => 'chef.finances@acsi.cg',
                'structure_code' => 'SVC-FIN',
                'role' => 'utilisateur',
                'pivot_role' => 'Chef de service',
            ],
            [
                'name' => 'Agent DAF',
                'email' => 'agent.daf@acsi.cg',
                'structure_code' => 'SVC-FIN',
                'role' => 'utilisateur',
                'pivot_role' => 'Agent',
            ],
            [
                'name' => 'Utilisateur Démo',
                'email' => 'utilisateur@acsi.cg',
                'structure_code' => 'DAF',
                'role' => 'utilisateur',
                'pivot_role' => 'Agent',
            ],
        ];

        foreach ($users as $u) {
            $structure = $structures->get($u['structure_code']);
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => Hash::make('password'),
                    'telephone' => $u['telephone'] ?? null,
                    'email_professionnel' => 'alangetelengo87@gmail.com',
                    'structure_id' => $structure?->id,
                ]
            );

            $user->syncRoles([$u['role']]);

            if ($structure) {
                $user->structures()->syncWithoutDetaching([
                    $structure->id => [
                        'role' => $u['pivot_role'],
                        'date_affectation' => now(),
                    ],
                ]);
            }
        }

        $this->assignerResponsables();
    }

    private function assignerResponsables(): void
    {
        $structures = Structure::all()->keyBy('code');
        $usersByEmail = User::all()->keyBy('email');

        $responsables = [
            'DG' => 'dg@acsi.cg',
            'SVC-FIN' => 'chef.finances@acsi.cg',
            'DING-SI' => 'dir.ing@acsi.cg',
            'DDSAIT' => 'dir.ddsait@acsi.cg',
            'DINFRA' => 'dir.infra@acsi.cg',
            'DSUPPORT' => 'dir.support@acsi.cg',
            'DCOM' => 'dir.com@acsi.cg',
            'DAF' => 'dir.af@acsi.cg',
            'SEC-DIR' => 'chef.sec@acsi.cg',
            'SJUR' => 'chef.jur@acsi.cg',
            'CCG' => 'controleur@acsi.cg',
            'ANT' => 'resp.antennes@acsi.cg',
        ];

        foreach ($responsables as $code => $email) {
            $structure = $structures->get($code);
            $user = $usersByEmail->get($email);
            if ($structure && $user) {
                $structure->update(['responsable_id' => $user->id]);
            }
        }
    }
}
