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
use App\Notifications\CourrierWorkflowNotification;
use App\Services\CircuitCourrierMoteurService;
use App\Services\CourrierNotificationService;
use App\Services\SmsService;
use Database\Seeders\CircuitCourrierSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class FacturePrestataireSmsNotificationTest extends TestCase
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
        $this->app->instance(SmsService::class, $sms);

        config(['cosud.whatsapp.driver' => 'log']);
    }

    public function test_enregistrement_facture_envoie_sms_au_dg(): void
    {
        Notification::fake();

        $secretaire = User::factory()->create([
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
        ]);
        $secretaire->assignRole('secretaire_direction');

        $dg = User::factory()->create([
            'structure_id' => Structure::where('code', 'DG')->value('id'),
            'telephone' => '242066811111',
        ]);
        $dg->assignRole('dg');

        $courrier = $this->creerFacture($secretaire, 'NETPLUS SARL');
        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();

        app(CircuitCourrierMoteurService::class)->demarrer($courrier, $circuit, $secretaire);

        Notification::assertSentTo(
            $dg,
            CourrierWorkflowNotification::class,
            function (CourrierWorkflowNotification $notification, array $channels) {
                return $notification->type === CourrierNotificationService::FACTURE_ENREGISTREE_DG
                    && in_array('database', $channels, true)
                    && in_array('cosud_sms', $channels, true)
                    && str_contains($notification->toCosudSms($notification->courrier->createur), 'NETPLUS SARL');
            }
        );
        Notification::assertSentToTimes($dg, CourrierWorkflowNotification::class, 1);
        Notification::assertNotSentTo($dg, CourrierWorkflowNotification::class, function (CourrierWorkflowNotification $n) {
            return $n->type === CourrierNotificationService::ETAPE_CIRCUIT;
        });
    }

    public function test_bon_pour_accord_envoie_sms_a_l_ac(): void
    {
        Notification::fake();

        $dg = User::factory()->create([
            'structure_id' => Structure::where('code', 'DG')->value('id'),
            'telephone' => '242066822222',
        ]);
        $dg->assignRole('dg');

        $ac = User::factory()->create([
            'structure_id' => Structure::where('code', 'DAF')->value('id'),
            'telephone' => '242066833333',
        ]);
        $ac->assignRole('agent_comptable');

        $courrier = $this->creerFacture($dg, 'KOMBO SERVICES');
        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();
        $courrier = app(CircuitCourrierMoteurService::class)->demarrer($courrier, $circuit, $dg);

        Notification::fake();

        app(CircuitCourrierMoteurService::class)
            ->instruire($courrier, $dg, 'Payer avant le 30 — etablir le cheque.');

        Notification::assertSentTo(
            $ac,
            CourrierWorkflowNotification::class,
            function (CourrierWorkflowNotification $notification, array $channels) use ($ac) {
                if ($notification->type !== CourrierNotificationService::BON_POUR_ACCORD_AC) {
                    return false;
                }

                $sms = $notification->toCosudSms($ac);

                return in_array('cosud_sms', $channels, true)
                    && str_contains($sms, 'KOMBO SERVICES')
                    && str_contains($sms, 'Payer avant le 30');
            }
        );
    }

    public function test_pas_de_sms_si_telephone_absent(): void
    {
        Notification::fake();

        $secretaire = User::factory()->create([
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
        ]);
        $secretaire->assignRole('secretaire_direction');

        $dg = User::factory()->create([
            'structure_id' => Structure::where('code', 'DG')->value('id'),
            'telephone' => null,
        ]);
        $dg->assignRole('dg');

        $courrier = $this->creerFacture($secretaire, 'SANS TEL');
        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();
        app(CircuitCourrierMoteurService::class)->demarrer($courrier, $circuit, $secretaire);

        Notification::assertSentTo(
            $dg,
            CourrierWorkflowNotification::class,
            function (CourrierWorkflowNotification $notification, array $channels) {
                return $notification->type === CourrierNotificationService::FACTURE_ENREGISTREE_DG
                    && in_array('database', $channels, true)
                    && ! in_array('cosud_sms', $channels, true);
            }
        );
    }

    private function creerFacture(User $createur, string $fournisseur): Courrier
    {
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'recu')->firstOrFail();
        $type = TypeCourrier::where('code', 'facture')->firstOrFail();

        return Courrier::create([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => $type->id,
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => PrioriteCourrier::where('code', 'normale')->value('id'),
            'numero_registre' => 42,
            'numero_registre_annee' => 2026,
            'origine' => 'externe',
            'date_reception' => now()->toDateString(),
            'expediteur_libelle' => $fournisseur,
            'objet' => 'Facture SMS test',
            'createur_id' => $createur->id,
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
            'service_demandeur_structure_id' => Structure::where('code', 'DAF')->value('id'),
            'montant_facture' => 1_000_000,
        ]);
    }
}
