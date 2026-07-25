<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $courrierPermissions = [
        'courriers.view',
        'courriers.create',
        'courriers.edit',
        'courriers.orienter',
        'courriers.ventiler',
        'courriers.signer',
        'courriers.rejeter',
        'courriers.transmettre',
        'courriers.archiver',
        'courriers.recevoir',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->courrierPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->courrierPermissions)
            ->delete();
    }
};
