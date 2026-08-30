<?php

namespace Tests\Feature;

use App\Models\Courrier;
use App\Models\FournisseurPrestataire;
use App\Models\Moratoire;
use App\Models\User;
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

class FactureRegularisationModifierSupprimerTest extends TestCase
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

    public function test_taty_peut_passer_impayee_en_programmee(): void
    {
        Storage::fake('public');

        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $this->actingAs($taty)
            ->post(route('factures-regularisation.store', absolute: false), [
                'fournisseur_libelle' => 'SOMAC',
                'montant_facture' => '280000',
                'paiement' => 'impayee',
                'objet' => 'Gardiennage',
                'fichiers' => [UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect();

        $courrier = Courrier::query()->where('est_regularisation', true)->firstOrFail();
        $this->assertTrue($courrier->estRegularisationImpayee());

        $this->actingAs($taty)
            ->put(route('factures-regularisation.update', $courrier, absolute: false), [
                'fournisseur_libelle' => 'SOMAC',
                'montant_facture' => '280000',
                'paiement' => 'programmee',
                'mode_paiement' => 'cheque',
                'date_programmation' => '2026-09-10',
                'numero_piece' => 'CHQ-1',
                'banque' => 'BCH',
                'objet' => 'Gardiennage',
            ])
            ->assertRedirect(route('factures-regularisation.index', absolute: false));

        $courrier->refresh();
        $this->assertTrue($courrier->estRegularisationProgrammee());
        $this->assertSame('cheque', $courrier->regularisation_mode_paiement);
        $this->assertSame('CHQ-1', $courrier->regularisation_numero_piece);
    }

    public function test_modification_resync_fournisseur_prestataire_id(): void
    {
        Storage::fake('public');

        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $ancien = FournisseurPrestataire::factory()->create(['nom' => 'ANCIEN SARL']);
        $nouveau = FournisseurPrestataire::factory()->create(['nom' => 'NOUVEAU SARL']);

        $this->actingAs($taty)
            ->post(route('factures-regularisation.store', absolute: false), [
                'fournisseur_prestataire_id' => $ancien->id,
                'montant_facture' => '150000',
                'paiement' => 'impayee',
                'objet' => 'Facture à réaffecter',
                'fichiers' => [UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect();

        $courrier = Courrier::query()->where('est_regularisation', true)->firstOrFail();
        $this->assertSame($ancien->id, (int) $courrier->fournisseur_prestataire_id);

        $this->actingAs($taty)
            ->put(route('factures-regularisation.update', $courrier, absolute: false), [
                'fournisseur_prestataire_id' => $nouveau->id,
                'montant_facture' => '150000',
                'paiement' => 'impayee',
                'objet' => 'Facture à réaffecter',
            ])
            ->assertRedirect(route('factures-regularisation.index', absolute: false));

        $courrier->refresh();
        $this->assertSame($nouveau->id, (int) $courrier->fournisseur_prestataire_id);
        $this->assertSame('NOUVEAU SARL', $courrier->expediteur_libelle);
    }

    public function test_taty_peut_supprimer_une_regularisation_non_payee(): void
    {
        Storage::fake('public');

        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $this->actingAs($taty)
            ->post(route('factures-regularisation.store', absolute: false), [
                'fournisseur_libelle' => 'DS',
                'montant_facture' => '3500000',
                'paiement' => 'impayee',
                'fichiers' => [UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect();

        $courrier = Courrier::query()->where('est_regularisation', true)->firstOrFail();
        $id = $courrier->id;

        $this->actingAs($taty)
            ->delete(route('factures-regularisation.destroy', $courrier, absolute: false))
            ->assertRedirect(route('factures-regularisation.index', absolute: false));

        $this->assertDatabaseMissing('courriers', ['id' => $id]);
    }

    public function test_eleni_ne_peut_pas_modifier_ni_supprimer(): void
    {
        Storage::fake('public');

        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $this->actingAs($taty)
            ->post(route('factures-regularisation.store', absolute: false), [
                'fournisseur_libelle' => 'AF.COM',
                'montant_facture' => '100000',
                'paiement' => 'programmee',
                'mode_paiement' => 'espece',
                'date_programmation' => '2026-09-01',
                'fichiers' => [UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect();

        $courrier = Courrier::query()->where('est_regularisation', true)->firstOrFail();

        $this->actingAs($eleni)
            ->get(route('factures-regularisation.edit', $courrier, absolute: false))
            ->assertForbidden();

        $this->actingAs($eleni)
            ->delete(route('factures-regularisation.destroy', $courrier, absolute: false))
            ->assertForbidden();
    }

    public function test_facture_payee_ne_peut_plus_etre_modifiee(): void
    {
        Storage::fake('public');

        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $this->actingAs($taty)
            ->post(route('factures-regularisation.store', absolute: false), [
                'fournisseur_libelle' => 'AF.COM',
                'montant_facture' => '100000',
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
            ->assertRedirect();

        $this->actingAs($taty)
            ->get(route('factures-regularisation.edit', $courrier, absolute: false))
            ->assertForbidden();

        $this->actingAs($taty)
            ->delete(route('factures-regularisation.destroy', $courrier, absolute: false))
            ->assertForbidden();
    }

    public function test_modification_et_suppression_refusees_si_moratoire_actif(): void
    {
        Storage::fake('public');

        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $this->actingAs($taty)
            ->post(route('factures-regularisation.store', absolute: false), [
                'fournisseur_libelle' => 'SOMAC',
                'montant_facture' => '2800000',
                'paiement' => 'impayee',
                'fichiers' => [UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect();

        $courrier = Courrier::query()->where('est_regularisation', true)->firstOrFail();

        $this->actingAs($eleni)
            ->post(route('moratoires.store', absolute: false), [
                'fournisseur_libelle' => 'SOMAC',
                'montant_echeance_defaut' => '500000',
                'fichiers' => [UploadedFile::fake()->create('instruction-dg.pdf', 20, 'application/pdf')],
            ])
            ->assertRedirect();

        $this->actingAs($taty)
            ->get(route('factures-regularisation.edit', $courrier, absolute: false))
            ->assertForbidden();

        $this->actingAs($taty)
            ->put(route('factures-regularisation.update', $courrier, absolute: false), [
                'fournisseur_libelle' => 'SOMAC',
                'montant_facture' => '100000',
                'paiement' => 'impayee',
                'objet' => 'Tentative réduction dette',
            ])
            ->assertForbidden();

        $this->actingAs($taty)
            ->delete(route('factures-regularisation.destroy', $courrier, absolute: false))
            ->assertForbidden();

        $this->assertDatabaseHas('courriers', [
            'id' => $courrier->id,
            'montant_facture' => 2800000,
        ]);
    }

    public function test_modification_et_suppression_refusees_si_moratoire_solde(): void
    {
        Storage::fake('public');

        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $this->actingAs($taty)
            ->post(route('factures-regularisation.store', absolute: false), [
                'fournisseur_libelle' => 'SOMAC',
                'montant_facture' => '2800000',
                'paiement' => 'impayee',
                'fichiers' => [UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect();

        $courrier = Courrier::query()->where('est_regularisation', true)->firstOrFail();

        $this->actingAs($eleni)
            ->post(route('moratoires.store', absolute: false), [
                'fournisseur_libelle' => 'SOMAC',
                'montant_echeance_defaut' => '500000',
                'fichiers' => [UploadedFile::fake()->create('instruction-dg.pdf', 20, 'application/pdf')],
            ])
            ->assertRedirect();

        Moratoire::query()->firstOrFail()->update([
            'statut' => Moratoire::STATUT_SOLDE,
        ]);

        $this->actingAs($taty)
            ->get(route('factures-regularisation.edit', $courrier, absolute: false))
            ->assertForbidden();

        $this->actingAs($taty)
            ->delete(route('factures-regularisation.destroy', $courrier, absolute: false))
            ->assertForbidden();

        $this->assertDatabaseHas('courriers', ['id' => $courrier->id]);
    }
}
