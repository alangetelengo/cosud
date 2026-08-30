<?php

namespace Tests\Feature;

use App\Models\Courrier;
use App\Models\User;
use App\Services\FournisseurDetteService;
use Database\Seeders\CategorieDepenseSeeder;
use Database\Seeders\CircuitCourrierSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StatutDocumentSeeder;
use Database\Seeders\StructureSeeder;
use Database\Seeders\TypeDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FactureRegularisationTest extends TestCase
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
            TypeDocumentSeeder::class,
            StatutDocumentSeeder::class,
        ]);
    }

    public function test_regularisation_impayee_hors_circuit_alimente_la_dette(): void
    {
        Storage::fake('public');

        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $this->actingAs($taty)
            ->post(route('factures-regularisation.store', absolute: false), [
                'fournisseur_libelle' => 'ETABLISSEMENT JAY',
                'montant_facture' => '5 000 000',
                'paiement' => 'impayee',
                'objet' => 'Facture historique JAY 1',
                'fichiers' => [UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect(route('factures-regularisation.index', absolute: false));

        $courrier = Courrier::query()->where('est_regularisation', true)->first();
        $this->assertNotNull($courrier);
        $this->assertNull($courrier->circuit_courrier_id);
        $this->assertNull($courrier->circuit_etape_actuelle_id);
        $this->assertTrue($courrier->est_regularisation);
        $this->assertSame('impayee', $courrier->regularisation_paiement);
        $this->assertEquals(5_000_000.0, (float) $courrier->montant_facture);
        $this->assertSame('cloture', $courrier->statutCourrier?->code);
        $this->assertNull($courrier->suiviPaiement);

        $dette = app(FournisseurDetteService::class)->dettePourFournisseur('ETABLISSEMENT JAY');
        $this->assertNotNull($dette);
        $this->assertEquals(5_000_000.0, $dette['montant_facture']);
        $this->assertEquals(0.0, $dette['montant_paye']);
        $this->assertEquals(5_000_000.0, $dette['dette']);
    }

    public function test_contrat_mensuel_calcule_dette_initiale_en_une_ligne(): void
    {
        Storage::fake('public');

        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $this->actingAs($taty)
            ->post(route('factures-regularisation.store', absolute: false), [
                'fournisseur_libelle' => 'Chauffeur DG',
                'montant_mensuel_contrat' => '250000',
                'nb_mois_impayes' => 2,
                'paiement' => 'contrat_mensuel',
                'fichiers' => [UploadedFile::fake()->create('contrat.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect(route('factures-regularisation.index', absolute: false));

        $courrier = Courrier::query()->where('est_regularisation', true)->firstOrFail();
        $this->assertTrue($courrier->estRegularisationContratMensuel());
        $this->assertEquals(500_000.0, (float) $courrier->montant_facture);
        $this->assertEquals(250_000.0, (float) $courrier->regularisation_montant_mensuel);
        $this->assertSame(2, $courrier->regularisation_nb_mois_impayes);
        $this->assertStringContainsString('Contrat mensuel', $courrier->objet);

        $dette = app(FournisseurDetteService::class)->dettePourFournisseur('Chauffeur DG');
        $this->assertNotNull($dette);
        $this->assertEquals(500_000.0, $dette['dette']);
        $this->assertSame(1, $dette['nb_factures']);
    }

    public function test_formulaire_creation_expose_apercu_scans(): void
    {
        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $this->actingAs($taty)
            ->get(route('factures-regularisation.create', absolute: false))
            ->assertOk()
            ->assertSee('scansUploadPreview', false)
            ->assertSee('Choisir un ou plusieurs fichiers', false);
    }

    public function test_eleni_ne_peut_pas_creer_une_regularisation(): void
    {
        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $this->actingAs($eleni)
            ->get(route('factures-regularisation.create', absolute: false))
            ->assertForbidden();

        $this->actingAs($eleni)
            ->get(route('factures-regularisation.index', absolute: false))
            ->assertOk();
    }

    public function test_utilisateur_sans_permission_ne_peut_pas_regulariser(): void
    {
        $user = User::factory()->create();
        $user->assignRole('utilisateur');

        $this->actingAs($user)
            ->get(route('factures-regularisation.create', absolute: false))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('factures-regularisation.index', absolute: false))
            ->assertForbidden();
    }
}
