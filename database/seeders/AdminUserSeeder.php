<?php

namespace Database\Seeders;

use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Compte technique d’administration (bootstrap).
 * Mot de passe par défaut : « password ».
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $dg = Structure::where('code', 'DG')->first();
        if (! $dg) {
            $this->command?->warn('AdminUserSeeder : structure DG introuvable, compte admin non créé.');

            return;
        }

        $user = User::updateOrCreate(
            ['email' => 'alange@acsi.cg'],
            [
                'name' => 'Administrateur GED',
                'password' => Hash::make('password'),
                'structure_id' => $dg->id,
                'actif' => true,
            ]
        );

        $user->syncRoles(['admin']);

        $user->structures()->syncWithoutDetaching([
            $dg->id => [
                'role' => 'Administrateur',
                'fonction_id' => null,
                'date_affectation' => now(),
                'date_fin' => null,
            ],
        ]);
    }
}
