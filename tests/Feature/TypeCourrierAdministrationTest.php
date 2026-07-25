<?php

namespace Tests\Feature;

use App\Models\CircuitCourrier;
use App\Models\Courrier;
use App\Models\PrioriteCourrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\TypeCourrier;
use App\Models\User;
use Database\Seeders\CircuitCourrierSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TypeCourrierAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleAndPermissionSeeder::class,
            StructureSeeder::class,
            CourrierReferentielSeeder::class,
            CircuitCourrierSeeder::class,
        ]);
    }

    public function test_non_admin_ne_peut_pas_acceder_a_la_gestion_des_types(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('parametres.types-courriers.index', absolute: false))
            ->assertForbidden();
    }

    public function test_admin_peut_lister_les_types_avec_leur_circuit(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('parametres.types-courriers.index', absolute: false))
            ->assertOk()
            ->assertSee('Facture prestataire', false)
            ->assertSee('Factures prestataires', false);
    }

    public function test_admin_peut_associer_un_circuit_a_un_type_via_http(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $type = TypeCourrier::where('code', 'demande')->firstOrFail();
        $circuitFacture = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('parametres.types-courriers.update', $type, absolute: false), [
                'code' => $type->code,
                'libelle' => $type->libelle,
                'circuit_courrier_id' => $circuitFacture->id,
                'actif' => '1',
            ])
            ->assertRedirect(route('parametres.types-courriers.index', absolute: false));

        $this->assertSame($circuitFacture->id, $type->fresh()->circuit_courrier_id);
    }

    public function test_admin_peut_retirer_le_circuit_d_un_type(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $type = TypeCourrier::where('code', 'facture')->firstOrFail();
        $this->assertNotNull($type->circuit_courrier_id);

        $this->actingAs($admin)
            ->put(route('parametres.types-courriers.update', $type, absolute: false), [
                'code' => $type->code,
                'libelle' => $type->libelle,
                'circuit_courrier_id' => '',
                'actif' => '1',
            ])
            ->assertRedirect(route('parametres.types-courriers.index', absolute: false));

        $this->assertNull($type->fresh()->circuit_courrier_id);
    }

    public function test_creation_d_un_type_avec_circuit_via_http(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('parametres.types-courriers.store', absolute: false), [
                'code' => 'note_interne',
                'libelle' => 'Note interne',
                'circuit_courrier_id' => $circuit->id,
                'actif' => '1',
            ])
            ->assertRedirect(route('parametres.types-courriers.index', absolute: false));

        $this->assertDatabaseHas('type_courriers', [
            'code' => 'note_interne',
            'circuit_courrier_id' => $circuit->id,
        ]);
    }

    public function test_suppression_refusee_si_des_courriers_utilisent_le_type(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $type = TypeCourrier::where('code', 'facture')->firstOrFail();
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'recu')->firstOrFail();

        Courrier::create([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => $type->id,
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => PrioriteCourrier::where('code', 'normale')->value('id'),
            'numero_registre' => 1,
            'numero_registre_annee' => (int) now()->year,
            'origine' => Courrier::ORIGINE_EXTERNE,
            'date_reception' => now()->toDateString(),
            'expediteur_libelle' => 'Fournisseur test',
            'objet' => 'Test suppression type',
            'createur_id' => $admin->id,
            'structure_id' => $admin->structure_id,
        ]);

        $this->actingAs($admin)
            ->delete(route('parametres.types-courriers.destroy', $type, absolute: false))
            ->assertRedirect();

        $this->assertDatabaseHas('type_courriers', ['id' => $type->id]);
    }
}
