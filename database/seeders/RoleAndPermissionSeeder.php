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
            'dossiers.create-racine-structure',
            'dossiers.share-direction',
            'utilisateurs.view', 'utilisateurs.create', 'utilisateurs.edit', 'utilisateurs.delete',
            'courriers.view', 'courriers.create', 'courriers.edit', 'courriers.orienter', 'courriers.ventiler',
            'courriers.signer', 'courriers.rejeter', 'courriers.transmettre', 'courriers.archiver', 'courriers.recevoir',
            'courriers.voir-factures', 'courriers.voir-depenses',
            'suivi-paiements.view',
            'suivi-paiements.create',
            'suivi-factures.view',
            'bordereau-transmission.view',
            'dashboard.view',
            'organigramme.view',
            'parametres.view',
            'parametres.structures.view',
            'parametres.roles.view',
            'parametres.plan-classement.view',
            'parametres.types-dossiers.view',
            'parametres.categories-depense.view',
            'parametres.types-metadonnees.view',
            'parametres.audit.view',
            'parametres.workflow.view',
            'parametres.circuits-courriers.view',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $permissionsMenusAdminSeuls = [
            'types-documents.view',
            'types-documents.create',
            'types-documents.edit',
            'types-documents.delete',
            'recherche.view',
            'corbeille.view',
            'parametres.view',
            'parametres.structures.view',
            'parametres.roles.view',
            'parametres.plan-classement.view',
            'parametres.types-dossiers.view',
            'parametres.categories-depense.view',
            'parametres.types-metadonnees.view',
            'parametres.audit.view',
            'parametres.workflow.view',
            'parametres.circuits-courriers.view',
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
            'dashboard.view',
            'courriers.view', 'courriers.create', 'courriers.edit', 'courriers.transmettre',
            'courriers.archiver', 'courriers.recevoir',
        ];

        Role::firstOrCreate(['name' => 'secretaire_direction', 'guard_name' => 'web'])
            ->syncPermissions(array_merge($permissionsSecretariatCourrier, [
                // SEC-DIR : flux factures+MAD toutes structures ; hors DG : filtre structure.
                'courriers.voir-factures',
                'courriers.voir-depenses',
            ]));

        Role::firstOrCreate(['name' => 'directeur', 'guard_name' => 'web'])->syncPermissions([
            'documents.view', 'documents.create', 'documents.edit', 'documents.view-hierarchique',
            'dossiers.view', 'dossiers.create', 'dossiers.edit', 'dossiers.delete',
            'dossiers.create-structure', 'dossiers.share-direction', 'dossiers.create-racine-structure',
            'dashboard.view', 'organigramme.view',
            'courriers.view', 'courriers.orienter', 'courriers.ventiler', 'courriers.signer', 'courriers.rejeter', 'courriers.archiver',
        ]);

        $userPerms = [
            'documents.view', 'documents.create', 'documents.edit',
            'dossiers.view', 'dossiers.create', 'dossiers.edit', 'dossiers.delete',
            'dossiers.create-structure',
            'dashboard.view',
        ];
        Role::firstOrCreate(['name' => 'utilisateur', 'guard_name' => 'web'])->syncPermissions($userPerms);
        Role::firstOrCreate(['name' => 'chef_service', 'guard_name' => 'web'])
            ->syncPermissions(array_merge($userPerms, ['organigramme.view']));
        foreach (['chef_projet', 'chef_pool', 'chef_centre'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'])->syncPermissions($userPerms);
        }

        $permsParRoleCircuit = [
            'particulier_dg' => array_merge($permissionsSecretariatCourrier, [
                'courriers.voir-factures',
                'courriers.voir-depenses',
                'suivi-paiements.view',
                'suivi-factures.view',
                'bordereau-transmission.view',
            ]),
            'particulier_ac' => array_merge($permissionsSecretariatCourrier, [
                'courriers.voir-factures',
                'courriers.voir-depenses',
                'bordereau-transmission.view',
            ]),
            'responsable_dossiers_prestataires' => array_merge($permissionsSecretariatCourrier, [
                'courriers.voir-factures',
                'suivi-factures.view',
            ]),
            'responsable_suivi_depenses' => array_merge($permissionsSecretariatCourrier, [
                'courriers.voir-depenses',
                'suivi-paiements.view',
                'suivi-paiements.create',
                'bordereau-transmission.view',
            ]),
            'agent_comptable' => array_merge($permissionsSecretariatCourrier, [
                'courriers.voir-factures',
                'courriers.voir-depenses',
                'bordereau-transmission.view',
            ]),
            'caissier' => array_merge($permissionsSecretariatCourrier, [
                'courriers.voir-factures',
                'courriers.voir-depenses',
                'bordereau-transmission.view',
            ]),
        ];

        foreach ($permsParRoleCircuit as $roleName => $perms) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'])->syncPermissions($perms);
        }

        $dg->givePermissionTo([
            'dashboard.view',
            'courriers.voir-factures',
            'courriers.voir-depenses',
            'suivi-paiements.view',
            'suivi-paiements.create',
            'suivi-factures.view',
            'bordereau-transmission.view',
        ]);

        $accesGedBase = Permission::whereIn('name', ['documents.view', 'dossiers.view', 'dashboard.view'])
            ->where('guard_name', 'web')
            ->get();

        if ($accesGedBase->isNotEmpty()) {
            foreach (Role::where('guard_name', 'web')->get() as $role) {
                $role->givePermissionTo($accesGedBase);
            }
        }

        foreach (Role::where('guard_name', 'web')->where('name', '!=', 'admin')->get() as $role) {
            $role->revokePermissionTo($permissionsMenusAdminSeuls);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
