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
use App\Notifications\CourrierFournisseurRecouvrementNotification;
use App\Notifications\CourrierWorkflowNotification;
use App\Services\CircuitCourrierMoteurService;
use App\Services\CourrierNotificationService;
use App\Services\SmsService;
use Database\Seeders\CircuitCourrierSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ModePaiementCircuitFactureTest extends TestCase
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

        $sms = Mockery::mock(SmsService::class)->makePartial();
        $sms->shouldReceive('isConfigured')->andReturn(true);
        $sms->shouldReceive('sanitizeSmsText')->andReturnUsing(fn (string $t): string => $t);
        $this->app->instance(SmsService::class, $sms);
    }

    public function test_dg_doit_choisir_le_mode_de_paiement_a_linstruction(): void
    {
        $dg = $this->creerDg();
        $courrier = $this->demarrerFacture($dg);

        $this->actingAs($dg)
            ->post(route('courriers.circuit.instruire', $courrier, absolute: false), [
                'instructions' => 'Bon pour accord.',
            ])
            ->assertSessionHasErrors('mode_paiement_circuit');
    }

    public function test_instruction_ov_affiche_ui_ac_et_enregistre_references(): void
    {
        Storage::fake('public');
        Notification::fake();

        $dg = $this->creerDg();
        $ac = User::factory()->create(['structure_id' => Structure::where('code', 'DAF')->value('id')]);
        $ac->assignRole('agent_comptable');

        $courrier = $this->demarrerFacture($dg, 'ACS Services');

        $this->actingAs($dg)
            ->post(route('courriers.circuit.instruire', $courrier, absolute: false), [
                'instructions' => 'Bon pour accord — paiement par OV.',
                'mode_paiement_circuit' => Courrier::MODE_PAIEMENT_OV,
            ])
            ->assertRedirect();

        $courrier->refresh();
        $this->assertSame(Courrier::MODE_PAIEMENT_OV, $courrier->mode_paiement_circuit);
        $this->assertSame('ac_etablit_cheque', $courrier->circuitEtapeActuelle?->code);

        Notification::assertSentTo(
            $ac,
            CourrierWorkflowNotification::class,
            function (CourrierWorkflowNotification $n) use ($ac): bool {
                if ($n->type !== CourrierNotificationService::BON_POUR_ACCORD_AC) {
                    return false;
                }

                return str_contains($n->toCosudSms($ac), 'etablir un OV');
            }
        );

        $this->actingAs($ac)
            ->get(route('courriers.show', $courrier, absolute: false))
            ->assertOk()
            ->assertSee('Envoyer l’ordre de virement au DG', false)
            ->assertSee('Saisir les références de l’OV', false)
            ->assertSee('N° / réf. OV', false);

        $this->actingAs($ac)
            ->post(route('courriers.circuit.envoyer-cheque', $courrier, absolute: false), [
                'message' => 'OV établi.',
                'montant' => '750000',
                'numero_piece' => 'OV-2026-0045',
                'banque' => 'BCH',
                'scans_cheque' => [UploadedFile::fake()->create('ov.pdf', 40, 'application/pdf')],
            ])
            ->assertRedirect();

        $courrier->refresh();
        $this->assertSame('dg_signe_cheque', $courrier->circuitEtapeActuelle?->code);
        $this->assertDatabaseHas('suivi_paiements', [
            'courrier_id' => $courrier->id,
            'numero_piece' => 'OV-2026-0045',
            'banque' => 'BCH',
        ]);
    }

    public function test_signature_ov_envoie_sms_fournisseur_avec_references(): void
    {
        Notification::fake();

        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->instruire(
            $this->demarrerFacture($dg, 'NETPLUS SARL'),
            $dg,
            'BPA OV.',
            $ac->id,
            null,
            null,
            Courrier::MODE_PAIEMENT_OV,
        );
        $courrier = $moteur->envoyerChequeAuDg($courrier, $ac, 'OV prêt.', 1_200_000, [
            'numero_piece' => 'OV-9988',
            'banque' => 'UBA',
            'beneficiaire_libelle' => 'NETPLUS SARL',
        ]);
        $courrier->update([
            'expediteur_telephone' => '242066844444',
            'expediteur_libelle' => 'NETPLUS SARL',
            'origine' => 'externe',
        ]);

        $this->actingAs($dg)
            ->get(route('courriers.show', $courrier->fresh(), absolute: false))
            ->assertOk()
            ->assertSee('Confirmer la signature de l’ordre de virement', false)
            ->assertSee('OV signé — renvoyer à l’AC', false);

        $this->actingAs($dg)
            ->post(route('courriers.circuit.signer-cheque', $courrier, absolute: false), [
                'notifier_fournisseur' => '1',
            ])
            ->assertRedirect();

        Notification::assertSentOnDemand(
            CourrierFournisseurRecouvrementNotification::class,
            function (CourrierFournisseurRecouvrementNotification $notification, array $channels, $notifiable): bool {
                $sms = $notification->toCosudSms($notifiable);

                return in_array('cosud_sms', $channels, true)
                    && str_contains($sms, 'NETPLUS SARL')
                    && str_contains($sms, 'OV-9988')
                    && str_contains($sms, 'UBA')
                    && str_contains(mb_strtolower($sms), 'ordre de virement');
            }
        );

        $this->actingAs($ac)
            ->get(route('courriers.show', $courrier->fresh(), absolute: false))
            ->assertOk()
            ->assertSee('Bordereau — accusé de réception banque', false)
            ->assertSee('Enregistrer l’accusé banque', false);
    }

    private function creerDg(): User
    {
        $dg = User::factory()->create(['structure_id' => Structure::where('code', 'DG')->value('id')]);
        $dg->assignRole('dg');

        return $dg;
    }

    private function demarrerFacture(User $acteur, string $fournisseur = 'Fournisseur Test'): Courrier
    {
        $type = TypeCourrier::where('code', 'facture')->firstOrFail();
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('code', 'recu')->firstOrFail();
        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();

        $courrier = Courrier::create([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => $type->id,
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => PrioriteCourrier::where('code', 'normale')->value('id'),
            'numero_registre' => (int) Courrier::query()->max('numero_registre') + 1,
            'numero_registre_annee' => (int) now()->format('Y'),
            'objet' => 'Facture mode paiement '.$fournisseur,
            'expediteur_libelle' => $fournisseur,
            'montant_facture' => 500_000,
            'origine' => 'externe',
            'createur_id' => $acteur->id,
            'structure_id' => $acteur->structure_id,
            'service_demandeur_structure_id' => Structure::where('code', 'DAF')->value('id'),
        ]);

        return app(CircuitCourrierMoteurService::class)->demarrer($courrier, $circuit, $acteur);
    }
}
