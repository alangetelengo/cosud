<?php

use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $arrivee = SensCourrier::query()->where('code', SensCourrier::ARRIVEE)->first();
        if ($arrivee) {
            StatutCourrier::updateOrCreate(
                ['sens_courrier_id' => $arrivee->id, 'code' => 'annule'],
                [
                    'libelle' => 'Annulé',
                    'ordre' => 6,
                    'est_initial' => false,
                    'est_final' => true,
                    'actif' => true,
                ]
            );
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'courriers.delete', 'guard_name' => 'web']);
    }

    public function down(): void
    {
        $arrivee = SensCourrier::query()->where('code', SensCourrier::ARRIVEE)->first();
        if ($arrivee) {
            StatutCourrier::query()
                ->where('sens_courrier_id', $arrivee->id)
                ->where('code', 'annule')
                ->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::query()
            ->where('guard_name', 'web')
            ->where('name', 'courriers.delete')
            ->delete();
    }
};
