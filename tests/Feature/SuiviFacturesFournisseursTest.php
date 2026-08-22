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

class SuiviFacturesFournisseursTest extends TestCase
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

    public function test_responsable_dossiers_voit_la_page_suivi_factures(): void
    {
        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->instruire($this->demarrerFacture($dg, 'Facture AF.COM suivi Taty'), $dg, 'Bon pour accord.', $ac->id);

        $this->actingAs($taty)
            ->get(route('suivi-factures-fournisseurs.index', absolute: false))
            ->assertOk()
            ->assertSee('Suivi factures fournisseurs', false)
            ->assertSee('Facture AF.COM suivi Taty', false)
            ->assertSee('Rapport CSV (vendredi)', false)
            ->assertSee($courrier->expediteur_libelle ?? 'Fournisseur Test', false);
    }

    public function test_filtre_semaine_et_export_csv(): void
    {
        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        $moteur = app(CircuitCourrierMoteurService::class);
        $moteur->instruire($this->demarrerFacture($dg, 'Facture semaine'), $dg, 'BPA semaine.', $ac->id);

        $this->actingAs($taty)
            ->get(route('suivi-factures-fournisseurs.index', ['periode' => 'semaine'], absolute: false))
            ->assertOk()
            ->assertSee('Facture semaine', false)
            ->assertSee('Semaine en cours', false);

        $export = $this->actingAs($taty)
            ->get(route('suivi-factures-fournisseurs.export', ['periode' => 'semaine'], absolute: false));

        $export->assertOk();
        $content = $export->streamedContent();
        $this->assertStringContainsString('Fournisseur', $content);
        $this->assertStringContainsString('Statut paiement', $content);
        $this->assertStringContainsString('Facture semaine', $content);
    }

    public function test_agent_comptable_ne_peut_pas_acceder(): void
    {
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        $this->actingAs($ac)
            ->get(route('suivi-factures-fournisseurs.index', absolute: false))
            ->assertForbidden();
    }

    private function creerDg(): User
    {
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'DG')->value('id')]);
        $user->assignRole('dg');

        return $user;
    }

    private function demarrerFacture(User $acteur, string $objet): Courrier
    {
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'recu')->firstOrFail();
        $type = TypeCourrier::where('code', 'facture')->firstOrFail();

        $courrier = Courrier::create([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => $type->id,
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => PrioriteCourrier::where('code', 'normale')->value('id'),
            'numero_registre' => random_int(200, 900),
            'numero_registre_annee' => 2026,
            'origine' => 'externe',
            'date_reception' => now()->toDateString(),
            'objet' => $objet,
            'expediteur_libelle' => 'AF.COM',
            'createur_id' => $acteur->id,
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
            'service_demandeur_structure_id' => Structure::where('code', 'DAF')->value('id'),
        ]);

        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();

        return app(CircuitCourrierMoteurService::class)->demarrer($courrier, $circuit, $acteur);
    }
}
