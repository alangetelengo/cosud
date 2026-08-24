<?php

namespace Database\Seeders;

use App\Models\CategorieDepense;
use Illuminate\Database\Seeder;

class CategorieDepenseSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => CategorieDepense::CODE_FACTURE, 'libelle' => 'Fiche de suivi paiement facture', 'ordre' => 10, 'est_systeme' => true],
            ['code' => CategorieDepense::CODE_PAIEMENT_DIVERS, 'libelle' => 'Fiche de suivi paiement divers', 'ordre' => 20, 'est_systeme' => true],
            ['code' => CategorieDepense::CODE_PAIE, 'libelle' => 'Paie', 'ordre' => 30, 'est_systeme' => false],
            ['code' => CategorieDepense::CODE_COMMISSION, 'libelle' => 'Commission', 'ordre' => 40, 'est_systeme' => false],
            ['code' => CategorieDepense::CODE_TTF, 'libelle' => 'TTF', 'ordre' => 50, 'est_systeme' => false],
        ];

        foreach ($categories as $data) {
            CategorieDepense::query()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'libelle' => $data['libelle'],
                    'ordre' => $data['ordre'],
                    'actif' => true,
                    'est_systeme' => $data['est_systeme'],
                ]
            );
        }
    }
}
