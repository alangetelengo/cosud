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
use App\Notifications\CourrierWorkflowNotification;
use App\Services\CircuitCourrierMoteurService;
use App\Services\CourrierNotificationService;
use App\Services\FournisseurDetteService;
use App\Services\SuiviFacturesFournisseursService;
use Database\Seeders\CategorieDepenseSeeder;
use Database\Seeders\CircuitCourrierSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PayerReliquatFactureTest extends TestCase
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

    public function test_ac_peut_payer_et_cloturer_le_reliquat(): void
    {
        Storage::fake('public');
        Notification::fake();

        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');
        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $courrier = $this->factureAvecReliquat($ac, 2_500_000, 1_500_000);

        $this->actingAs($ac)
            ->get(route('courriers.show', $courrier, absolute: false))
            ->assertOk()
            ->assertSee('Payer le reliquat', false)
            ->assertSee('1 000 000', false);

        $this->actingAs($ac)
            ->post(route('courriers.circuit.payer-reliquat', $courrier, absolute: false), [
                'montant' => '1000000',
                'numero_piece' => 'CHQ-REL-001',
                'banque' => 'BICEC',
                'beneficiaire_libelle' => 'AF.COM',
                'date_decharge' => now()->toDateString(),
                'observation' => 'Solde du reliquat',
                'scans_cheque' => [UploadedFile::fake()->create('cheque-reliquat.pdf', 120, 'application/pdf')],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('suivi_paiements', 2);
        $this->assertDatabaseHas('suivi_paiements', [
            'courrier_id' => $courrier->id,
            'origine' => SuiviPaiement::ORIGINE_RELIQUAT,
            'montant' => 1_000_000,
            'numero_piece' => 'CHQ-REL-001',
        ]);

        $montants = app(SuiviFacturesFournisseursService::class)->montantsSurFacture($courrier->fresh());
        $this->assertEquals(2_500_000.0, $montants['montant_facture']);
        $this->assertEquals(2_500_000.0, $montants['montant_paye']);
        $this->assertEquals(0.0, $montants['reliquat']);
        $this->assertFalse($montants['a_reliquat']);

        $dette = app(FournisseurDetteService::class)->dettePourFournisseur('AF.COM');
        $this->assertEquals(0.0, $dette['dette']);

        Notification::assertSentTo(
            $eleni,
            CourrierWorkflowNotification::class,
            function (CourrierWorkflowNotification $notification) use ($courrier): bool {
                return $notification->type === CourrierNotificationService::ETAPE_CIRCUIT
                    && (int) $notification->courrier->id === (int) $courrier->id
                    && str_contains((string) $notification->detail, 'reliquat');
            }
        );
    }

    public function test_reliquat_bloque_si_moratoire_solde_pour_le_fournisseur(): void
    {
        Storage::fake('public');

        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');
        $courrier = $this->factureAvecReliquat($ac, 2_000_000, 1_200_000);

        Moratoire::query()->create([
            'fournisseur_libelle' => 'AF.COM',
            'fournisseur_normalise' => app(FournisseurDetteService::class)->normaliserLibelle('AF.COM'),
            'montant_dette_initial' => 800_000,
            'montant_echeance_defaut' => 200_000,
            'statut' => Moratoire::STATUT_SOLDE,
            'created_by' => $ac->id,
        ]);

        $this->actingAs($ac)
            ->get(route('courriers.show', $courrier, absolute: false))
            ->assertOk()
            ->assertDontSee('Payer le reliquat', false);

        $this->actingAs($ac)
            ->post(route('courriers.circuit.payer-reliquat', $courrier, absolute: false), [
                'montant' => '800000',
                'numero_piece' => 'CHQ-REL-SOLDE',
                'banque' => 'BICEC',
                'beneficiaire_libelle' => 'AF.COM',
                'date_decharge' => now()->toDateString(),
                'scans_cheque' => [UploadedFile::fake()->create('cheque.pdf', 80, 'application/pdf')],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('suivi_paiements', 1);
    }

    public function test_eleni_ne_peut_pas_payer_le_reliquat(): void
    {
        Storage::fake('public');

        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');
        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $courrier = $this->factureAvecReliquat($ac, 2_000_000, 1_200_000);

        $this->actingAs($eleni)
            ->post(route('courriers.circuit.payer-reliquat', $courrier, absolute: false), [
                'montant' => '800000',
                'numero_piece' => 'CHQ-REL-002',
                'banque' => 'SGBC',
                'beneficiaire_libelle' => 'AF.COM',
                'date_decharge' => now()->toDateString(),
                'scans_cheque' => [UploadedFile::fake()->create('cheque.pdf', 80, 'application/pdf')],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('suivi_paiements', 1);
    }

    public function test_montant_reliquat_ne_peut_pas_depasser_le_solde(): void
    {
        Storage::fake('public');

        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');
        $courrier = $this->factureAvecReliquat($ac, 2_000_000, 1_500_000);

        $this->actingAs($ac)
            ->from(route('courriers.show', $courrier, absolute: false))
            ->post(route('courriers.circuit.payer-reliquat', $courrier, absolute: false), [
                'montant' => '600000',
                'numero_piece' => 'CHQ-REL-003',
                'banque' => 'Afriland',
                'beneficiaire_libelle' => 'AF.COM',
                'date_decharge' => now()->toDateString(),
                'scans_cheque' => [UploadedFile::fake()->create('cheque.pdf', 80, 'application/pdf')],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('montant');

        $this->assertDatabaseCount('suivi_paiements', 1);
    }

    private function factureAvecReliquat(User $ac, float $montantFacture, float $montantPaye): Courrier
    {
        $dg = User::factory()->create(['structure_id' => Structure::where('code', 'DG')->value('id')]);
        $dg->assignRole('dg');

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->instruire($this->demarrerFacture($dg, 'Facture avec reliquat'), $dg, 'BPA.', $ac->id);
        $courrier->forceFill([
            'montant_facture' => $montantFacture,
            'circuit_etape_actuelle_id' => null,
        ])->save();

        $catFacture = CategorieDepense::query()->where('code', CategorieDepense::CODE_FACTURE)->firstOrFail();
        SuiviPaiement::query()->create([
            'courrier_id' => $courrier->id,
            'type' => SuiviPaiement::TYPE_FSP_FACTURE,
            'categorie_depense_id' => $catFacture->id,
            'origine' => SuiviPaiement::ORIGINE_CIRCUIT_CHEQUE,
            'numero_ligne' => 1,
            'numero_annee' => (int) now()->year,
            'date_suivi' => now()->toDateString(),
            'date_decharge' => now()->toDateString(),
            'controle_at' => now(),
            'intitule' => $courrier->objet,
            'montant' => $montantPaye,
            'fournisseur_libelle' => $courrier->expediteur_libelle,
            'beneficiaire_libelle' => $courrier->expediteur_libelle,
            'numero_piece' => 'CHQ-INIT-001',
            'banque' => 'BICEC',
            'etabli_par_id' => $ac->id,
        ]);

        return $courrier->fresh(['suiviPaiements', 'typeCourrier']);
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
