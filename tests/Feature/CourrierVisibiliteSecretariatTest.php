<?php

namespace Tests\Feature;

use App\Models\Courrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourrierVisibiliteSecretariatTest extends TestCase
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

    public function test_chaque_secretariat_ne_voit_que_son_registre(): void
    {
        $secDir = Structure::where('code', 'SEC-DIR')->firstOrFail();
        $secDaf = Structure::where('code', 'SEC-DAF')->firstOrFail();

        $lucienne = User::factory()->create(['structure_id' => $secDir->id]);
        $lucienne->assignRole('particulier_dg');

        $ruth = User::factory()->create(['structure_id' => $secDaf->id]);
        $ruth->assignRole('secretaire_direction');

        $depart = Courrier::create([
            'sens_courrier_id' => SensCourrier::where('code', 'depart')->value('id'),
            'statut_courrier_id' => StatutCourrier::where('code', 'archive')->value('id'),
            'numero_registre' => 1,
            'numero_registre_annee' => (int) now()->year,
            'objet' => 'Lettre au Prefet — départ SEC-DIR',
            'destinataire_libelle' => $secDaf->nom,
            'structure_destinataire_id' => $secDaf->id,
            'createur_id' => $lucienne->id,
            'structure_id' => $secDir->id,
            'date_expedition' => now(),
        ]);

        $arrivee = Courrier::create([
            'sens_courrier_id' => SensCourrier::where('code', 'arrivee')->value('id'),
            'statut_courrier_id' => StatutCourrier::where('code', 'en_parapheur')->value('id'),
            'numero_registre' => 1,
            'numero_registre_annee' => (int) now()->year,
            'objet' => 'Lettre au Prefet — arrivée SEC-DAF',
            'expediteur_libelle' => $secDir->nom,
            'origine' => Courrier::ORIGINE_INTERNE,
            'courrier_depart_source_id' => $depart->id,
            'createur_id' => $ruth->id,
            'structure_id' => $secDaf->id,
            'date_reception' => now()->toDateString(),
        ]);

        $depart->update(['courrier_arrivee_lie_id' => $arrivee->id]);

        $this->assertTrue($depart->visiblePar($lucienne));
        $this->assertFalse($arrivee->visiblePar($lucienne));

        $this->assertTrue($arrivee->visiblePar($ruth));
        $this->assertFalse($depart->visiblePar($ruth));

        $this->actingAs($lucienne)
            ->get(route('courriers.index', ['sens' => 'depart'], absolute: false))
            ->assertOk()
            ->assertSee('Lettre au Prefet — départ SEC-DIR', false)
            ->assertDontSee('Lettre au Prefet — arrivée SEC-DAF', false);

        $this->actingAs($lucienne)
            ->get(route('courriers.index', ['sens' => 'arrivee'], absolute: false))
            ->assertOk()
            ->assertDontSee('Lettre au Prefet — arrivée SEC-DAF', false);

        $this->actingAs($ruth)
            ->get(route('courriers.index', ['sens' => 'arrivee'], absolute: false))
            ->assertOk()
            ->assertSee('Lettre au Prefet — arrivée SEC-DAF', false)
            ->assertDontSee('Lettre au Prefet — départ SEC-DIR', false);

        $this->actingAs($ruth)
            ->get(route('courriers.index', ['sens' => 'depart'], absolute: false))
            ->assertOk()
            ->assertDontSee('Lettre au Prefet — départ SEC-DIR', false);

        $this->actingAs($lucienne)
            ->get(route('courriers.show', $arrivee, absolute: false))
            ->assertForbidden();

        $this->actingAs($ruth)
            ->get(route('courriers.show', $depart, absolute: false))
            ->assertForbidden();
    }

    public function test_secretariat_destinataire_voit_le_depart_en_attente_de_reception(): void
    {
        $secDir = Structure::where('code', 'SEC-DIR')->firstOrFail();
        $secDaf = Structure::where('code', 'SEC-DAF')->firstOrFail();

        $emetteur = User::factory()->create(['structure_id' => $secDir->id]);
        $emetteur->assignRole('secretaire_direction');

        $ruth = User::factory()->create(['structure_id' => $secDaf->id]);
        $ruth->assignRole('secretaire_direction');

        $depart = Courrier::create([
            'sens_courrier_id' => SensCourrier::where('code', 'depart')->value('id'),
            'statut_courrier_id' => StatutCourrier::where('code', 'expedie')->value('id'),
            'numero_registre' => 2,
            'numero_registre_annee' => (int) now()->year,
            'objet' => 'Départ en attente DAF',
            'destinataire_libelle' => $secDaf->nom,
            'structure_destinataire_id' => $secDaf->id,
            'createur_id' => $emetteur->id,
            'structure_id' => $secDir->id,
            'date_expedition' => now(),
        ]);

        $this->assertTrue($depart->visiblePar($ruth));
        $this->assertTrue($depart->visiblePar($emetteur));

        // Fiche consultable pour réception, mais hors registre / liste Départ du destinataire.
        $this->assertFalse(
            Courrier::query()->visibleBy($ruth)->whereKey($depart->id)->exists()
        );

        $this->actingAs($ruth)
            ->get(route('courriers.index', ['sens' => 'depart'], absolute: false))
            ->assertOk()
            ->assertDontSee('Départ en attente DAF', false);

        $this->actingAs($ruth)
            ->get(route('courriers.registres.depart', ['annee' => $depart->numero_registre_annee], absolute: false))
            ->assertOk()
            ->assertDontSee('Départ en attente DAF', false);

        $this->actingAs($ruth)
            ->get(route('courriers.a-recevoir', absolute: false))
            ->assertOk()
            ->assertSee('Départ en attente DAF', false);

        $this->actingAs($ruth)
            ->get(route('courriers.show', $depart, absolute: false))
            ->assertOk();
    }
}
