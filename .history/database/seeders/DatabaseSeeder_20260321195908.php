<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            StructureSeeder::class,
            UsersAvecDirectionsSeeder::class,
            StatutDocumentSeeder::class,
            FormatsDocumentSeeder::class,
            TypeDossierSeeder::class,
            WorkflowEtapeSeeder::class,
            TypeDocumentSeeder::class,
            TypeMetadonneeSeeder::class,
            PlanClassementSeeder::class,
            DocumentSeeder::class,
        ]);
    }
}
