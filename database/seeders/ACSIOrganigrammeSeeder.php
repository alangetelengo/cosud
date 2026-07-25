<?php

namespace Database\Seeders;

use App\Models\Structure;
use Illuminate\Database\Seeder;

/**
 * Organigramme minimal au démarrage : seule la Direction Générale.
 * Les autres directions / services sont créés ensuite par l’administrateur.
 */
class ACSIOrganigrammeSeeder extends Seeder
{
    public function run(): void
    {
        Structure::updateOrCreate(
            ['code' => 'DG'],
            [
                'parent_id' => null,
                'nom' => 'DIRECTION GENERALE',
                'type' => 'direction',
                'actif' => true,
            ]
        );
    }
}
