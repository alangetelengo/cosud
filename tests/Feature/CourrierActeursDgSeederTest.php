<?php

namespace Tests\Feature;

use App\Models\Structure;
use App\Models\User;
use Database\Seeders\ACSIFonctionsSeeder;
use Database\Seeders\CircuitCourrierSeeder;
use Database\Seeders\CourrierActeursDgSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CourrierActeursDgSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_affecte_les_trois_acteurs_dg(): void
    {
        $this->seed([
            RoleAndPermissionSeeder::class,
            StructureSeeder::class,
            ACSIFonctionsSeeder::class,
            CourrierReferentielSeeder::class,
            CircuitCourrierSeeder::class,
            CourrierActeursDgSeeder::class,
        ]);

        $dg = Structure::where('code', 'DG')->firstOrFail();
        $secDir = Structure::where('code', 'SEC-DIR')->firstOrFail();

        $directeur = User::where('email', '003057w@acsi.cg')->firstOrFail();
        $this->assertTrue($directeur->hasRole('dg'));
        $this->assertSame($dg->id, (int) $directeur->structure_id);

        $particuliere = User::where('email', '003144s@acsi.cg')->firstOrFail();
        $this->assertTrue($particuliere->hasRole('particulier_dg'));
        $this->assertSame($secDir->id, (int) $particuliere->structure_id);
        $this->assertTrue($particuliere->can('documents.view'));
        $this->assertTrue($particuliere->can('dossiers.view'));

        $respDossiers = User::where('email', '001958d@acsi.cg')->firstOrFail();
        $this->assertTrue($respDossiers->hasRole('responsable_dossiers_prestataires'));
        $this->assertSame($secDir->id, (int) $respDossiers->structure_id);
        $this->assertTrue($respDossiers->can('documents.view'));
        $this->assertTrue($respDossiers->can('dossiers.view'));

        $respDepenses = User::where('email', '003091k@acsi.cg')->firstOrFail();
        $this->assertSame('ASTRIDE ELENI OSSEBI', $respDepenses->name);
        $this->assertTrue($respDepenses->hasRole('responsable_suivi_depenses'));
        $this->assertSame($secDir->id, (int) $respDepenses->structure_id);
        $this->assertTrue($respDepenses->can('documents.view'));
        $this->assertTrue($respDepenses->can('dossiers.view'));

        $ancienSuivi = User::where('email', '003269d@acsi.cg')->first();
        if ($ancienSuivi) {
            $this->assertFalse($ancienSuivi->hasRole('responsable_suivi_depenses'));
        }
    }

    public function test_seed_affecte_agent_comptable_dac_et_particuliere(): void
    {
        $this->seed([
            RoleAndPermissionSeeder::class,
            StructureSeeder::class,
            ACSIFonctionsSeeder::class,
            CourrierReferentielSeeder::class,
            CircuitCourrierSeeder::class,
            CourrierActeursDgSeeder::class,
        ]);

        $dac = Structure::where('code', 'DAC')->firstOrFail();
        $secDac = Structure::where('code', 'SEC-DAC')->firstOrFail();

        $this->assertSame('agent_comptable', $dac->role_technique);

        $agent = User::where('email', '003232b@acsi.cg')->firstOrFail();
        $this->assertSame('RAÏSSA LEBANITOU', $agent->name);
        $this->assertTrue($agent->hasRole('agent_comptable'));
        $this->assertSame($dac->id, (int) $agent->structure_id);
        $this->assertSame($agent->id, $dac->titulaireValidationActuel()?->id);

        $particuliere = User::where('email', '002871v@acsi.cg')->firstOrFail();
        $this->assertSame('NICOLE BIENVENUE OBA', $particuliere->name);
        $this->assertTrue($particuliere->hasRole('particulier_ac'));
        $this->assertSame($secDac->id, (int) $particuliere->structure_id);
        $this->assertTrue($particuliere->can('documents.view'));
        $this->assertTrue($particuliere->can('dossiers.view'));
    }

    public function test_seed_affecte_directeur_ddsait(): void
    {
        $this->seed([
            RoleAndPermissionSeeder::class,
            StructureSeeder::class,
            ACSIFonctionsSeeder::class,
            CourrierReferentielSeeder::class,
            CircuitCourrierSeeder::class,
            CourrierActeursDgSeeder::class,
        ]);

        $ddsait = Structure::where('code', 'DDSAIT')->firstOrFail();

        $directeur = User::where('email', '003152b@acsi.cg')->firstOrFail();
        $this->assertSame('BRICE GANGOUE', $directeur->name);
        $this->assertTrue($directeur->hasRole('directeur'));
        $this->assertSame($ddsait->id, (int) $directeur->structure_id);
        $this->assertSame($directeur->id, $ddsait->titulaireValidationActuel()?->id);
    }

    public function test_seed_affecte_directeurs_ding_dinfra_dsupport_dcom(): void
    {
        $this->seed([
            RoleAndPermissionSeeder::class,
            StructureSeeder::class,
            ACSIFonctionsSeeder::class,
            CourrierReferentielSeeder::class,
            CircuitCourrierSeeder::class,
            CourrierActeursDgSeeder::class,
        ]);

        $attendus = [
            '003012y@acsi.cg' => 'DING-SI',
            '001966m@acsi.cg' => 'DINFRA',
            '001957c@acsi.cg' => 'DSUPPORT',
            '003330u@acsi.cg' => 'DCOM',
        ];

        foreach ($attendus as $email => $codeStructure) {
            $structure = Structure::where('code', $codeStructure)->firstOrFail();
            $directeur = User::where('email', $email)->firstOrFail();

            $this->assertTrue($directeur->hasRole('directeur'), $email);
            $this->assertSame($structure->id, (int) $directeur->structure_id, $email);
            $this->assertSame($directeur->id, $structure->titulaireValidationActuel()?->id, $email);
        }
    }

    public function test_circuit_seeder_ne_retire_pas_documents_et_dossiers(): void
    {
        $this->seed([
            RoleAndPermissionSeeder::class,
            CircuitCourrierSeeder::class,
        ]);

        $role = Role::findByName('particulier_dg', 'web');
        $this->assertTrue($role->hasPermissionTo('documents.view'));
        $this->assertTrue($role->hasPermissionTo('dossiers.view'));
        $this->assertTrue($role->hasPermissionTo('courriers.view'));

        $particulierAc = Role::findByName('particulier_ac', 'web');
        $this->assertTrue($particulierAc->hasPermissionTo('documents.view'));
        $this->assertTrue($particulierAc->hasPermissionTo('dossiers.view'));
        $this->assertTrue($particulierAc->hasPermissionTo('courriers.view'));
    }
}
