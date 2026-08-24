<?php

namespace Tests\Feature;

use App\Models\CircuitCourrier;
use App\Models\Courrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\Structure;
use App\Models\TypeCourrier;
use App\Models\User;
use App\Services\CircuitCourrierMoteurService;
use Database\Seeders\CircuitCourrierSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourrierVisibilitePerimetreFacturesDepensesTest extends TestCase
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

    public function test_taty_voit_seulement_les_factures(): void
    {
        $secDir = Structure::where('code', 'SEC-DIR')->firstOrFail();
        $taty = User::factory()->create(['structure_id' => $secDir->id]);
        $taty->assignRole('responsable_dossiers_prestataires');
        $createur = User::factory()->create(['structure_id' => $secDir->id]);

        $facture = $this->creerArrivee($secDir, $createur, 'facture', 'Facture périmètre Taty');
        $mad = $this->creerArrivee($secDir, $createur, 'mad', 'MAD hors périmètre Taty');
        $demande = $this->creerArrivee($secDir, $createur, 'demande', 'Demande hors périmètre Taty');

        $this->assertTrue($facture->visiblePar($taty));
        $this->assertFalse($mad->visiblePar($taty));
        $this->assertFalse($demande->visiblePar($taty));

        $ids = Courrier::query()->visibleBy($taty)->pluck('id');
        $this->assertTrue($ids->contains($facture->id));
        $this->assertFalse($ids->contains($mad->id));
        $this->assertFalse($ids->contains($demande->id));
    }

    public function test_eleni_voit_seulement_les_depenses_mad(): void
    {
        $secDir = Structure::where('code', 'SEC-DIR')->firstOrFail();
        $eleni = User::factory()->create(['structure_id' => $secDir->id]);
        $eleni->assignRole('responsable_suivi_depenses');
        $createur = User::factory()->create(['structure_id' => $secDir->id]);

        $facture = $this->creerArrivee($secDir, $createur, 'facture', 'Facture hors Eleni');
        $mad = $this->creerArrivee($secDir, $createur, 'mad', 'MAD périmètre Eleni');
        $demande = $this->creerArrivee($secDir, $createur, 'demande', 'Demande hors Eleni');

        $this->assertFalse($facture->visiblePar($eleni));
        $this->assertTrue($mad->visiblePar($eleni));
        $this->assertFalse($demande->visiblePar($eleni));

        $ids = Courrier::query()->visibleBy($eleni)->pluck('id');
        $this->assertFalse($ids->contains($facture->id));
        $this->assertTrue($ids->contains($mad->id));
        $this->assertFalse($ids->contains($demande->id));
    }

    public function test_eleni_voit_facture_sur_circuit_ou_elle_est_impliquee(): void
    {
        $secDir = Structure::where('code', 'SEC-DIR')->firstOrFail();
        $eleni = User::factory()->create(['structure_id' => $secDir->id]);
        $eleni->assignRole('responsable_suivi_depenses');
        $createur = User::factory()->create(['structure_id' => $secDir->id]);
        $createur->assignRole('admin');

        $facture = $this->creerArrivee($secDir, $createur, 'facture', 'Gardiennage des locaux ACSI');
        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();
        $facture = app(CircuitCourrierMoteurService::class)->demarrer($facture, $circuit, $createur);

        $this->assertTrue($facture->fresh()->visiblePar($eleni));
        $this->assertTrue(
            Courrier::query()->visibleBy($eleni)->whereKey($facture->id)->exists()
        );

        $this->actingAs($eleni)
            ->get(route('courriers.show', $facture, absolute: false))
            ->assertOk();
    }

    public function test_secretaire_sec_dir_voit_factures_et_mad_toutes_structures(): void
    {
        $secDir = Structure::where('code', 'SEC-DIR')->firstOrFail();
        $secDaf = Structure::where('code', 'SEC-DAF')->firstOrFail();

        $sec = User::factory()->create(['structure_id' => $secDir->id]);
        $sec->assignRole('secretaire_direction');

        $createurDaf = User::factory()->create(['structure_id' => $secDaf->id]);
        $factureDaf = $this->creerArrivee($secDaf, $createurDaf, 'facture', 'Facture DAF visible SEC-DIR');
        $madDaf = $this->creerArrivee($secDaf, $createurDaf, 'mad', 'MAD DAF visible SEC-DIR');
        $demandeDaf = $this->creerArrivee($secDaf, $createurDaf, 'demande', 'Demande DAF invisible SEC-DIR');

        $this->assertTrue($factureDaf->visiblePar($sec));
        $this->assertTrue($madDaf->visiblePar($sec));
        $this->assertFalse($demandeDaf->visiblePar($sec));

        $ids = Courrier::query()->visibleBy($sec)->pluck('id');
        $this->assertTrue($ids->contains($factureDaf->id));
        $this->assertTrue($ids->contains($madDaf->id));
        $this->assertFalse($ids->contains($demandeDaf->id));
    }

    public function test_secretaire_hors_dg_ne_voit_pas_facture_autre_structure(): void
    {
        $secDir = Structure::where('code', 'SEC-DIR')->firstOrFail();
        $secDaf = Structure::where('code', 'SEC-DAF')->firstOrFail();

        $ruth = User::factory()->create(['structure_id' => $secDaf->id]);
        $ruth->assignRole('secretaire_direction');

        $createurDir = User::factory()->create(['structure_id' => $secDir->id]);
        $factureDir = $this->creerArrivee($secDir, $createurDir, 'facture', 'Facture SEC-DIR hors DAF');

        $this->assertFalse($factureDir->visiblePar($ruth));
        $this->assertFalse(
            Courrier::query()->visibleBy($ruth)->whereKey($factureDir->id)->exists()
        );
    }

    public function test_eleni_n_accede_pas_au_menu_factures_fournisseurs(): void
    {
        $eleni = User::factory()->create([
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
        ]);
        $eleni->assignRole('responsable_suivi_depenses');

        $this->actingAs($eleni)
            ->get(route('suivi-factures-fournisseurs.index', absolute: false))
            ->assertForbidden();
    }

    private function creerArrivee(Structure $structure, User $createur, string $codeType, string $objet): Courrier
    {
        return Courrier::create([
            'sens_courrier_id' => SensCourrier::where('code', 'arrivee')->value('id'),
            'statut_courrier_id' => StatutCourrier::where('code', 'en_parapheur')->value('id'),
            'type_courrier_id' => TypeCourrier::where('code', $codeType)->value('id'),
            'numero_registre' => random_int(1, 99999),
            'numero_registre_annee' => (int) now()->year,
            'objet' => $objet,
            'expediteur_libelle' => 'Fournisseur Test',
            'origine' => Courrier::ORIGINE_EXTERNE,
            'createur_id' => $createur->id,
            'structure_id' => $structure->id,
            'date_reception' => now()->toDateString(),
        ]);
    }
}
