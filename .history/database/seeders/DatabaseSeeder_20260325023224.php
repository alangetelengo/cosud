<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Ordre : rôles/permissions → structures → utilisateurs (rôles explicites) → référentiels
     * → types / workflow → plan de classement (dossiers). DocumentSeeder : optionnel (fichiers démo).
     *
     * Plan de classement : env SEED_PLAN_CLASSEMENT — full (défaut, arborescence complète),
     * dg_only (démo DG seule), none (aucun dossier).
     * Racines « Mes dossiers » : non créées au seed ni à l’inscription ; création paresseuse à la première
     * ouverture de création de dossier (DossierController). Pour vider les racines existantes :
     * php artisan dossiers:supprimer-racines-mes-dossiers
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            StructureSeeder::class,
            FonctionSeeder::class,
            UsersAvecDirectionsSeeder::class,
            StatutDocumentSeeder::class,
            FormatsDocumentSeeder::class,
            TypeDossierSeeder::class,
            WorkflowEtapeSeeder::class,
            TypeDocumentSeeder::class,
            TypeMetadonneeSeeder::class,
            // PlanClassementSeeder::class,
        ]);

        if (filter_var(env('SEED_DEMO_DOCUMENTS', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(DocumentSeeder::class);
        }
    }
}
