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

class BordereauTransmissionTest extends TestCase
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

    public function test_ac_voit_le_bordereau_avec_cheque_en_attente_signature(): void
    {
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        $this->creerChequeEnSignature('Chèque N° 0000401', 'SOMAC Services');

        $this->actingAs($ac)
            ->get(route('bordereau-transmission.index', absolute: false))
            ->assertOk()
            ->assertSee('Bordereau de transmission', false)
            ->assertSee('Chèque N° 0000401', false)
            ->assertSee('SOMAC Services', false)
            ->assertSee('Signature DG', false)
            ->assertSee('Total hebdomadaire', false)
            ->assertSee('Périodicité', false);

        $this->actingAs($ac)
            ->get(route('bordereau-transmission.index', ['periode' => 'mensuel'], absolute: false))
            ->assertOk()
            ->assertSee('Total mensuel', false);

        $this->actingAs($ac)
            ->get(route('bordereau-transmission.index', ['periode' => 'trimestriel'], absolute: false))
            ->assertOk()
            ->assertSee('Total trimestriel', false);
    }

    public function test_eleni_et_dg_accedent_au_bordereau(): void
    {
        $this->creerChequeEnSignature('Chèque N° 0000402', 'BL Technology');

        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $this->actingAs($eleni)
            ->get(route('bordereau-transmission.index', absolute: false))
            ->assertOk()
            ->assertSee('Chèque N° 0000402', false);

        $dg = $this->creerDg();
        $this->actingAs($dg)
            ->get(route('bordereau-transmission.index', absolute: false))
            ->assertOk()
            ->assertSee('Chèque N° 0000402', false);
    }

    public function test_ac_n_accede_plus_au_suivi_paiements_eleni(): void
    {
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        $this->actingAs($ac)
            ->get(route('suivi-paiements.index', absolute: false))
            ->assertForbidden();
    }

    public function test_utilisateur_sans_permission_interdit_sur_bordereau(): void
    {
        $user = User::factory()->create();
        $user->assignRole('utilisateur');

        $this->actingAs($user)
            ->get(route('bordereau-transmission.index', absolute: false))
            ->assertForbidden();
    }

    public function test_envoi_cheque_exige_les_references_bordereau(): void
    {
        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        $courrier = $this->demarrerEtInstruire($dg, $ac, 'Facture refs obligatoires');

        $this->actingAs($ac)
            ->from(route('courriers.show', $courrier, absolute: false))
            ->post(route('courriers.circuit.envoyer-cheque', $courrier, absolute: false), [
                'message' => 'Prêt',
                'montant' => 100000,
            ])
            ->assertRedirect(route('courriers.show', $courrier, absolute: false))
            ->assertSessionHasErrors(['numero_piece', 'banque']);
    }

    private function creerChequeEnSignature(string $numeroPiece, string $beneficiaire): Courrier
    {
        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        $courrier = $this->demarrerEtInstruire($dg, $ac, 'Facture bordereau '.$numeroPiece);
        app(CircuitCourrierMoteurService::class)->envoyerChequeAuDg(
            $courrier,
            $ac,
            'Chèque établi.',
            594500,
            [
                'numero_piece' => $numeroPiece,
                'banque' => 'CREDIT DU CONGO',
                'beneficiaire_libelle' => $beneficiaire,
                'programmation' => '05 Août 2026',
            ]
        );

        return $courrier->fresh();
    }

    private function demarrerEtInstruire(User $dg, User $ac, string $objet): Courrier
    {
        $courrier = $this->creerCourrierArrivee($dg, $objet);
        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();
        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->demarrer($courrier, $circuit, $dg);

        return $moteur->instruire($courrier, $dg, 'Bon pour accord.', $ac->id);
    }

    private function creerDg(): User
    {
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'DG')->value('id')]);
        $user->assignRole('dg');

        return $user;
    }

    private function creerCourrierArrivee(User $acteur, string $objet): Courrier
    {
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'recu')->firstOrFail();
        $type = TypeCourrier::where('code', 'facture')->firstOrFail();

        return Courrier::create([
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
            'createur_id' => $acteur->id,
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
            'service_demandeur_structure_id' => Structure::where('code', 'DAF')->value('id'),
            'montant_facture' => 594_500,
        ]);
    }
}
