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
use App\Notifications\CourrierWorkflowNotification;
use App\Services\CircuitCourrierMoteurService;
use Database\Seeders\CircuitCourrierSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CourrierLectureEtAgentsConfiesTest extends TestCase
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

    public function test_instruire_peut_confier_a_plusieurs_destinataires_par_fonction(): void
    {
        Notification::fake();

        $dg = $this->creerDg();
        $directeur = User::factory()->create(['structure_id' => Structure::where('code', 'DAF')->value('id')]);
        $directeur->assignRole('directeur');
        $directeur2 = User::factory()->create(['structure_id' => Structure::where('code', 'DCOM')->value('id')]);
        $directeur2->assignRole('directeur');

        $courrier = $this->demarrerFacture($dg);
        $moteur = app(CircuitCourrierMoteurService::class);

        $courrier = $moteur->instruire(
            $courrier,
            $dg,
            'Traiter et informer les directions concernées.',
            null,
            [$directeur->id, $directeur2->id],
        );

        $this->assertEqualsCanonicalizing(
            [$directeur->id, $directeur2->id],
            $courrier->agentsConfies()->pluck('users.id')->all()
        );
        $this->assertContains($courrier->agent_confie_id, [$directeur->id, $directeur2->id]);
        $this->assertTrue($moteur->peutAgir($courrier, $directeur));
        $this->assertTrue($moteur->peutAgir($courrier, $directeur2));

        Notification::assertSentTo($directeur, CourrierWorkflowNotification::class);
        Notification::assertSentTo($directeur2, CourrierWorkflowNotification::class);

        $libelle = $moteur->libelleActeurPour($courrier, $courrier->circuitEtapeActuelle);
        $this->assertStringContainsString('Confié à —', $libelle);
        $this->assertStringContainsString($directeur->libelleDestinataireCourrier(), $libelle);
        $this->assertStringContainsString($directeur2->libelleDestinataireCourrier(), $libelle);
    }

    public function test_ouverture_courrier_marque_comme_lu_et_compteurs_onglets(): void
    {
        $dg = $this->creerDg();
        $courrier = $this->demarrerFacture($dg);

        $this->actingAs($dg)
            ->get(route('courriers.index', ['sens' => 'arrivee'], absolute: false))
            ->assertOk()
            ->assertSee('font-bold', false);

        $this->assertDatabaseMissing('courrier_lectures', [
            'courrier_id' => $courrier->id,
            'user_id' => $dg->id,
        ]);

        $this->actingAs($dg)
            ->get(route('courriers.show', $courrier, absolute: false))
            ->assertOk();

        $this->assertDatabaseHas('courrier_lectures', [
            'courrier_id' => $courrier->id,
            'user_id' => $dg->id,
        ]);

        $response = $this->actingAs($dg)
            ->get(route('courriers.index', ['sens' => 'arrivee'], absolute: false))
            ->assertOk();

        $compteurs = $response->viewData('compteursNonLus');
        $this->assertSame(0, $compteurs['arrivee']);
    }

    public function test_formulaire_instruction_accepte_agent_confie_ids(): void
    {
        $dg = $this->creerDg();
        $directeur = User::factory()->create();
        $directeur->assignRole('directeur');
        $courrier = $this->demarrerFacture($dg);

        $this->actingAs($dg)
            ->post(route('courriers.circuit.instruire', $courrier, absolute: false), [
                'instructions' => 'Diffuser aux directions.',
                'agent_confie_ids' => [$directeur->id],
            ])
            ->assertRedirect();

        $courrier->refresh();
        $this->assertTrue($courrier->agentsConfies()->where('users.id', $directeur->id)->exists());
    }

    public function test_instruction_refuse_destinataire_hors_directeur(): void
    {
        $dg = $this->creerDg();
        $chef = User::factory()->create();
        $chef->assignRole('chef_service');
        $courrier = $this->demarrerFacture($dg);

        $this->actingAs($dg)
            ->from(route('courriers.show', $courrier, absolute: false))
            ->post(route('courriers.circuit.instruire', $courrier, absolute: false), [
                'instructions' => 'Ne doit pas passer.',
                'agent_confie_ids' => [$chef->id],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('agent_confie_ids');

        $courrier->refresh();
        $this->assertTrue($courrier->agentsConfies()->doesntExist());
        $this->assertSame('instructions_dg', $courrier->circuitEtapeActuelle?->code);
    }

    public function test_liste_destinataires_instruction_ne_contient_que_directeurs(): void
    {
        $dg = $this->creerDg();
        $directeur = User::factory()->create(['name' => 'DIRECTEUR TEST LISTE']);
        $directeur->assignRole('directeur');
        $chef = User::factory()->create(['name' => 'CHEF TEST LISTE']);
        $chef->assignRole('chef_service');
        $ac = User::factory()->create(['name' => 'AC TEST LISTE']);
        $ac->assignRole('agent_comptable');

        $courrier = $this->demarrerFacture($dg);

        $html = $this->actingAs($dg)
            ->get(route('courriers.show', $courrier, absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('__agentsConfieSelect', $html);
        $this->assertMatchesRegularExpression('/"value"\s*:\s*"'.$directeur->id.'"/', $html);
        $this->assertDoesNotMatchRegularExpression('/"value"\s*:\s*"'.$chef->id.'"/', $html);
        $this->assertDoesNotMatchRegularExpression('/"value"\s*:\s*"'.$ac->id.'"/', $html);
    }

    private function creerDg(): User
    {
        $dg = User::factory()->create([
            'structure_id' => Structure::where('code', 'DG')->value('id'),
        ]);
        $dg->assignRole('dg');

        return $dg;
    }

    private function demarrerFacture(User $acteur): Courrier
    {
        $courrier = $this->creerCourrierArrivee($acteur, 'facture');
        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();

        return app(CircuitCourrierMoteurService::class)->demarrer($courrier, $circuit, $acteur);
    }

    private function creerCourrierArrivee(User $acteur, string $typeCode): Courrier
    {
        return Courrier::create([
            'sens_courrier_id' => SensCourrier::where('code', SensCourrier::ARRIVEE)->value('id'),
            'type_courrier_id' => TypeCourrier::where('code', $typeCode)->value('id'),
            'statut_courrier_id' => StatutCourrier::where('code', 'recu')->value('id'),
            'priorite_courrier_id' => PrioriteCourrier::query()->value('id'),
            'numero_registre' => random_int(1000, 9999),
            'numero_registre_annee' => (int) now()->format('Y'),
            'objet' => 'Test lecture / multi destinataires',
            'expediteur_libelle' => 'Prestataire test',
            'date_reception' => now()->toDateString(),
            'createur_id' => $acteur->id,
            'structure_id' => $acteur->structure_id,
        ]);
    }
}
