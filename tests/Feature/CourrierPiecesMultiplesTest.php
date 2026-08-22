<?php

namespace Tests\Feature;

use App\Models\CircuitCourrier;
use App\Models\Courrier;
use App\Models\PrioriteCourrier;
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
use Database\Seeders\TypeDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourrierPiecesMultiplesTest extends TestCase
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
        Storage::fake('public');
    }

    public function test_arrivee_accepte_plusieurs_scans(): void
    {
        $secretaire = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $secretaire->assignRole('secretaire_direction');

        $this->actingAs($secretaire)
            ->post(route('courriers.store', absolute: false), [
                'sens' => 'arrivee',
                'type_courrier_id' => TypeCourrier::where('code', 'facture')->value('id'),
                'objet' => 'Facture AF.COM multi-scans',
                'expediteur_libelle' => 'AF.COM',
                'date_reception' => now()->toDateString(),
                'service_demandeur_structure_id' => Structure::where('code', 'DAF')->value('id'),
                'fichiers' => [
                    UploadedFile::fake()->create('facture.pdf', 50, 'application/pdf'),
                    UploadedFile::fake()->create('bon-pour-accord.pdf', 30, 'application/pdf'),
                ],
            ])
            ->assertRedirect();

        $courrier = Courrier::query()->where('objet', 'Facture AF.COM multi-scans')->firstOrFail();
        $this->assertCount(2, $courrier->documents);
    }

    public function test_dg_peut_confirmer_signature_sans_scan(): void
    {
        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->instruire($this->demarrerFacture($dg), $dg, 'Bon pour accord.', $ac->id);
        $courrier = $moteur->envoyerChequeAuDg($courrier, $ac, 'Chèque prêt.', 379540);

        $this->actingAs($dg)
            ->post(route('courriers.circuit.signer-cheque', $courrier, absolute: false), [
                'notifier_fournisseur' => '0',
            ])
            ->assertRedirect();

        $courrier->refresh();
        $this->assertSame('preuve_paiement', $courrier->circuitEtapeActuelle->code);
        $this->assertSame(0, $courrier->documents()->count());
    }

    private function creerDg(): User
    {
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'DG')->value('id')]);
        $user->assignRole('dg');

        return $user;
    }

    private function demarrerFacture(User $acteur): Courrier
    {
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'recu')->firstOrFail();
        $type = TypeCourrier::where('code', 'facture')->firstOrFail();

        $courrier = Courrier::create([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => $type->id,
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => PrioriteCourrier::where('code', 'normale')->value('id'),
            'numero_registre' => 901,
            'numero_registre_annee' => 2026,
            'origine' => 'externe',
            'date_reception' => now()->toDateString(),
            'objet' => 'Facture test multi-pièces',
            'createur_id' => $acteur->id,
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
            'service_demandeur_structure_id' => Structure::where('code', 'DAF')->value('id'),
        ]);

        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();

        return app(CircuitCourrierMoteurService::class)->demarrer($courrier, $circuit, $acteur);
    }
}
