<?php

namespace Tests\Feature;

use App\Models\Courrier;
use App\Models\Moratoire;
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

class FactureRegularisationProgrammeeTest extends TestCase
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

    public function test_taty_cree_facture_programmee_qui_alimente_la_dette(): void
    {
        Storage::fake('public');

        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $this->actingAs($taty)
            ->post(route('factures-regularisation.store', absolute: false), [
                'fournisseur_libelle' => 'AF.COM',
                'montant_facture' => '2500000',
                'paiement' => 'programmee',
                'mode_paiement' => 'cheque',
                'date_programmation' => '2026-09-01',
                'numero_piece' => 'CHQ-PROG-01',
                'banque' => 'BCH',
                'objet' => 'Facture programmée AF.COM',
                'fichiers' => [UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect(route('factures-regularisation.index', absolute: false));

        $courrier = Courrier::query()->where('est_regularisation', true)->first();
        $this->assertNotNull($courrier);
        $this->assertTrue($courrier->estRegularisationProgrammee());
        $this->assertSame('cheque', $courrier->regularisation_mode_paiement);
        $this->assertSame('CHQ-PROG-01', $courrier->regularisation_numero_piece);
        $this->assertSame('2026-09-01', $courrier->regularisation_date_programmation?->toDateString());
        $this->assertNull($courrier->suiviPaiement);

        $dette = app(FournisseurDetteService::class)->dettePourFournisseur('AF.COM');
        $this->assertNotNull($dette);
        $this->assertEquals(2_500_000.0, $dette['dette']);
    }

    public function test_eleni_enregistre_paiement_effectif_et_annule_la_dette(): void
    {
        Storage::fake('public');

        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $this->actingAs($taty)
            ->post(route('factures-regularisation.store', absolute: false), [
                'fournisseur_libelle' => 'AF.COM',
                'montant_facture' => '2500000',
                'paiement' => 'programmee',
                'mode_paiement' => 'ov',
                'date_programmation' => '2026-09-01',
                'numero_piece' => 'OV-9988',
                'fichiers' => [UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect();

        $courrier = Courrier::query()->where('est_regularisation', true)->firstOrFail();

        $this->actingAs($taty)
            ->get(route('factures-regularisation.payer', $courrier, absolute: false))
            ->assertForbidden();

        $this->actingAs($eleni)
            ->post(route('factures-regularisation.payer.store', $courrier, absolute: false), [
                'date_paiement' => '2026-09-15',
                'numero_piece' => 'OV-9988',
                'fichiers' => [UploadedFile::fake()->create('preuve.pdf', 80, 'application/pdf')],
            ])
            ->assertRedirect(route('factures-regularisation.index', absolute: false));

        $courrier->refresh();
        $this->assertTrue($courrier->estRegularisationPayee());

        $suivi = $courrier->suiviPaiement;
        $this->assertNotNull($suivi);
        $this->assertSame(SuiviPaiement::ORIGINE_REGULARISATION, $suivi->origine);
        $this->assertSame('2026-09-15', $suivi->date_decharge?->toDateString());
        $this->assertEquals(2_500_000.0, (float) $suivi->montant);
        $this->assertSame('OV-9988', $suivi->numero_piece);
        $this->assertSame((int) $courrier->fournisseur_prestataire_id, (int) $suivi->fournisseur_prestataire_id);
        $this->assertNotNull($suivi->fournisseur_prestataire_id);

        $dette = app(FournisseurDetteService::class)->dettePourFournisseur('AF.COM');
        $this->assertNotNull($dette);
        $this->assertEquals(2_500_000.0, $dette['montant_facture']);
        $this->assertEquals(2_500_000.0, $dette['montant_paye']);
        $this->assertEquals(0.0, $dette['dette']);
    }

    public function test_taty_ne_peut_plus_saisir_deja_payee(): void
    {
        Storage::fake('public');

        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $this->actingAs($taty)
            ->post(route('factures-regularisation.store', absolute: false), [
                'fournisseur_libelle' => 'AF.COM',
                'montant_facture' => '100000',
                'paiement' => 'payee',
                'numero_piece' => 'X',
                'date_paiement' => '2026-01-01',
                'fichiers' => [UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf')],
            ])
            ->assertSessionHasErrors('paiement');
    }

    public function test_second_paiement_effectif_est_refuse(): void
    {
        Storage::fake('public');

        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $this->actingAs($taty)
            ->post(route('factures-regularisation.store', absolute: false), [
                'fournisseur_libelle' => 'AF.COM',
                'montant_facture' => '1000000',
                'paiement' => 'programmee',
                'mode_paiement' => 'espece',
                'date_programmation' => '2026-09-01',
                'fichiers' => [UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect();

        $courrier = Courrier::query()->where('est_regularisation', true)->firstOrFail();

        $this->actingAs($eleni)
            ->post(route('factures-regularisation.payer.store', $courrier, absolute: false), [
                'date_paiement' => '2026-09-15',
            ])
            ->assertRedirect(route('factures-regularisation.index', absolute: false));

        $this->actingAs($eleni)
            ->post(route('factures-regularisation.payer.store', $courrier, absolute: false), [
                'date_paiement' => '2026-09-16',
            ])
            ->assertNotFound();

        $this->assertSame(1, SuiviPaiement::query()->where('courrier_id', $courrier->id)->count());
    }

    public function test_paiement_direct_refuse_si_moratoire_actif(): void
    {
        Storage::fake('public');

        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $this->actingAs($taty)
            ->post(route('factures-regularisation.store', absolute: false), [
                'fournisseur_libelle' => 'AF.COM',
                'montant_facture' => '2500000',
                'paiement' => 'programmee',
                'mode_paiement' => 'cheque',
                'date_programmation' => '2026-09-01',
                'numero_piece' => 'CHQ-MORATOIRE',
                'banque' => 'BCH',
                'fichiers' => [UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect();

        $courrier = Courrier::query()->where('est_regularisation', true)->firstOrFail();

        $this->actingAs($eleni)
            ->post(route('moratoires.store', absolute: false), [
                'fournisseur_libelle' => 'AF.COM',
                'montant_echeance_defaut' => '1500000',
                'fichiers' => [UploadedFile::fake()->create('instruction-dg.pdf', 20, 'application/pdf')],
            ])
            ->assertRedirect();

        $this->actingAs($eleni)
            ->get(route('factures-regularisation.payer', $courrier, absolute: false))
            ->assertForbidden();

        $this->actingAs($eleni)
            ->post(route('factures-regularisation.payer.store', $courrier, absolute: false), [
                'date_paiement' => '2026-09-15',
                'numero_piece' => 'CHQ-MORATOIRE',
            ])
            ->assertSessionHasErrors('date_paiement');

        $courrier->refresh();
        $this->assertTrue($courrier->estRegularisationProgrammee());
        $this->assertNull($courrier->suiviPaiement);
        $this->assertSame(0, SuiviPaiement::query()->where('courrier_id', $courrier->id)->count());
    }

    public function test_paiement_direct_refuse_si_moratoire_solde(): void
    {
        Storage::fake('public');

        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $this->actingAs($taty)
            ->post(route('factures-regularisation.store', absolute: false), [
                'fournisseur_libelle' => 'AF.COM',
                'montant_facture' => '500000',
                'paiement' => 'programmee',
                'mode_paiement' => 'espece',
                'date_programmation' => '2026-09-01',
                'fichiers' => [UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect();

        $courrier = Courrier::query()->where('est_regularisation', true)->firstOrFail();

        $this->actingAs($eleni)
            ->post(route('moratoires.store', absolute: false), [
                'fournisseur_libelle' => 'AF.COM',
                'montant_echeance_defaut' => '500000',
                'fichiers' => [UploadedFile::fake()->create('instruction-dg.pdf', 20, 'application/pdf')],
            ])
            ->assertRedirect();

        $moratoire = Moratoire::query()->firstOrFail();
        $moratoire->update(['statut' => Moratoire::STATUT_SOLDE]);

        $this->actingAs($eleni)
            ->get(route('factures-regularisation.payer', $courrier, absolute: false))
            ->assertForbidden();

        $this->actingAs($eleni)
            ->post(route('factures-regularisation.payer.store', $courrier, absolute: false), [
                'date_paiement' => '2026-09-15',
            ])
            ->assertSessionHasErrors('date_paiement');

        $this->assertSame(0, SuiviPaiement::query()->where('courrier_id', $courrier->id)->count());
    }
}
