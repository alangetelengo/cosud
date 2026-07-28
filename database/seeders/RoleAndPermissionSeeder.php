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
            'recherche.view',
            'corbeille.view',
            'dossiers.view', 'dossiers.create', 'dossiers.edit', 'dossiers.delete', 'dossiers.view-confidentiel',
            'dossiers.create-structure',
            /** Créer un dossier racine (sans parent) rattaché à une structure — ex. responsable / directeur. */
            'dossiers.create-racine-structure',
            /** Partager les dossiers du plan rattachés à une direction (titulaire de la structure + ce rôle). */
            'dossiers.share-direction',
            'utilisateurs.view', 'utilisateurs.create', 'utilisateurs.edit', 'utilisateurs.delete',
            'courriers.view', 'courriers.create', 'courriers.edit', 'courriers.orienter', 'courriers.ventiler',
            'courriers.signer', 'courriers.rejeter', 'courriers.transmettre', 'courriers.archiver', 'courriers.recevoir',
            'suivi-paiements.view',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        /** Menus réservés à l’admin par défaut (réattribuables via Paramètres → Rôles). */
        $permissionsMenusAdminSeuls = [
            'types-documents.view',
            'types-documents.create',
            'types-documents.edit',
            'types-documents.delete',
            'recherche.view',
            'corbeille.view',
        ];

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::where('guard_name', 'web')->pluck('name'));

        $dg = Role::firstOrCreate(['name' => 'dg', 'guard_name' => 'web']);
        $dg->syncPermissions(
            Permission::where('guard_name', 'web')
                ->whereNotIn('name', $permissionsMenusAdminSeuls)
                ->pluck('name')
        );

        $permissionsSecretariatCourrier = [
            'documents.view', 'documents.create', 'documents.edit',
            'dossiers.view', 'dossiers.create', 'dossiers.edit',
            'courriers.view', 'courriers.create', 'courriers.edit', 'courriers.transmettre',
            'courriers.archiver', 'courriers.recevoir',
        ];

        $secretaireDirection = Role::firstOrCreate(['name' => 'secretaire_direction', 'guard_name' => 'web']);
        $secretaireDirection->syncPermissions($permissionsSecretariatCourrier);

        $directeur = Role::firstOrCreate(['name' => 'directeur', 'guard_name' => 'web']);
        $directeur->syncPermissions([
            'documents.view',
            'documents.create',
            'documents.edit',
            'dossiers.view',
            'dossiers.create',
            'dossiers.edit',
            'dossiers.delete',
            'dossiers.create-structure',
            'documents.view-hierarchique',
            'dossiers.share-direction',
            'dossiers.create-racine-structure',
            'courriers.view', 'courriers.orienter', 'courriers.ventiler', 'courriers.signer', 'courriers.rejeter', 'courriers.archiver',
        ]);

        $user = Role::firstOrCreate(['name' => 'utilisateur', 'guard_name' => 'web']);
        $user->syncPermissions([
            'documents.view', 'documents.create', 'documents.edit',
            'dossiers.view', 'dossiers.create', 'dossiers.edit', 'dossiers.delete',
            'dossiers.create-structure',
        ]);

        $chefService = Role::firstOrCreate(['name' => 'chef_service', 'guard_name' => 'web']);
        $chefService->syncPermissions($user->permissions);

        $chefProjet = Role::firstOrCreate(['name' => 'chef_projet', 'guard_name' => 'web']);
        $chefProjet->syncPermissions($user->permissions);

        $chefPool = Role::firstOrCreate(['name' => 'chef_pool', 'guard_name' => 'web']);
        $chefPool->syncPermissions($user->permissions);

        $chefCentre = Role::firstOrCreate(['name' => 'chef_centre', 'guard_name' => 'web']);
        $chefCentre->syncPermissions($user->permissions);

        foreach ([
            'particulier_dg',
            'particulier_ac',
            'responsable_dossiers_prestataires',
            'responsable_suivi_depenses',
            'agent_comptable',
            'caissier',
        ] as $roleCircuit) {
            $role = Role::firstOrCreate(['name' => $roleCircuit, 'guard_name' => 'web']);
            $role->syncPermissions(array_merge($permissionsSecretariatCourrier, ['suivi-paiements.view']));
        }

        // Accès GED de base : Documents + Dossiers visibles pour tous les rôles.
        $accesGedBase = Permission::whereIn('name', ['documents.view', 'dossiers.view'])
            ->where('guard_name', 'web')
            ->get();

        if ($accesGedBase->isNotEmpty()) {
            foreach (Role::where('guard_name', 'web')->get() as $role) {
                $role->givePermissionTo($accesGedBase);
            }
        }

        // Garantit que seuls les rôles explicitement autorisés gardent les menus admin.
        foreach (Role::where('guard_name', 'web')->where('name', '!=', 'admin')->get() as $role) {
            $role->revokePermissionTo($permissionsMenusAdminSeuls);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
