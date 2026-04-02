<?php

namespace Database\Seeders;

use App\Models\Fonction;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersAvecDirectionsSeeder extends Seeder
{
    public function run(): void
    {
        Structure::query()->update(['responsable_id' => null]);

        $structures = Structure::all()->keyBy('code');
        $fonctionsByCode = Fonction::all()->keyBy('code');

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
                'structure_code' => 'SVC-DDI-DEVINT',
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
                'name' => 'Chef Développement & Intégration',
                'email' => 'chef.devint@acsi.cg',
                'structure_code' => 'SVC-DDI-DEVINT',
                'role' => 'utilisateur',
                'pivot_role' => 'Chef de service',
            ],
            [
                'name' => 'Chef de projet Développement & Intégration',
                'email' => 'chef.projet.devint@acsi.cg',
                'structure_code' => 'SVC-DDI-DEVINT',
                'role' => 'utilisateur',
                'pivot_role' => 'Chef de projet',
            ],
            [
                'name' => 'Chef de pool Développement & Intégration',
                'email' => 'chef.pool.devint@acsi.cg',
                'structure_code' => 'SVC-DDI-DEVINT',
                'role' => 'utilisateur',
                'pivot_role' => 'Chef de pool',
            ],
            [
                'name' => 'Chef Bases de Données',
                'email' => 'chef.bdd@acsi.cg',
                'structure_code' => 'SVC-DDI-BDD',
                'role' => 'utilisateur',
                'pivot_role' => 'Chef de service',
            ],
            [
                'name' => 'Chef Maintenance Applicative',
                'email' => 'chef.maint@acsi.cg',
                'structure_code' => 'SVC-DDI-MAINT',
                'role' => 'utilisateur',
                'pivot_role' => 'Chef de service',
            ],
            [
                'name' => 'Chef Veille & Innovation',
                'email' => 'chef.veille@acsi.cg',
                'structure_code' => 'SVC-DDI-VEILLE',
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
            $emailPro = $u['email_professionnel']
                ?? env('SEED_EMAIL_PROFESSIONNEL')
                ?: null;

            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => Hash::make('password'),
                    'telephone' => $u['telephone'] ?? null,
                    'email_professionnel' => $emailPro,
                    'structure_id' => $structure?->id,
                ]
            );

            $roles = [$u['role']];
            $pivotRole = strtolower((string) ($u['pivot_role'] ?? ''));
            if ($u['role'] === 'utilisateur' && str_contains($pivotRole, 'directeur')) {
                $roles[] = 'directeur';
            }

            // Rôles métier pour le workflow (workflow_etapes.role_requis)
            // Certains libellés ACSI sont plus spécifiques (chef de section/centre/sce).
            // Pour le workflow ACSI :
            // - "chef de centre" doit être indépendant (rôle Spatie = chef_centre)
            // - "chef de section/sce" reste l'étape finale (rôle Spatie = chef_service)
            if (
                // Chef de centre correspond au rôle Spatie "chef_centre" (étape intermédiaire).
                str_contains($pivotRole, 'chef de centre')
                || str_contains($pivotRole, 'chef centre')
            ) {
                $roles[] = 'chef_centre';
            } elseif (
                str_contains($pivotRole, 'chef de service')
                || str_contains($pivotRole, 'chef service')
                || str_contains($pivotRole, 'chef de section')
                || str_contains($pivotRole, 'chef sce')
                || str_contains($pivotRole, 'controleur')
                || str_contains($pivotRole, 'contrôleur')
            ) {
                $roles[] = 'chef_service';
            }
            if (str_contains($pivotRole, 'chef de projet') || str_contains($pivotRole, 'projet')) {
                $roles[] = 'chef_projet';
            }
            if (str_contains($pivotRole, 'chef de pool') || str_contains($pivotRole, 'pool')) {
                $roles[] = 'chef_pool';
            }
            // Chef adjoint : il doit partager la même "permission" que le chef de projet.
            if (str_contains($pivotRole, 'adjoint')) {
                $roles[] = 'chef_projet';
            }

            $roles = array_values(array_unique($roles));
            $user->syncRoles($roles);

            if ($structure) {
                $fonctionPivotId = $this->fonctionIdPourPivot($u, $structure, $fonctionsByCode);
                $user->structures()->syncWithoutDetaching([
                    $structure->id => [
                        'role' => $u['pivot_role'],
                        'fonction_id' => $fonctionPivotId,
                        'date_affectation' => now(),
                    ],
                ]);
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<string, \App\Models\Fonction>  $fonctionsByCode
     */
    private function fonctionIdPourPivot(array $u, Structure $structure, $fonctionsByCode): ?int
    {
        $role = $u['pivot_role'] ?? '';
        $roleLower = strtolower((string) $role);

        if ($structure->code === 'DG' && str_contains($roleLower, 'directeur')) {
            return $fonctionsByCode->get('directeur_general')?->id;
        }

        // Priorité aux rôles métiers spécifiques
        if (str_contains($roleLower, 'chef de pool') || str_contains($roleLower, 'pool')) {
            return $fonctionsByCode->get('chef_pool')?->id;
        }
        if (str_contains($roleLower, 'chef de projet') || str_contains($roleLower, 'projet')) {
            return $fonctionsByCode->get('chef_projet')?->id;
        }

        if (str_contains($roleLower, 'directeur') || $roleLower === 'responsable') {
            return $fonctionsByCode->get('directeur_direction')?->id;
        }

        // "Chef de centre" : pour la visibilité / validation hiérarchique, on garde généralement
        // `chef_service` côté pivot fonction_id (nos structures de niveau "service" sont en `chef_service`).
        if (
            str_contains($roleLower, 'chef de centre')
            || str_contains($roleLower, 'chef centre')
        ) {
            return $fonctionsByCode->get('chef_service')?->id;
        }

        // Chef de service (y compris variantes ACSI : section/sce/controleur)
        if (
            str_contains($roleLower, 'chef de service')
            || str_contains($roleLower, 'chef service')
            || str_contains($roleLower, 'chef de section')
            || str_contains($roleLower, 'chef sce')
            || str_contains($roleLower, 'controleur')
            || str_contains($roleLower, 'contrôleur')
        ) {
            return $fonctionsByCode->get('chef_service')?->id;
        }

        return null;
    }
}
