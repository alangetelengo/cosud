<?php

namespace Tests\Feature;

use App\Models\Courrier;
use App\Models\PrioriteCourrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\Structure;
use App\Models\TypeCourrier;
use App\Models\User;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourrierPolicyUpdatePerimetreTest extends TestCase
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

    public function test_agent_comptable_hors_perimetre_ne_peut_pas_mettre_a_jour_ni_cloturer(): void
    {
        $secretaire = $this->creerSecretaire(Structure::where('code', 'SEC-DIR')->firstOrFail());
        $courrier = $this->creerCourrierArrivee($secretaire);

        $ac = User::factory()->create(['structure_id' => Structure::where('code', 'DAF')->value('id')]);
        $ac->assignRole('agent_comptable');

        $this->assertFalse($courrier->visiblePar($ac));
        $this->assertFalse($ac->can('update', $courrier));

        $this->actingAs($ac)
            ->post(route('courriers.parapheur', $courrier, absolute: false))
            ->assertForbidden();

        $this->actingAs($ac)
            ->post(route('courriers.cloturer', $courrier, absolute: false))
            ->assertForbidden();
    }

    public function test_secretaire_autre_structure_ne_peut_pas_mettre_a_jour(): void
    {
        $secretaireSecDir = $this->creerSecretaire(Structure::where('code', 'SEC-DIR')->firstOrFail());
        $courrier = $this->creerCourrierArrivee($secretaireSecDir);

        $autreStructure = Structure::where('code', '!=', 'SEC-DIR')
            ->where('code', 'like', 'SEC-%')
            ->first()
            ?? Structure::where('code', 'DAF')->firstOrFail();

        $secretaireAutre = $this->creerSecretaire($autreStructure);

        $this->assertFalse($courrier->visiblePar($secretaireAutre));
        $this->assertFalse($secretaireAutre->can('update', $courrier));
    }

    public function test_secretaire_createur_peut_mettre_a_jour(): void
    {
        $secretaire = $this->creerSecretaire(Structure::where('code', 'SEC-DIR')->firstOrFail());
        $courrier = $this->creerCourrierArrivee($secretaire);

        $this->assertTrue($courrier->visiblePar($secretaire));
        $this->assertTrue($secretaire->can('update', $courrier));
    }

    private function creerSecretaire(Structure $structure): User
    {
        $user = User::factory()->create(['structure_id' => $structure->id]);
        $user->assignRole('secretaire_direction');

        return $user;
    }

    private function creerCourrierArrivee(User $user): Courrier
    {
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'recu')->firstOrFail();

        return Courrier::create([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => TypeCourrier::where('code', 'administratif')->value('id'),
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => PrioriteCourrier::where('code', 'normale')->value('id'),
            'numero_registre' => random_int(1000, 9000),
            'numero_registre_annee' => (int) now()->year,
            'origine' => Courrier::ORIGINE_EXTERNE,
            'date_reception' => now()->toDateString(),
            'expediteur_libelle' => 'Expéditeur test',
            'objet' => 'Courrier périmètre update',
            'createur_id' => $user->id,
            'structure_id' => $user->structure_id,
        ]);
    }
}
