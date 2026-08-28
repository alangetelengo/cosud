<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $permissions = [
        'factures-regularisation.view',
        'factures-regularisation.create',
        'factures-regularisation.payer',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $this->attribuerAuxRoles();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'factures-regularisation.view',
                'factures-regularisation.payer',
            ])
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function attribuerAuxRoles(): void
    {
        $taty = Role::query()->where('name', 'responsable_dossiers_prestataires')->where('guard_name', 'web')->first();
        if ($taty) {
            $taty->givePermissionTo([
                'factures-regularisation.view',
                'factures-regularisation.create',
            ]);
        }

        $eleni = Role::query()->where('name', 'responsable_suivi_depenses')->where('guard_name', 'web')->first();
        if ($eleni) {
            $eleni->givePermissionTo([
                'factures-regularisation.view',
                'factures-regularisation.payer',
            ]);
            if ($eleni->hasPermissionTo('factures-regularisation.create')) {
                $eleni->revokePermissionTo('factures-regularisation.create');
            }
        }

        $dg = Role::query()->where('name', 'dg')->where('guard_name', 'web')->first();
        if ($dg) {
            $dg->givePermissionTo($this->permissions);
        }

        $admin = Role::query()->where('name', 'admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo($this->permissions);
        }
    }
};
