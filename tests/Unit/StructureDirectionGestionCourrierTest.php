<?php

namespace Tests\Unit;

use App\Models\Fonction;
use App\Models\Structure;
use App\Models\User;
use App\Services\CourrierSecretariatService;
use Database\Seeders\ACSIFonctionsSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StructureDirectionGestionCourrierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleAndPermissionSeeder::class,
            StructureSeeder::class,
            CourrierReferentielSeeder::class,
        ]);
    }

    public function test_direction_gestion_courrier_depuis_direction_ou_secretariat(): void
    {
        $ddsait = Structure::where('code', 'DDSAIT')->firstOrFail();
        $secDdsait = Structure::where('code', 'SEC-DDSAIT')->firstOrFail();

        $this->assertSame($ddsait->id, $ddsait->directionGestionCourrier()?->id);
        $this->assertSame($ddsait->id, $secDdsait->directionGestionCourrier()?->id);
    }

    public function test_directeur_pour_structure_direction_est_le_directeur_de_cette_direction(): void
    {
        $ddsait = Structure::where('code', 'DDSAIT')->firstOrFail();
        $dg = Structure::where('code', 'DG')->firstOrFail();

        $directeurDdsait = User::factory()->create(['structure_id' => $ddsait->id, 'name' => 'Directeur DDSAIT']);
        $directeurDdsait->assignRole('directeur');

        $directeurDg = User::factory()->create(['structure_id' => $dg->id, 'name' => 'Directeur Général']);
        $directeurDg->assignRole('directeur');

        $service = app(CourrierSecretariatService::class);

        $this->assertSame(
            $directeurDdsait->id,
            $service->directeurPourSecretariat($ddsait)?->id
        );
        $this->assertSame(
            $directeurDdsait->id,
            $service->directeurPourSecretariat(Structure::where('code', 'SEC-DDSAIT')->first())?->id
        );
    }

    public function test_directeur_pour_sec_dir_reconnait_le_role_dg(): void
    {
        $dg = Structure::where('code', 'DG')->firstOrFail();
        $secDir = Structure::where('code', 'SEC-DIR')->firstOrFail();

        $directeurGeneral = User::factory()->create([
            'structure_id' => $dg->id,
            'name' => 'LORD MARHYNO GANDOU',
        ]);
        $directeurGeneral->assignRole('dg');

        $service = app(CourrierSecretariatService::class);

        $this->assertSame(
            $directeurGeneral->id,
            $service->directeurPourSecretariat($secDir)?->id
        );
    }

    public function test_directeur_pour_sec_dir_via_titulaire_fonction_dg(): void
    {
        $this->seed(ACSIFonctionsSeeder::class);

        $dg = Structure::where('code', 'DG')->firstOrFail();
        $secDir = Structure::where('code', 'SEC-DIR')->firstOrFail();
        $fonctionId = Fonction::query()->where('code', 'directeur_general')->value('id');

        $directeurGeneral = User::factory()->create([
            'structure_id' => Structure::where('code', 'DAF')->value('id'),
            'name' => 'Titulaire DG via pivot',
        ]);
        $directeurGeneral->assignRole('dg');
        $directeurGeneral->structures()->syncWithoutDetaching([
            $dg->id => [
                'role' => 'Directeur Général',
                'fonction_id' => $fonctionId,
                'date_affectation' => now(),
                'date_fin' => null,
            ],
        ]);

        $service = app(CourrierSecretariatService::class);

        $this->assertSame(
            $directeurGeneral->id,
            $service->directeurPourSecretariat($secDir)?->id
        );
    }
}
