<?php

namespace Tests\Feature;

use App\Models\CircuitCourrier;
use App\Models\Courrier;
use App\Models\PrioriteCourrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\Structure;
use App\Models\TypeCourrier;
use App\Models\User;
use App\Services\CircuitCourrierMoteurService;
use Database\Seeders\CircuitCourrierSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UtilisateurSuppressionAdminTest extends TestCase
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

    public function test_admin_peut_supprimer_un_autre_compte(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $cible = User::factory()->create();

        $this->actingAs($admin)
            ->delete(route('utilisateurs.destroy', $cible, absolute: false))
            ->assertRedirect(route('utilisateurs.index', absolute: false))
            ->assertSessionHas('success');

        $this->assertNull($cible->fresh());
    }

    public function test_admin_ne_peut_pas_supprimer_un_compte_qui_a_cree_des_courriers(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $cible = User::factory()->create([
            'structure_id' => Structure::query()->where('code', 'SEC-DIR')->value('id'),
        ]);
        $cible->assignRole('secretaire_direction');

        $courrier = $this->creerFacture($cible);
        $this->assertSame($cible->id, $courrier->createur_id);

        $this->actingAs($admin)
            ->from(route('utilisateurs.show', $cible, absolute: false))
            ->delete(route('utilisateurs.destroy', $cible, absolute: false))
            ->assertRedirect(route('utilisateurs.show', $cible, absolute: false))
            ->assertSessionHas('error');

        $this->assertNotNull($cible->fresh());
        $this->assertNotNull($courrier->fresh());
        $this->assertSame($cible->id, $courrier->fresh()->createur_id);
    }

    public function test_admin_ne_peut_pas_se_supprimer_lui_meme(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->delete(route('utilisateurs.destroy', $admin, absolute: false))
            ->assertForbidden();

        $this->assertNotNull($admin->fresh());
    }

    public function test_dg_ne_peut_pas_supprimer_un_compte(): void
    {
        $dg = User::factory()->create();
        $dg->assignRole('dg');

        $cible = User::factory()->create();

        $this->assertFalse($dg->can('delete', $cible));

        $this->actingAs($dg)
            ->delete(route('utilisateurs.destroy', $cible, absolute: false))
            ->assertForbidden();

        $this->assertNotNull($cible->fresh());
    }

    public function test_secretaire_ne_peut_pas_supprimer_un_compte(): void
    {
        $secretaire = User::factory()->create();
        $secretaire->assignRole('secretaire_direction');

        $cible = User::factory()->create();

        $this->actingAs($secretaire)
            ->delete(route('utilisateurs.destroy', $cible, absolute: false))
            ->assertForbidden();

        $this->assertNotNull($cible->fresh());
    }

    private function creerFacture(User $createur): Courrier
    {
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'recu')->firstOrFail();
        $type = TypeCourrier::where('code', 'facture')->firstOrFail();

        $courrier = Courrier::create([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => $type->id,
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => PrioriteCourrier::where('code', 'normale')->value('id'),
            'numero_registre' => 9001,
            'numero_registre_annee' => 2026,
            'numero_fulgurant' => '9001/DG',
            'origine' => Courrier::ORIGINE_EXTERNE,
            'date_reception' => now()->toDateString(),
            'expediteur_libelle' => 'FOURNISSEUR TEST SUPPRESSION',
            'objet' => 'Facture protection suppression user',
            'createur_id' => $createur->id,
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
            'montant_facture' => 100_000,
        ]);

        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();

        return app(CircuitCourrierMoteurService::class)->demarrer($courrier, $circuit, $createur);
    }
}
