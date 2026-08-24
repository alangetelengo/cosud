<?php

namespace Tests\Feature;

use App\Models\CategorieDepense;
use App\Models\Structure;
use App\Models\SuiviPaiement;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Database\Seeders\TypeDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuiviDepensesEleniTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleAndPermissionSeeder::class,
            StructureSeeder::class,
            TypeDocumentSeeder::class,
        ]);
    }

    public function test_eleni_peut_enregistrer_une_depense_paie_remise_dg(): void
    {
        $eleni = $this->creerEleni();
        $catPaie = CategorieDepense::query()->where('code', CategorieDepense::CODE_PAIE)->firstOrFail();

        $this->actingAs($eleni)
            ->post(route('suivi-paiements.remise-dg', absolute: false), [
                'categorie_depense_id' => $catPaie->id,
                'date_suivi' => now()->toDateString(),
                'intitule' => 'Remboursement frais médicaux — AFCOM',
                'montant' => '185 000',
                'beneficiaire_libelle' => 'Agent X',
                'numero_piece' => 'PH-2026-12',
                'instruction_dg' => 'À suivre',
            ])
            ->assertRedirect(route('suivi-paiements.index', [
                'annee' => (int) now()->year,
            ], absolute: false))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('suivi_paiements', [
            'type' => SuiviPaiement::TYPE_FSP_PAIE,
            'categorie_depense_id' => $catPaie->id,
            'origine' => SuiviPaiement::ORIGINE_REMISE_DG,
            'intitule' => 'Remboursement frais médicaux — AFCOM',
            'beneficiaire_libelle' => 'Agent X',
            'montant' => 185000,
            'courrier_id' => null,
            'etabli_par_id' => $eleni->id,
        ]);
    }

    public function test_taty_ne_peut_pas_saisir_remise_dg(): void
    {
        $taty = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $taty->assignRole('responsable_dossiers_prestataires');
        $cat = CategorieDepense::query()->where('code', CategorieDepense::CODE_TTF)->firstOrFail();

        $this->actingAs($taty)
            ->post(route('suivi-paiements.remise-dg', absolute: false), [
                'categorie_depense_id' => $cat->id,
                'date_suivi' => now()->toDateString(),
                'intitule' => 'TTF interdite',
                'montant' => '1000',
            ])
            ->assertForbidden();
    }

    public function test_page_suivi_depense_unifiee_sans_onglets_paie(): void
    {
        $eleni = $this->creerEleni();

        $this->actingAs($eleni)
            ->get(route('suivi-paiements.index', absolute: false))
            ->assertOk()
            ->assertSee('Suivi de dépense', false)
            ->assertSee('Enregistrer une dépense', false)
            ->assertSee('Liste des dépenses', false)
            ->assertSee('Ex. : 185 000', false)
            ->assertDontSee('FSP FACTURE', false)
            ->assertDontSee('FSP MAD', false)
            ->assertDontSee('FSP COMMISSION', false)
            ->assertDontSee('COMMISSIONS', false);
    }

    public function test_export_hebdomadaire_consolide_les_categories(): void
    {
        $eleni = $this->creerEleni();
        $cat = CategorieDepense::query()->where('code', CategorieDepense::CODE_COMMISSION)->firstOrFail();

        SuiviPaiement::query()->create([
            'type' => SuiviPaiement::TYPE_FSP_COMMISSION,
            'categorie_depense_id' => $cat->id,
            'origine' => SuiviPaiement::ORIGINE_REMISE_DG,
            'numero_ligne' => 1,
            'numero_annee' => (int) now()->year,
            'date_suivi' => now()->toDateString(),
            'intitule' => 'Commission test',
            'montant' => 50000,
            'etabli_par_id' => $eleni->id,
        ]);

        $debut = now()->startOfWeek()->toDateString();
        $fin = now()->endOfWeek()->toDateString();

        $response = $this->actingAs($eleni)
            ->get(route('suivi-paiements.export-hebdomadaire', [
                'date_debut_hebdo' => $debut,
                'date_fin_hebdo' => $fin,
            ], absolute: false));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('Commission test', $content);
        $this->assertStringContainsString('Rapport hebdomadaire', $content);
    }

    private function creerEleni(): User
    {
        $user = User::factory()->create([
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
        ]);
        $user->assignRole('responsable_suivi_depenses');

        return $user;
    }
}
