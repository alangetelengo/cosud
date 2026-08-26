<?php

namespace Tests\Feature;

use App\Models\Courrier;
use App\Models\SuiviPaiement;
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

    public function test_regularisation_payee_cree_suivi_et_n_augmente_pas_la_dette(): void
    {
        Storage::fake('public');

        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $this->actingAs($eleni)
            ->post(route('factures-regularisation.store', absolute: false), [
                'fournisseur_libelle' => 'AF.COM',
                'montant_facture' => '2500000',
                'paiement' => 'payee',
                'numero_piece' => 'CHQ-HIST-01',
                'banque' => 'BCH',
                'date_paiement' => '2025-06-15',
                'fichiers' => [UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect(route('factures-regularisation.index', absolute: false));

        $courrier = Courrier::query()->where('est_regularisation', true)->first();
        $this->assertNotNull($courrier);
        $this->assertNull($courrier->circuit_courrier_id);
        $this->assertTrue($courrier->estRegularisationPayee());

        $suivi = $courrier->suiviPaiement;
        $this->assertNotNull($suivi);
        $this->assertSame(SuiviPaiement::ORIGINE_REGULARISATION, $suivi->origine);
        $this->assertNotNull($suivi->date_decharge);
        $this->assertEquals(2_500_000.0, (float) $suivi->montant);
        $this->assertNotNull($suivi->dossier_id);

        $this->actingAs($eleni)
            ->get(route('suivi-paiements.index', absolute: false))
            ->assertOk()
            ->assertSee('Régularisation', false)
            ->assertSee('AF.COM', false);

        $dette = app(FournisseurDetteService::class)->dettePourFournisseur('AF.COM');
        $this->assertNotNull($dette);
        $this->assertEquals(2_500_000.0, $dette['montant_facture']);
        $this->assertEquals(2_500_000.0, $dette['montant_paye']);
        $this->assertEquals(0.0, $dette['dette']);
    }

    public function test_utilisateur_sans_permission_ne_peut_pas_regulariser(): void
    {
        $user = User::factory()->create();
        $user->assignRole('utilisateur');

        $this->actingAs($user)
            ->get(route('factures-regularisation.create', absolute: false))
            ->assertForbidden();
    }
}
