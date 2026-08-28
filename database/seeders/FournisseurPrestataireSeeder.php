<?php

namespace Database\Seeders;

use App\Models\FournisseurPrestataire;
use Illuminate\Database\Seeder;

class FournisseurPrestataireSeeder extends Seeder
{
    public function run(): void
    {
        $lignes = [
            [
                'nom' => 'ACS - Approvisionnement Congo Services',
                'type' => FournisseurPrestataire::TYPE_PRESTATAIRE,
                'type_contrat' => 'Entretien des groupes électrogènes',
                'a_contrat' => true,
                'a_dossier_fiscal' => false,
            ],
            [
                'nom' => 'AIRTEL',
                'type' => FournisseurPrestataire::TYPE_PARTENAIRE,
                'type_contrat' => 'Protocole de partenariat d’intégration plateforme à travers un réseau mobile (API)',
                'a_contrat' => true,
                'a_dossier_fiscal' => false,
            ],
            [
                'nom' => 'BILLY SERVICES',
                'type' => FournisseurPrestataire::TYPE_PRESTATAIRE,
                'type_contrat' => 'Location véhicule',
                'a_contrat' => true,
                'a_dossier_fiscal' => false,
            ],
        ];

        foreach ($lignes as $ligne) {
            FournisseurPrestataire::query()->updateOrCreate(
                ['nom_normalise' => FournisseurPrestataire::normaliserNom($ligne['nom'])],
                array_merge($ligne, [
                    'nom_normalise' => FournisseurPrestataire::normaliserNom($ligne['nom']),
                    'actif' => true,
                ])
            );
        }
    }
}
