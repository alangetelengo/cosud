<?php

namespace Database\Seeders;

use App\Models\TypeMetadonnee;
use Illuminate\Database\Seeder;

class TypeMetadonneeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'auteur', 'libelle' => 'Auteur', 'type_valeur' => 'texte', 'description' => 'Auteur du document (PDF Author)'],
            ['code' => 'titre', 'libelle' => 'Titre', 'type_valeur' => 'texte', 'description' => 'Titre du document (PDF Title)'],
            ['code' => 'createur', 'libelle' => 'Créateur', 'type_valeur' => 'texte', 'description' => 'Application de création (PDF Creator)'],
            ['code' => 'producteur', 'libelle' => 'Producteur', 'type_valeur' => 'texte', 'description' => 'Application ayant produit le PDF'],
            ['code' => 'date_creation', 'libelle' => 'Date de création', 'type_valeur' => 'date', 'description' => 'Date de création du document'],
            ['code' => 'date_modification', 'libelle' => 'Date de modification', 'type_valeur' => 'date', 'description' => 'Date de dernière modification'],
            ['code' => 'nb_pages', 'libelle' => 'Nombre de pages', 'type_valeur' => 'numerique', 'description' => 'Nombre de pages (PDF)'],
            ['code' => 'mots_cles', 'libelle' => 'Mots-clés', 'type_valeur' => 'texte', 'description' => 'Mots-clés (PDF Keywords)'],
        ];

        foreach ($types as $t) {
            TypeMetadonnee::updateOrCreate(
                ['code' => $t['code']],
                array_merge($t, ['actif' => true])
            );
        }
    }
}
