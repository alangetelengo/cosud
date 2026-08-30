<?php

namespace Tests\Feature;

use App\Models\Courrier;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\CircuitCourrierSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RepriseFacturesChecklistAoutSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RepriseFacturesChecklistAoutSeederTest extends TestCase
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

    protected function tearDown(): void
    {
        RepriseFacturesChecklistAoutSeeder::$cheminJson = null;
        parent::tearDown();
    }

    public function test_reformule_objet_retire_numero_facture_et_comptant(): void
    {
        $result = RepriseFacturesChecklistAoutSeeder::reformulerObjet(
            'Facture N°0188, relative à la location de la hyundai Starex comptant pour le mois de juillet 2026'
        );

        $this->assertSame('0188', $result['reference']);
        $this->assertSame(
            'Location de la hyundai Starex mois de juillet 2026',
            $result['objet']
        );
    }

    public function test_reformule_objet_facture_proforma_et_sans_relative(): void
    {
        $proforma = RepriseFacturesChecklistAoutSeeder::reformulerObjet(
            "Facture proforma N°S98805, relative à l'acquisition des fauteuil president chat-mix cuir noir"
        );
        $this->assertSame('S98805', $proforma['reference']);
        $this->assertSame(
            'Acquisition des fauteuil president chat-mix cuir noir',
            $proforma['objet']
        );

        $seul = RepriseFacturesChecklistAoutSeeder::reformulerObjet(
            'Facture N° FAC20260701-3PFXY-SPAPW'
        );
        $this->assertSame('FAC20260701-3PFXY-SPAPW', $seul['reference']);
        $this->assertSame('FAC20260701-3PFXY-SPAPW', $seul['objet']);
    }

    public function test_parser_numero_registre_accepte_1125dg_sans_slash(): void
    {
        $parsed = RepriseFacturesChecklistAoutSeeder::parserNumeroRegistre('1125DG');

        $this->assertSame(1125, $parsed['numero']);
        $this->assertSame(2026, $parsed['annee']);
        $this->assertSame('1125/DG', $parsed['complet']);
    }

    public function test_decomposer_telephones_separe_deux_numeros(): void
    {
        $this->assertSame(
            ['050333232', '044323232'],
            RepriseFacturesChecklistAoutSeeder::decomposerTelephones('050333232 / 044323232')
        );
        $this->assertSame(
            ['066856266', null],
            RepriseFacturesChecklistAoutSeeder::decomposerTelephones('066856266')
        );
        $this->assertSame(
            [null, null],
            RepriseFacturesChecklistAoutSeeder::decomposerTelephones(null)
        );
    }

    public function test_seeder_cree_facture_avec_numero_fulgurant_et_est_idempotent(): void
    {
        $taty = User::factory()->create([
            'email' => RepriseFacturesChecklistAoutSeeder::EMAIL_CREATEUR,
            'name' => 'ANNE LETHICIA TATY-TCHICAYA NÉE ND',
            'structure_id' => Structure::query()->where('code', 'SEC-DIR')->value('id'),
        ]);
        $taty->assignRole('responsable_dossiers_prestataires');

        $fixture = storage_path('framework/testing/checklist_reprise_aout_factures.fixture.json');
        File::ensureDirectoryExists(dirname($fixture));
        File::put($fixture, json_encode([
            [
                'numero_registre_complet' => '1003/DG',
                'date_reception' => '2026-08-03',
                'type' => 'facture',
                'expediteur_libelle' => 'EDITION LES SOZO',
                'objet_brut' => 'Facture N°0188, relative à la location de la hyundai Starex comptant pour le mois de juillet 2026',
                'montant' => 3120000,
                'telephone' => '050333232 / 044323232',
                'paye' => 'Non',
                'scan_ok' => 'Non',
                'statut_cosud' => '? saisir',
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        RepriseFacturesChecklistAoutSeeder::$cheminJson = $fixture;

        try {
            $this->seed(RepriseFacturesChecklistAoutSeeder::class);

            $courrier = Courrier::query()->where('numero_fulgurant', '1003/DG')->first();
            $this->assertNotNull($courrier);
            $this->assertSame('1003/DG', $courrier->numeroRegistreComplet());
            $this->assertSame(Courrier::ORIGINE_EXTERNE, $courrier->origine);
            $this->assertSame('0188', $courrier->reference);
            $this->assertSame(
                'Location de la hyundai Starex mois de juillet 2026',
                $courrier->objet
            );
            $this->assertSame('050333232', $courrier->expediteur_telephone);
            $this->assertSame('044323232', $courrier->expediteur_telephone_2);
            $this->assertSame(RepriseFacturesChecklistAoutSeeder::OBSERVATIONS, $courrier->observations);
            $this->assertSame($taty->id, $courrier->createur_id);
            $this->assertEquals(3120000.0, (float) $courrier->montant_facture);
            $this->assertNotNull($courrier->circuit_courrier_id);
            $this->assertSame(1, Courrier::query()->where('numero_fulgurant', '1003/DG')->count());

            $this->seed(RepriseFacturesChecklistAoutSeeder::class);
            $this->assertSame(1, Courrier::query()->where('numero_fulgurant', '1003/DG')->count());
            $this->assertSame($taty->id, Courrier::query()->where('numero_fulgurant', '1003/DG')->value('createur_id'));
            $this->assertSame('050333232', Courrier::query()->where('numero_fulgurant', '1003/DG')->value('expediteur_telephone'));
            $this->assertSame('044323232', Courrier::query()->where('numero_fulgurant', '1003/DG')->value('expediteur_telephone_2'));
        } finally {
            File::delete($fixture);
            RepriseFacturesChecklistAoutSeeder::$cheminJson = null;
        }
    }
}
