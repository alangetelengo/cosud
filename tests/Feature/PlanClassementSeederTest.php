<?php

namespace Tests\Feature;

use App\Models\Dossier;
use App\Models\Structure;
use Database\Seeders\ACSIOrganigrammeSeeder;
use Database\Seeders\PlanClassementSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Database\Seeders\TypeDossierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanClassementSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleAndPermissionSeeder::class,
            ACSIOrganigrammeSeeder::class,
            StructureSeeder::class,
            TypeDossierSeeder::class,
        ]);
    }

    public function test_mode_full_rattache_chaque_racine_a_une_structure_existante(): void
    {
        putenv('SEED_PLAN_CLASSEMENT=full');
        $_ENV['SEED_PLAN_CLASSEMENT'] = 'full';
        $_SERVER['SEED_PLAN_CLASSEMENT'] = 'full';

        $this->seed(PlanClassementSeeder::class);

        $racines = Dossier::whereNull('parent_id')->get();
        $this->assertGreaterThanOrEqual(9, $racines->count());

        foreach ($racines as $racine) {
            $this->assertNotNull(
                $racine->structure_id,
                "La racine « {$racine->nom} » (type « {$racine->type} ») n'est rattachée à aucune structure."
            );
            $this->assertTrue(
                Structure::whereKey($racine->structure_id)->exists(),
                "La structure référencée par la racine « {$racine->nom} » n'existe pas."
            );
        }

        putenv('SEED_PLAN_CLASSEMENT');
        unset($_ENV['SEED_PLAN_CLASSEMENT'], $_SERVER['SEED_PLAN_CLASSEMENT']);
    }
}
