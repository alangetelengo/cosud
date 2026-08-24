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
use App\Services\SuiviPaiementService;
use Database\Seeders\CircuitCourrierSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Database\Seeders\TypeDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuiviPaiementBugbotFixesTest extends TestCase
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
            TypeDocumentSeeder::class,
        ]);
    }

    public function test_deux_categories_personnalisees_peuvent_avoir_le_numero_1(): void
    {
        $eleni = $this->creerEleni();
        $service = app(SuiviPaiementService::class);

        $catA = CategorieDepense::query()->create([
            'code' => 'frais_mission',
            'libelle' => 'Frais de mission',
            'ordre' => 100,
            'actif' => true,
            'est_systeme' => false,
        ]);
        $catB = CategorieDepense::query()->create([
            'code' => 'loyers',
            'libelle' => 'Loyers',
            'ordre' => 110,
            'actif' => true,
            'est_systeme' => false,
        ]);

        $ligneA = $service->creerRemiseDg($eleni, [
            'categorie_depense_id' => $catA->id,
            'date_suivi' => '2026-03-10',
            'intitule' => 'Mission A',
            'montant' => 1000,
        ]);
        $ligneB = $service->creerRemiseDg($eleni, [
            'categorie_depense_id' => $catB->id,
            'date_suivi' => '2026-03-11',
            'intitule' => 'Loyer B',
            'montant' => 2000,
        ]);

        $this->assertSame(SuiviPaiement::TYPE_FSP_MANUEL, $ligneA->type);
        $this->assertSame(SuiviPaiement::TYPE_FSP_MANUEL, $ligneB->type);
        $this->assertSame(1, $ligneA->numero_ligne);
        $this->assertSame(1, $ligneB->numero_ligne);
        $this->assertSame(2026, $ligneA->numero_annee);
        $this->assertSame(2026, $ligneB->numero_annee);
    }

    public function test_numero_annee_suit_la_date_suivi_saisie(): void
    {
        $eleni = $this->creerEleni();
        $catPaie = CategorieDepense::query()->where('code', CategorieDepense::CODE_PAIE)->firstOrFail();

        $ligne = app(SuiviPaiementService::class)->creerRemiseDg($eleni, [
            'categorie_depense_id' => $catPaie->id,
            'date_suivi' => '2025-12-15',
            'intitule' => 'Paie décembre 2025',
            'montant' => 50000,
        ]);

        $this->assertSame(2025, $ligne->numero_annee);
        $this->assertSame('2025-12-15', $ligne->date_suivi->toDateString());

        $this->actingAs($eleni)
            ->get(route('suivi-paiements.index', ['annee' => 2025], absolute: false))
            ->assertOk()
            ->assertSee('Paie décembre 2025', false);

        $this->actingAs($eleni)
            ->get(route('suivi-paiements.index', ['annee' => 2026], absolute: false))
            ->assertOk()
            ->assertDontSee('Paie décembre 2025', false);
    }

    public function test_decharge_ne_modifie_pas_date_suivi_du_bordereau(): void
    {
        $dg = User::factory()->create(['structure_id' => Structure::where('code', 'DG')->value('id')]);
        $dg->assignRole('dg');
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'recu')->firstOrFail();
        $type = TypeCourrier::where('code', 'facture')->firstOrFail();

        $courrier = Courrier::create([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => $type->id,
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => PrioriteCourrier::where('code', 'normale')->value('id'),
            'numero_registre' => random_int(100, 999),
            'numero_registre_annee' => 2026,
            'origine' => 'externe',
            'date_reception' => now()->toDateString(),
            'objet' => 'Facture décharge date_suivi',
            'expediteur_libelle' => 'ETS TEST',
            'createur_id' => $dg->id,
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
            'service_demandeur_structure_id' => Structure::where('code', 'DAF')->value('id'),
        ]);

        $moteur = app(CircuitCourrierMoteurService::class);
        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();
        $courrier = $moteur->demarrer($courrier, $circuit, $dg);
        $courrier = $moteur->instruire($courrier, $dg, 'BPA', $ac->id);
        $courrier = $moteur->envoyerChequeAuDg($courrier, $ac, 'Chèque', 100000, [
            'numero_piece' => 'Chèque N° FIX-1',
            'banque' => 'BCH',
            'beneficiaire_libelle' => 'Bénéficiaire',
        ]);

        $suiviAvant = SuiviPaiement::query()->where('courrier_id', $courrier->id)->firstOrFail();
        $dateSuiviInitiale = $suiviAvant->date_suivi->toDateString();

        $courrier = $moteur->signerChequeDg($courrier, $dg, 'Signé.', false);
        $moteur->enregistrerDechargeAc($courrier, $ac, [
            'date_decharge' => '2026-01-05',
        ], 'Décharge');

        $suiviApres = $suiviAvant->fresh();
        $this->assertSame($dateSuiviInitiale, $suiviApres->date_suivi->toDateString());
        $this->assertSame('2026-01-05', $suiviApres->date_decharge->toDateString());
        $this->assertSame('Chèque N° FIX-1', $suiviApres->numero_piece);
        $this->assertSame('BCH', $suiviApres->banque);
        $this->assertEquals(100000, (float) $suiviApres->montant);
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
