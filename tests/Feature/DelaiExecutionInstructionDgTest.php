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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelaiExecutionInstructionDgTest extends TestCase
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

    public function test_instruction_facture_peut_definir_un_delai_en_jours(): void
    {
        $dg = $this->creerDg();
        $courrier = $this->demarrerCircuit($dg, 'facture', 'facture_prestataire');

        $this->actingAs($dg)
            ->post(route('courriers.circuit.instruire', $courrier, absolute: false), [
                'instructions' => 'Bon pour accord — traiter sous 5 jours.',
                'delai_execution_jours' => 5,
                'mode_paiement_circuit' => Courrier::MODE_PAIEMENT_CHEQUE,
            ])
            ->assertRedirect();

        $courrier->refresh();
        $this->assertSame(5, $courrier->delai_execution_jours);
        $this->assertNotNull($courrier->dateEcheanceExecution());
        $this->assertSame(
            $courrier->date_orientation->copy()->startOfDay()->addDays(5)->toDateString(),
            $courrier->dateEcheanceExecution()->toDateString()
        );
        $this->assertStringContainsString('5 jours', (string) $courrier->libelleDelaiExecution());
    }

    public function test_instruction_circuit_general_accepte_delai_facultatif(): void
    {
        $dg = $this->creerDg();
        $courrier = $this->demarrerCircuit($dg, 'administratif', 'courrier_general');

        $this->actingAs($dg)
            ->post(route('courriers.circuit.instruire', $courrier, absolute: false), [
                'instructions' => 'Préparer une note de réponse.',
            ])
            ->assertRedirect();

        $this->assertNull($courrier->fresh()->delai_execution_jours);

        $courrier = $this->demarrerCircuit($dg, 'administratif', 'courrier_general');
        $this->actingAs($dg)
            ->post(route('courriers.circuit.instruire', $courrier, absolute: false), [
                'instructions' => 'Réponse sous 10 jours.',
                'delai_execution_jours' => 10,
            ])
            ->assertRedirect();

        $this->assertSame(10, $courrier->fresh()->delai_execution_jours);
    }

    public function test_ac_ne_peut_pas_modifier_le_beneficiaire_si_fournisseur_connu(): void
    {
        $dg = $this->creerDg();
        $ac = User::factory()->create(['structure_id' => Structure::where('code', 'DAF')->value('id')]);
        $ac->assignRole('agent_comptable');

        $courrier = $this->demarrerCircuit($dg, 'facture', 'facture_prestataire', 'NETPLUS SARL');
        app(CircuitCourrierMoteurService::class)->instruire($courrier, $dg, 'Établir le chèque.');

        $courrier->refresh();
        $this->assertSame('ac_etablit_cheque', $courrier->circuitEtapeActuelle->code);

        $this->actingAs($ac)
            ->post(route('courriers.circuit.envoyer-cheque', $courrier, absolute: false), [
                'message' => 'Chèque prêt.',
                'montant' => '150000',
                'numero_piece' => 'CHQ-001',
                'banque' => 'BCH',
                'beneficiaire_libelle' => 'TENTATIVE MODIFICATION',
            ])
            ->assertRedirect();

        $this->assertSame('NETPLUS SARL', $courrier->fresh()->suiviPaiement?->beneficiaire_libelle);
    }

    private function creerDg(): User
    {
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'DG')->value('id')]);
        $user->assignRole('dg');

        return $user;
    }

    private function demarrerCircuit(
        User $acteur,
        string $typeCode,
        string $circuitCode,
        ?string $expediteur = null,
    ): Courrier {
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'recu')->firstOrFail();
        $type = TypeCourrier::where('code', $typeCode)->firstOrFail();

        $courrier = Courrier::create([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => $type->id,
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => PrioriteCourrier::where('code', 'normale')->value('id'),
            'numero_registre' => random_int(100, 999),
            'numero_registre_annee' => 2026,
            'origine' => 'externe',
            'date_reception' => now()->toDateString(),
            'expediteur_libelle' => $expediteur,
            'objet' => 'Test délai / bénéficiaire',
            'createur_id' => $acteur->id,
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
            'service_demandeur_structure_id' => in_array($typeCode, ['facture', 'mad'], true)
                ? Structure::where('code', 'DAF')->value('id')
                : null,
        ]);

        $circuit = CircuitCourrier::where('code', $circuitCode)->firstOrFail();

        return app(CircuitCourrierMoteurService::class)->demarrer($courrier, $circuit, $acteur);
    }
}
