<?php

namespace Tests\Feature;

use App\Models\Structure;
use App\Models\User;
use Database\Seeders\ACSIFonctionsSeeder;
use Database\Seeders\ACSIOrganigrammeSeeder;
use Database\Seeders\ACSIUsersSeeder;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AcsiBootstrapSeedersTest extends TestCase
{
    use RefreshDatabase;

    private string $agentsPath;

    private ?string $agentsBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agentsPath = database_path('seeders/data/acsi_agents_full.json');
        $this->agentsBackup = File::exists($this->agentsPath) ? File::get($this->agentsPath) : null;

        File::ensureDirectoryExists(dirname($this->agentsPath));
        File::put($this->agentsPath, json_encode([
            [
                'matricule' => '003001A',
                'nom' => 'TEST',
                'prenom' => 'Agent Un',
                'libDirection' => 'DIRECTION TECHNIQUE',
                'libService' => 'SERVICE DES ETUDES',
                'libFonction' => 'CHEF SERVICE',
                'libEmploi' => 'CHEF SCE ETUDES',
            ],
            [
                'matricule' => '003002B',
                'nom' => 'DEMO',
                'prenom' => 'Agent Deux',
                'libDirection' => 'DIRECTION COMMERCIALE',
                'libService' => 'SCE COMMERCIAL',
                'libFonction' => null,
                'libEmploi' => 'ATTACHE ADMIN',
            ],
        ], JSON_UNESCAPED_UNICODE));
    }

    protected function tearDown(): void
    {
        if ($this->agentsBackup !== null) {
            File::put($this->agentsPath, $this->agentsBackup);
        }

        parent::tearDown();
    }

    public function test_organigramme_ne_cree_que_la_direction_generale(): void
    {
        $this->seed([
            RoleAndPermissionSeeder::class,
            ACSIOrganigrammeSeeder::class,
        ]);

        $this->assertDatabaseCount('structures', 1);
        $this->assertDatabaseHas('structures', [
            'code' => 'DG',
            'nom' => 'DIRECTION GENERALE',
            'type' => 'direction',
            'parent_id' => null,
            'actif' => true,
        ]);
    }

    public function test_agents_sont_crees_sans_structure_role_ni_fonction(): void
    {
        $this->seed([
            RoleAndPermissionSeeder::class,
            ACSIOrganigrammeSeeder::class,
            ACSIFonctionsSeeder::class,
            ACSIUsersSeeder::class,
        ]);

        $agent = User::where('email', '003001a@acsi.cg')->first();
        $this->assertNotNull($agent);
        $this->assertSame('Agent Un TEST', $agent->name);
        $this->assertNull($agent->structure_id);
        $this->assertCount(0, $agent->roles);
        $this->assertCount(0, $agent->structures);

        $agent2 = User::where('email', '003002b@acsi.cg')->first();
        $this->assertNotNull($agent2);
        $this->assertNull($agent2->structure_id);
        $this->assertCount(0, $agent2->roles);
    }

    public function test_admin_bootstrap_est_alange_avec_role_admin_sur_dg(): void
    {
        $this->seed([
            RoleAndPermissionSeeder::class,
            ACSIOrganigrammeSeeder::class,
            ACSIFonctionsSeeder::class,
            ACSIUsersSeeder::class,
            AdminUserSeeder::class,
        ]);

        $admin = User::where('email', 'alange@acsi.cg')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('admin'));

        $dg = Structure::where('code', 'DG')->first();
        $this->assertNotNull($dg);
        $this->assertSame($dg->id, $admin->structure_id);
        $this->assertTrue($admin->structures->contains('id', $dg->id));

        $this->assertNull(User::where('email', 'admin@acsi.cg')->first());
    }
}
