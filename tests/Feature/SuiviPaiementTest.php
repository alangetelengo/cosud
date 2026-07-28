<?php

namespace Tests\Feature;

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

class SuiviPaiementTest extends TestCase
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

    public function test_responsable_suivi_depenses_peut_voir_la_liste_fsp_facture(): void
    {
        $suivi = User::factory()->create();
        $suivi->assignRole('responsable_suivi_depenses');

        $ligne = $this->creerLigneFspFacture();

        $this->actingAs($suivi)
            ->get(route('suivi-paiements.index', ['type' => SuiviPaiement::TYPE_FSP_FACTURE], absolute: false))
            ->assertOk()
            ->assertSee('FSP FACTURE', false)
            ->assertSee('Fiche de suivi des paiements facture', false)
            ->assertSee($ligne->intitule, false)
            ->assertSee('Exporter Excel (CSV)', false);
    }

    public function test_export_csv_fsp_facture_contient_les_colonnes_et_le_total(): void
    {
        $suivi = User::factory()->create();
        $suivi->assignRole('responsable_suivi_depenses');

        $this->creerLigneFspFacture(montant: 1949700);

        $response = $this->actingAs($suivi)
            ->get(route('suivi-paiements.export', [
                'type' => SuiviPaiement::TYPE_FSP_FACTURE,
                'annee' => 2026,
            ], absolute: false));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Fournisseur', $content);
        $this->assertStringContainsString('Service demandeur', $content);
        $this->assertStringContainsString('1 949 700', $content);
        $this->assertStringContainsString('Total', $content);
    }

    public function test_onglet_fsp_mad_affiche_les_colonnes_specifiques(): void
    {
        $suivi = User::factory()->create();
        $suivi->assignRole('responsable_suivi_depenses');

        $ac = User::factory()->create(['name' => 'RAÏSSA LEBANITOU']);
        $ac->assignRole('agent_comptable');
        $dg = $this->creerDg();

        $courrier = $this->creerCourrierArrivee($dg, 'mad');
        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();
        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->demarrer($courrier, $circuit, $dg);
        $courrier = $moteur->instruire($courrier, $dg, 'Bon pour accord.', $ac->id);
        $moteur->envoyerChequeAuDg($courrier, $ac, 'MAD établie.', 3000000);

        $this->actingAs($suivi)
            ->get(route('suivi-paiements.index', ['type' => SuiviPaiement::TYPE_FSP_MAD], absolute: false))
            ->assertOk()
            ->assertSee('FSP MAD', false)
            ->assertSee('Demandeur', false)
            ->assertSee('Responsable chargé du dossier', false)
            ->assertSee('RAÏSSA LEBANITOU', false)
            ->assertSee('3 000 000', false);
    }

    public function test_creer_depuis_entree_cheque_refuse_doublon_fsp(): void
    {
        $ligne = $this->creerLigneFspFacture();
        $courrier = Courrier::query()->findOrFail($ligne->courrier_id);
        $ac = User::query()->findOrFail($ligne->etabli_par_id);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Une fiche de suivi existe déjà pour ce courrier.');

        app(\App\Services\SuiviPaiementService::class)->creerDepuisEntreeCheque($courrier, $ac, 600000);

        $this->assertSame(1, SuiviPaiement::query()->where('courrier_id', $courrier->id)->count());
    }

    public function test_utilisateur_sans_permission_ne_peut_pas_acceder_au_suivi(): void
    {
        $user = User::factory()->create();
        $user->assignRole('utilisateur');

        $this->actingAs($user)
            ->get(route('suivi-paiements.index', absolute: false))
            ->assertForbidden();
    }

    private function creerLigneFspFacture(float $montant = 599294): SuiviPaiement
    {
        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        $courrier = $this->creerCourrierArrivee($dg, 'facture');
        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();
        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->demarrer($courrier, $circuit, $dg);
        $courrier = $moteur->instruire($courrier, $dg, 'Bon pour accord.', $ac->id);
        $moteur->envoyerChequeAuDg($courrier, $ac, 'Chèque établi.', $montant);

        return SuiviPaiement::query()->where('courrier_id', $courrier->id)->firstOrFail();
    }

    private function creerDg(): User
    {
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'DG')->value('id')]);
        $user->assignRole('dg');

        return $user;
    }

    private function creerCourrierArrivee(User $user, string $typeCode): Courrier
    {
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'recu')->firstOrFail();
        $type = TypeCourrier::where('code', $typeCode)->firstOrFail();

        return Courrier::create([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => $type->id,
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => PrioriteCourrier::where('code', 'normale')->value('id'),
            'numero_registre' => random_int(100, 999),
            'numero_registre_annee' => 2026,
            'origine' => 'externe',
            'date_reception' => now()->toDateString(),
            'objet' => 'Facture test FSP',
            'expediteur_libelle' => 'ETS KOMBO',
            'createur_id' => $user->id,
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
        ]);
    }
}
