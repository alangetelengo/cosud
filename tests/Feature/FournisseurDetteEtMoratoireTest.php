<?php

namespace Tests\Feature;

use App\Models\CategorieDepense;
use App\Models\CircuitCourrier;
use App\Models\Courrier;
use App\Models\Moratoire;
use App\Models\PrioriteCourrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\Structure;
use App\Models\SuiviPaiement;
use App\Models\TypeCourrier;
use App\Models\User;
use App\Services\CircuitCourrierMoteurService;
use App\Services\FournisseurDetteService;
use App\Services\MoratoireService;
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

class FournisseurDetteEtMoratoireTest extends TestCase
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

    public function test_cumul_dette_par_fournisseur_facture_moins_paiements(): void
    {
        $dg = $this->creerDg();
        $this->creerFacture($dg, 'ETABLISSEMENT JAY', 10_000_000);
        $this->creerFacture($dg, 'etablissement jay', 7_989_516);

        $ac = User::factory()->create();
        $catFacture = CategorieDepense::query()->where('code', CategorieDepense::CODE_FACTURE)->firstOrFail();
        SuiviPaiement::query()->create([
            'type' => SuiviPaiement::TYPE_FSP_FACTURE,
            'categorie_depense_id' => $catFacture->id,
            'origine' => SuiviPaiement::ORIGINE_CIRCUIT_CHEQUE,
            'numero_ligne' => 1,
            'numero_annee' => 2026,
            'date_suivi' => now()->toDateString(),
            'date_decharge' => now()->toDateString(),
            'intitule' => 'Paiement partiel JAY',
            'montant' => 1_500_000,
            'fournisseur_libelle' => 'ETABLISSEMENT JAY',
            'etabli_par_id' => $ac->id,
        ]);

        $dette = app(FournisseurDetteService::class)->dettePourFournisseur('ETABLISSEMENT JAY');

        $this->assertNotNull($dette);
        $this->assertSame(2, $dette['nb_factures']);
        $this->assertEquals(17_989_516.0, $dette['montant_facture']);
        $this->assertEquals(1_500_000.0, $dette['montant_paye']);
        $this->assertEquals(16_489_516.0, $dette['dette']);
    }

    public function test_generation_echeancier_type_document_jay(): void
    {
        $lignes = app(MoratoireService::class)->genererLignesEcheancier(17_989_516, 1_500_000);

        $this->assertCount(12, $lignes);
        $this->assertEquals(17_989_516.0, $lignes[0]['montant_dette']);
        $this->assertEquals(1_500_000.0, $lignes[0]['montant_echeance']);
        $this->assertEquals(16_489_516.0, $lignes[0]['solde']);
        $this->assertEquals(1_489_516.0, $lignes[11]['montant_echeance']);
        $this->assertEquals(0.0, $lignes[11]['solde']);
        $this->assertEquals($lignes[10]['solde'], $lignes[11]['montant_dette']);
    }

    public function test_responsable_peut_creer_moratoire_et_saisir_cheque(): void
    {
        Storage::fake('public');

        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $this->actingAs($eleni)
            ->post(route('moratoires.store', absolute: false), [
                'fournisseur_libelle' => 'ETABLISSEMENT JAY',
                'montant_dette_initial' => '17 989 516',
                'montant_echeance_defaut' => '1 500 000',
                'lieu' => 'Brazzaville',
                'signataire_libelle' => $eleni->name,
            ])
            ->assertRedirect();

        $moratoire = Moratoire::query()->first();
        $this->assertNotNull($moratoire);
        $this->assertSame(12, $moratoire->echeances()->count());

        $echeance = $moratoire->echeances()->where('numero', 1)->first();
        $this->assertNotNull($echeance);

        $this->actingAs($eleni)
            ->get(route('moratoires.show', $moratoire, absolute: false))
            ->assertOk()
            ->assertSee('Saisir', false)
            ->assertSee('Échéance n° 1', false)
            ->assertSee('Enregistrer', false);

        $this->actingAs($eleni)
            ->patch(route('moratoires.echeances.update', [$moratoire, $echeance], absolute: false), [
                'numero_cheque' => 'CHQ-001',
                'banque' => 'BCH',
                'date_paiement' => now()->toDateString(),
                'observation' => '1er versement',
                'fichiers' => [UploadedFile::fake()->create('cheque.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect();

        $echeance->refresh();
        $this->assertSame('CHQ-001', $echeance->numero_cheque);
        $this->assertTrue($echeance->estPayee());
        $this->assertNotNull($echeance->suivi_paiement_id);

        $suivi = SuiviPaiement::query()->find($echeance->suivi_paiement_id);
        $this->assertNotNull($suivi);
        $this->assertSame(SuiviPaiement::ORIGINE_MORATOIRE, $suivi->origine);
        $this->assertEquals(1_500_000.0, (float) $suivi->montant);
        $this->assertNotNull($suivi->date_decharge);
        $this->assertNotNull($suivi->dossier_id);

        $this->actingAs($eleni)
            ->get(route('suivi-paiements.index', absolute: false))
            ->assertOk()
            ->assertSee('Moratoire', false)
            ->assertSee('ETABLISSEMENT JAY', false);

        $this->actingAs($eleni)
            ->get(route('moratoires.print', $moratoire, absolute: false))
            ->assertOk()
            ->assertSee('Aperçu PDF', false)
            ->assertSee('data:application/pdf;base64,', false);
    }

    public function test_suivi_factures_affiche_dettes_et_lien_moratoire(): void
    {
        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $this->creerFacture($dg, 'AF.COM', 2_500_000);
        $moteur->instruire($courrier, $dg, 'Bon pour accord.', $ac->id);

        $this->actingAs($taty)
            ->get(route('suivi-factures-fournisseurs.index', absolute: false))
            ->assertOk()
            ->assertSee('Dettes par fournisseur', false)
            ->assertSee('AF.COM', false)
            ->assertSee('2 500 000', false)
            ->assertSee('/moratoires', false);
    }

    private function creerDg(): User
    {
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'DG')->value('id')]);
        $user->assignRole('dg');

        return $user;
    }

    private function creerFacture(User $acteur, string $fournisseur, float $montant): Courrier
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
            'objet' => 'Facture '.$fournisseur,
            'montant_facture' => $montant,
            'expediteur_libelle' => $fournisseur,
            'createur_id' => $acteur->id,
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
            'service_demandeur_structure_id' => Structure::where('code', 'DAF')->value('id'),
        ]);

        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();

        return app(CircuitCourrierMoteurService::class)->demarrer($courrier, $circuit, $acteur);
    }
}
