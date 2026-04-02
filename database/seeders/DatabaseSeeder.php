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
            // ACSI (réalité) : organigramme -> fonctions -> utilisateurs
            ACSIOrganigrammeSeeder::class,
            ACSIFonctionsSeeder::class,
            ACSIUsersSeeder::class,
            AdminUserSeeder::class,
            StatutDocumentSeeder::class,
            FormatsDocumentSeeder::class,
            TypeDossierSeeder::class,
            WorkflowEtapeSeeder::class,
            // Le preset workflow ne crée plus de circuits projet/service (principe global hiérarchique).
            WorkflowPresetSeeder::class,
            TypeDocumentSeeder::class,
            TypeMetadonneeSeeder::class,
            PlanClassementSeeder::class,
            // Le seeder ACSI projet est conservé pour neutraliser d'anciens circuits "acsi_service_*".
            ACSIProjectFunctionWorkflowSeeder::class,
        ]);

        if (filter_var(env('SEED_DEMO_DOCUMENTS', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(DocumentSeeder::class);
        }
    }
}
