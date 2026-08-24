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
use Database\Seeders\CircuitCourrierSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuiviDepenseUnifieTest extends TestCase
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

    public function test_tableau_unifie_affiche_circuit_et_saisie_avec_categorie(): void
    {
        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $catPaie = CategorieDepense::query()->where('code', CategorieDepense::CODE_PAIE)->firstOrFail();
        SuiviPaiement::query()->create([
            'type' => SuiviPaiement::TYPE_FSP_PAIE,
            'categorie_depense_id' => $catPaie->id,
            'origine' => SuiviPaiement::ORIGINE_REMISE_DG,
            'numero_ligne' => 1,
            'numero_annee' => (int) now()->year,
            'date_suivi' => now()->toDateString(),
            'intitule' => 'Saisie manuelle paie',
            'montant' => 10000,
            'etabli_par_id' => $eleni->id,
        ]);

        $this->creerChequeCircuit('Facture unifiée test');

        $this->actingAs($eleni)
            ->get(route('suivi-paiements.index', ['annee' => now()->year], absolute: false))
            ->assertOk()
            ->assertSee('Saisie manuelle paie', false)
            ->assertSee('Facture unifiée test', false)
            ->assertSee('Fiche de suivi paiement facture', false)
            ->assertSee('Paie', false);
    }

    public function test_filtre_par_categorie(): void
    {
        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $catPaie = CategorieDepense::query()->where('code', CategorieDepense::CODE_PAIE)->firstOrFail();
        $catTtf = CategorieDepense::query()->where('code', CategorieDepense::CODE_TTF)->firstOrFail();

        SuiviPaiement::query()->create([
            'type' => SuiviPaiement::TYPE_FSP_PAIE,
            'categorie_depense_id' => $catPaie->id,
            'origine' => SuiviPaiement::ORIGINE_REMISE_DG,
            'numero_ligne' => 1,
            'numero_annee' => (int) now()->year,
            'date_suivi' => now()->toDateString(),
            'intitule' => 'Ligne paie filtre',
            'montant' => 1000,
            'etabli_par_id' => $eleni->id,
        ]);
        SuiviPaiement::query()->create([
            'type' => SuiviPaiement::TYPE_FSP_TTF,
            'categorie_depense_id' => $catTtf->id,
            'origine' => SuiviPaiement::ORIGINE_REMISE_DG,
            'numero_ligne' => 1,
            'numero_annee' => (int) now()->year,
            'date_suivi' => now()->toDateString(),
            'intitule' => 'Ligne ttf filtre',
            'montant' => 2000,
            'etabli_par_id' => $eleni->id,
        ]);

        $this->actingAs($eleni)
            ->get(route('suivi-paiements.index', [
                'annee' => now()->year,
                'categorie_depense_id' => $catPaie->id,
            ], absolute: false))
            ->assertOk()
            ->assertSee('Ligne paie filtre', false)
            ->assertDontSee('Ligne ttf filtre', false);
    }

    public function test_lien_courrier_affiche_pour_eleni_si_fiche_suivi_existante(): void
    {
        $eleni = User::factory()->create([
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
        ]);
        $eleni->assignRole('responsable_suivi_depenses');

        $createur = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DAF')->value('id')]);
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'recu')->firstOrFail();
        $type = TypeCourrier::where('code', 'facture')->firstOrFail();
        $cat = CategorieDepense::query()->where('code', CategorieDepense::CODE_FACTURE)->firstOrFail();

        $courrier = Courrier::create([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => $type->id,
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => PrioriteCourrier::where('code', 'normale')->value('id'),
            'numero_registre' => random_int(200, 900),
            'numero_registre_annee' => 2026,
            'origine' => 'externe',
            'date_reception' => now()->toDateString(),
            'objet' => 'Facture avec fiche suivi Eleni',
            'expediteur_libelle' => 'SOMAC',
            'createur_id' => $createur->id,
            'structure_id' => Structure::where('code', 'SEC-DAF')->value('id'),
            'service_demandeur_structure_id' => Structure::where('code', 'DAF')->value('id'),
        ]);

        SuiviPaiement::query()->create([
            'courrier_id' => $courrier->id,
            'type' => SuiviPaiement::TYPE_FSP_FACTURE,
            'categorie_depense_id' => $cat->id,
            'origine' => SuiviPaiement::ORIGINE_CIRCUIT_CHEQUE,
            'numero_ligne' => 99,
            'numero_annee' => (int) now()->year,
            'date_suivi' => now()->toDateString(),
            'intitule' => 'Facture avec fiche suivi Eleni',
            'montant' => 1000,
            'numero_piece' => 'CHQ-SHOW',
            'etabli_par_id' => $createur->id,
        ]);

        $this->assertTrue($courrier->fresh()->visiblePar($eleni));
        $this->assertTrue($eleni->can('viewAny', SuiviPaiement::class));

        $this->actingAs($eleni)
            ->get(route('suivi-paiements.index', ['annee' => now()->year], absolute: false))
            ->assertOk()
            ->assertSee('Facture avec fiche suivi Eleni', false)
            ->assertSee(route('courriers.show', $courrier, absolute: false), false);
    }

    public function test_lien_courrier_affiche_si_utilisateur_autorise(): void
    {
        $dg = User::factory()->create(['structure_id' => Structure::where('code', 'DG')->value('id')]);
        $dg->assignRole('dg');
        $dg->givePermissionTo('suivi-paiements.view');

        $courrier = $this->creerChequeCircuit('Facture avec lien DG');

        $this->assertTrue($courrier->fresh()->visiblePar($dg));

        $this->actingAs($dg)
            ->get(route('suivi-paiements.index', ['annee' => now()->year], absolute: false))
            ->assertOk()
            ->assertSee('Facture avec lien DG', false)
            ->assertSee(route('courriers.show', $courrier, absolute: false), false);
    }

    private function creerChequeCircuit(string $objet): Courrier
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
            'numero_registre' => random_int(200, 900),
            'numero_registre_annee' => 2026,
            'origine' => 'externe',
            'date_reception' => now()->toDateString(),
            'objet' => $objet,
            'expediteur_libelle' => 'SOMAC',
            'createur_id' => $dg->id,
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
            'service_demandeur_structure_id' => Structure::where('code', 'DAF')->value('id'),
        ]);

        $moteur = app(CircuitCourrierMoteurService::class);
        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();
        $courrier = $moteur->demarrer($courrier, $circuit, $dg);
        $courrier = $moteur->instruire($courrier, $dg, 'BPA', $ac->id);
        $moteur->envoyerChequeAuDg($courrier, $ac, 'Chèque', 500000, [
            'numero_piece' => 'Chèque N° UNI-1',
            'banque' => 'BCH',
            'beneficiaire_libelle' => 'SOMAC',
        ]);

        return $courrier->fresh();
    }
}
