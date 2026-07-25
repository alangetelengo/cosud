<?php

namespace Tests\Feature;

use App\Models\Courrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\Structure;
use App\Models\User;
use App\Notifications\CourrierWorkflowNotification;
use App\Services\CourrierNotificationService;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CourrierOrientationDgTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleAndPermissionSeeder::class,
            StructureSeeder::class,
            CourrierReferentielSeeder::class,
        ]);
    }

    public function test_dg_oriente_vers_secretariat_et_notifie_defaut(): void
    {
        Notification::fake();

        $dg = $this->creerDg();
        $particulier = $this->creerParticulier();
        $directeurDaf = $this->creerDirecteurDaf();
        $secretaireDaf = $this->creerSecretaireDaf();

        $courrier = $this->creerArriveeEnParapheur($dg);

        $daf = Structure::where('code', 'DAF')->firstOrFail();

        $this->actingAs($dg)
            ->post(route('courriers.orienter', $courrier, absolute: false), [
                'orientation_mode' => 'direct',
                'instructions_dg' => 'Traiter pour paiement',
                'est_confidentiel' => '0',
                'destinataire_type' => 'secretariat',
                'direction_id' => $daf->id,
            ])
            ->assertRedirect();

        $courrier->refresh();
        $this->assertSame('oriente', $courrier->statutCourrier->code);
        $this->assertFalse($courrier->est_confidentiel);
        $this->assertSame('direct', $courrier->orientation_mode);
        $this->assertDatabaseHas('courrier_orientations', [
            'courrier_id' => $courrier->id,
            'destinataire_type' => 'secretariat',
        ]);

        Notification::assertSentTo($particulier, CourrierWorkflowNotification::class, fn ($n) => $n->type === CourrierNotificationService::ORIENTATION);
        Notification::assertSentTo($directeurDaf, CourrierWorkflowNotification::class, fn ($n) => $n->type === CourrierNotificationService::ORIENTATION);
        Notification::assertSentTo($secretaireDaf, CourrierWorkflowNotification::class, fn ($n) => $n->type === CourrierNotificationService::ORIENTATION);
    }

    public function test_dg_oriente_confidentiel_avec_agents_choisis(): void
    {
        Notification::fake();

        $dg = $this->creerDg();
        $agentA = User::factory()->create(['structure_id' => Structure::where('code', 'DAF')->value('id')]);
        $agentA->assignRole('utilisateur');
        $agentB = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $agentB->assignRole('secretaire_direction');

        $courrier = $this->creerArriveeEnParapheur($dg);
        $daf = Structure::where('code', 'DAF')->firstOrFail();

        $this->actingAs($dg)
            ->post(route('courriers.orienter', $courrier, absolute: false), [
                'orientation_mode' => 'direct',
                'instructions_dg' => 'Confidentiel — ne pas diffuser',
                'est_confidentiel' => '1',
                'destinataire_type' => 'directeur',
                'direction_id' => $daf->id,
                'notify_user_ids' => [$agentA->id],
            ])
            ->assertRedirect();

        $courrier->refresh();
        $this->assertTrue($courrier->est_confidentiel);

        Notification::assertSentTo($agentA, CourrierWorkflowNotification::class, fn ($n) => $n->type === CourrierNotificationService::ORIENTATION);
        Notification::assertNotSentTo($agentB, CourrierWorkflowNotification::class);
    }

    public function test_dg_instruit_la_particuliere_pour_preparer_une_reponse(): void
    {
        Notification::fake();

        $dg = $this->creerDg();
        $particulier = $this->creerParticulier();
        $courrier = $this->creerArriveeEnParapheur($dg);

        $this->actingAs($dg)
            ->post(route('courriers.orienter', $courrier, absolute: false), [
                'orientation_mode' => 'via_particuliere',
                'instructions_dg' => 'Préparer une lettre de réponse',
                'est_confidentiel' => '0',
            ])
            ->assertRedirect();

        $courrier->refresh();
        $this->assertSame('attente_reponse_particuliere', $courrier->statutCourrier->code);
        $this->assertSame('via_particuliere', $courrier->orientation_mode);

        Notification::assertSentTo(
            $particulier,
            CourrierWorkflowNotification::class,
            fn ($n) => $n->type === CourrierNotificationService::INSTRUCTION_PARTICULIERE
        );
    }

    public function test_page_create_arrivee_affiche_formulaire_et_aide(): void
    {
        $secretaire = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $secretaire->assignRole('secretaire_direction');

        $this->actingAs($secretaire)
            ->get(route('courriers.create', ['sens' => 'arrivee'], absolute: false))
            ->assertOk()
            ->assertSee('Enregistrer au registre', false)
            ->assertSee('Aide à l’enregistrement', false)
            ->assertSee('N° fulgurant', false)
            ->assertSee('Après l’enregistrement', false);
    }

    private function creerArriveeEnParapheur(User $createur): Courrier
    {
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'en_parapheur')->firstOrFail();

        return Courrier::create([
            'sens_courrier_id' => $sens->id,
            'statut_courrier_id' => $statut->id,
            'numero_registre' => random_int(300, 900),
            'numero_registre_annee' => (int) now()->year,
            'origine' => Courrier::ORIGINE_EXTERNE,
            'objet' => 'Courrier à orienter',
            'expediteur_libelle' => 'Ministère',
            'createur_id' => $createur->id,
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
            'date_reception' => now()->toDateString(),
        ]);
    }

    private function creerDg(): User
    {
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'DG')->value('id')]);
        $user->assignRole('dg');

        return $user;
    }

    private function creerParticulier(): User
    {
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $user->assignRole('particulier_dg');

        return $user;
    }

    private function creerDirecteurDaf(): User
    {
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'DAF')->value('id')]);
        $user->assignRole('directeur');

        return $user;
    }

    private function creerSecretaireDaf(): User
    {
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DAF')->value('id')]);
        $user->assignRole('secretaire_direction');

        return $user;
    }
}
