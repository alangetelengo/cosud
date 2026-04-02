<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Référentiel des rôles et permissions (Spatie).
 * L’affectation des rôles aux comptes de démo se fait dans UsersAvecDirectionsSeeder.
 */
class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'documents.view', 'documents.view-hierarchique', 'documents.create', 'documents.edit', 'documents.delete',
            'types-documents.view', 'types-documents.create', 'types-documents.edit', 'types-documents.delete',
            'dossiers.view', 'dossiers.create', 'dossiers.edit', 'dossiers.delete', 'dossiers.view-confidentiel',
            'dossiers.create-structure',
            /** Créer un dossier racine (sans parent) rattaché à une structure — ex. responsable / directeur. */
            'dossiers.create-racine-structure',
            /** Partager les dossiers du plan rattachés à une direction (titulaire de la structure + ce rôle). */
            'dossiers.share-direction',
            'utilisateurs.view', 'utilisateurs.create', 'utilisateurs.edit', 'utilisateurs.delete',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::where('guard_name', 'web')->pluck('name'));

        $dg = Role::firstOrCreate(['name' => 'dg', 'guard_name' => 'web']);
        $dg->syncPermissions(Permission::where('guard_name', 'web')->pluck('name'));

        $directeur = Role::firstOrCreate(['name' => 'directeur', 'guard_name' => 'web']);
        $directeur->syncPermissions([
            // Base "utilisateur" : sinon le directeur n'a pas les menus Documents / Dossiers.
            'documents.view',
            'documents.create',
            'documents.edit',
            'types-documents.view',
            'dossiers.view',
            'dossiers.create',
            'dossiers.edit',
            'dossiers.delete',
            'dossiers.create-structure',

            /** Aligné sur {@see User::aVisibiliteElargiePlanOrganisation} : un seul levier Spatie (pas de doublon hasRole). */
            'documents.view-hierarchique',
            'dossiers.share-direction',
            'dossiers.create-racine-structure',
        ]);

        $user = Role::firstOrCreate(['name' => 'utilisateur', 'guard_name' => 'web']);
        $user->syncPermissions([
            'documents.view', 'documents.create', 'documents.edit',
            'types-documents.view', 'dossiers.view', 'dossiers.create', 'dossiers.edit', 'dossiers.delete',
            'dossiers.create-structure',
        ]);

        // Rôles métier optionnels (utilisés via workflow_etapes.role_requis).
        // On leur donne les mêmes permissions que "utilisateur" pour ne pas bloquer l'accès.
        $chefService = Role::firstOrCreate(['name' => 'chef_service', 'guard_name' => 'web']);
        $chefService->syncPermissions($user->permissions);

        $chefProjet = Role::firstOrCreate(['name' => 'chef_projet', 'guard_name' => 'web']);
        $chefProjet->syncPermissions($user->permissions);

        $chefPool = Role::firstOrCreate(['name' => 'chef_pool', 'guard_name' => 'web']);
        $chefPool->syncPermissions($user->permissions);

        $chefCentre = Role::firstOrCreate(['name' => 'chef_centre', 'guard_name' => 'web']);
        $chefCentre->syncPermissions($user->permissions);
    }
}
