<?php

namespace Tests\Feature;

use App\Models\CategorieDepense;
use App\Models\CircuitCourrier;
use App\Models\Courrier;
use App\Models\PrioriteCourrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\Structure;
use App\Models\SuiviPaiement;
use App\Models\TypeCourrier;
use App\Models\User;
use App\Services\CircuitCourrierMoteurService;
use Database\Seeders\CategorieDepenseSeeder;
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
            CategorieDepenseSeeder::class,
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
            ->assertSee('Suivi factures fournisseurs et Prestataires', false)
            ->assertSee('Facture AF.COM suivi Taty', false)
            ->assertSee('CSV semaine', false)
            ->assertSee('CSV mensuel', false)
            ->assertSee('CSV annuel', false)
            ->assertSee('Imprimer', false)
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

    public function test_export_mensuel_annuel_et_impression_pdf(): void
    {
        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        $moteur = app(CircuitCourrierMoteurService::class);
        $moteur->instruire($this->demarrerFacture($dg, 'Facture mois annee'), $dg, 'BPA.', $ac->id);

        $mois = now()->format('Y-m');
        $annee = (int) now()->year;

        $this->actingAs($taty)
            ->get(route('suivi-factures-fournisseurs.export', ['periode' => 'mois', 'mois' => $mois], absolute: false))
            ->assertOk();

        $this->actingAs($taty)
            ->get(route('suivi-factures-fournisseurs.export', ['periode' => 'annee', 'annee' => $annee], absolute: false))
            ->assertOk();

        $this->actingAs($taty)
            ->get(route('suivi-factures-fournisseurs.print', ['periode' => 'mois', 'mois' => $mois], absolute: false))
            ->assertOk()
            ->assertSee('Aperçu PDF', false)
            ->assertSee('data:application/pdf;base64,', false)
            ->assertSee('Retour à la liste', false);
    }

    public function test_filtre_toutes_periodes_ne_restreint_pas_par_annee(): void
    {
        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        $moteur = app(CircuitCourrierMoteurService::class);
        $moteur->instruire($this->demarrerFacture($dg, 'Facture annee courante'), $dg, 'BPA.', $ac->id);
        $courrierAnneePrecedente = $moteur->instruire($this->demarrerFacture($dg, 'Facture annee precedente'), $dg, 'BPA.', $ac->id);
        $courrierAnneePrecedente->forceFill([
            'date_orientation' => now()->subYear()->startOfYear()->addDays(10),
        ])->save();

        $this->actingAs($taty)
            ->get(route('suivi-factures-fournisseurs.index', ['periode' => 'tous'], absolute: false))
            ->assertOk()
            ->assertSee('Toutes périodes', false)
            ->assertSee('Facture annee courante', false)
            ->assertSee('Facture annee precedente', false);

        $this->actingAs($taty)
            ->get(route('suivi-factures-fournisseurs.index', [
                'periode' => 'annee',
                'annee' => (int) now()->year,
            ], absolute: false))
            ->assertOk()
            ->assertSee('Facture annee courante', false)
            ->assertDontSee('Facture annee precedente', false);
    }

    public function test_export_csv_privilegie_montant_facture_sur_suivi(): void
    {
        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->instruire($this->demarrerFacture($dg, 'Facture montant csv'), $dg, 'BPA.', $ac->id);
        $courrier->forceFill(['montant_facture' => 3_200_000])->save();

        $catFacture = CategorieDepense::query()->where('code', CategorieDepense::CODE_FACTURE)->firstOrFail();
        SuiviPaiement::query()->create([
            'courrier_id' => $courrier->id,
            'type' => SuiviPaiement::TYPE_FSP_FACTURE,
            'categorie_depense_id' => $catFacture->id,
            'origine' => SuiviPaiement::ORIGINE_CIRCUIT_CHEQUE,
            'numero_ligne' => 1,
            'numero_annee' => (int) now()->year,
            'date_suivi' => now()->toDateString(),
            'intitule' => $courrier->objet,
            'montant' => 1_000_000,
            'fournisseur_libelle' => $courrier->expediteur_libelle,
            'etabli_par_id' => $ac->id,
        ]);

        $content = $this->actingAs($taty)
            ->get(route('suivi-factures-fournisseurs.export', ['periode' => 'tous'], absolute: false))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('3 200 000', $content);
        $this->assertStringNotContainsString('1 000 000', $content);
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
