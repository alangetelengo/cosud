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
use App\Services\CourrierRetardService;
use Database\Seeders\CircuitCourrierSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CourrierNotificationsEtRetardsTest extends TestCase
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

    public function test_enregistrement_arrivee_avec_circuit_notifie_dg_et_particuliere_une_seule_fois(): void
    {
        Notification::fake();

        $secretaire = $this->creerSecretaire();
        $dg = User::factory()->create(['structure_id' => Structure::where('code', 'DG')->value('id')]);
        $dg->assignRole('dg');
        $particuliere = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $particuliere->assignRole('particulier_dg');

        $type = TypeCourrier::where('code', 'facture')->firstOrFail();

        $this->actingAs($secretaire)
            ->post(route('courriers.store', absolute: false), [
                'sens' => 'arrivee',
                'objet' => 'Facture test notif',
                'expediteur_libelle' => 'Fournisseur',
                'date_reception' => now()->toDateString(),
                'type_courrier_id' => $type->id,
                'fichier' => UploadedFile::fake()->create('f.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        // Un circuit est attaché : seule l’étape réellement atteinte notifie (pas de
        // notification « enregistrement » redondante en plus).
        Notification::assertSentTo($dg, CourrierWorkflowNotification::class, function ($n) {
            return $n->type === CourrierNotificationService::ETAPE_CIRCUIT;
        });
        Notification::assertSentTo($particuliere, CourrierWorkflowNotification::class, function ($n) {
            return $n->type === CourrierNotificationService::ETAPE_CIRCUIT;
        });
        Notification::assertSentToTimes($dg, CourrierWorkflowNotification::class, 1);
        Notification::assertNotSentTo($dg, CourrierWorkflowNotification::class, function ($n) {
            return $n->type === CourrierNotificationService::ENREGISTREMENT_ARRIVEE;
        });

        $courrier = Courrier::where('objet', 'Facture test notif')->firstOrFail();
        $this->assertNotNull($courrier->circuit_etape_actuelle_id);
        $this->assertSame('instructions_dg', $courrier->circuitEtapeActuelle->code);
        $this->assertNotEmpty(app(CircuitCourrierMoteurService::class)->historiquePourAffichage($courrier));
    }

    public function test_enregistrement_arrivee_sans_circuit_notifie_dg_et_particuliere(): void
    {
        Notification::fake();

        $secretaire = $this->creerSecretaire();
        $dg = User::factory()->create(['structure_id' => Structure::where('code', 'DG')->value('id')]);
        $dg->assignRole('dg');
        $particuliere = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $particuliere->assignRole('particulier_dg');

        // Ni type, ni circuit renseignés : aucun circuit n’est résolu, on reste sur
        // l’ancien flux — la notification générique d’enregistrement doit être conservée.
        $this->actingAs($secretaire)
            ->post(route('courriers.store', absolute: false), [
                'sens' => 'arrivee',
                'objet' => 'Courrier sans circuit test notif',
                'expediteur_libelle' => 'Expéditeur externe',
                'date_reception' => now()->toDateString(),
                'fichier' => UploadedFile::fake()->create('f.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        $courrier = Courrier::where('objet', 'Courrier sans circuit test notif')->firstOrFail();
        $this->assertNull($courrier->circuit_courrier_id);

        Notification::assertSentTo($dg, CourrierWorkflowNotification::class, function ($n) {
            return $n->type === CourrierNotificationService::ENREGISTREMENT_ARRIVEE;
        });
        Notification::assertSentTo($particuliere, CourrierWorkflowNotification::class, function ($n) {
            return $n->type === CourrierNotificationService::ENREGISTREMENT_ARRIVEE;
        });
    }

    public function test_mise_en_parapheur_notifie_le_directeur_de_direction(): void
    {
        Notification::fake();

        $secDaf = Structure::where('code', 'SEC-DAF')->firstOrFail();
        $daf = Structure::where('code', 'DAF')->firstOrFail();

        $directeurDaf = User::factory()->create(['structure_id' => $daf->id]);
        $directeurDaf->assignRole('directeur');

        $secretaire = User::factory()->create(['structure_id' => $secDaf->id]);
        $secretaire->assignRole('secretaire_direction');

        $courrier = $this->creerCourrierArrivee($secretaire);

        $this->actingAs($secretaire)
            ->post(route('courriers.parapheur', $courrier, absolute: false))
            ->assertRedirect();

        $this->assertSame('en_parapheur', $courrier->fresh()->statutCourrier->code);

        Notification::assertSentTo($directeurDaf, CourrierWorkflowNotification::class, function ($n) {
            return $n->type === CourrierNotificationService::MISE_EN_PARAPHEUR;
        });
    }

    public function test_particulier_peut_modifier_courrier(): void
    {
        $particuliere = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $particuliere->assignRole('particulier_dg');

        $courrier = $this->creerCourrierArrivee($particuliere);

        $this->assertTrue($particuliere->can('update', $courrier));
        $this->assertTrue($particuliere->can('corriger', $courrier));
    }

    public function test_alerte_retard_notifie_dg(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $dg = User::factory()->create(['structure_id' => Structure::where('code', 'DG')->value('id')]);
        $dg->assignRole('dg');

        $courrier = $this->creerCourrierArrivee($admin);
        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();
        app(CircuitCourrierMoteurService::class)->demarrer($courrier, $circuit, $admin);

        $courrier->refresh();
        $courrier->circuit_etape_depuis = now()->subHours(config('ged.circuit_retard_heures', 48) + 2);
        $courrier->dernier_alerte_retard_at = null;
        $courrier->save();

        $nb = app(CourrierRetardService::class)->alerterRetards($admin);
        $this->assertSame(1, $nb);

        Notification::assertSentTo($dg, CourrierWorkflowNotification::class, function ($n) {
            return $n->type === CourrierNotificationService::RETARD_TRAITEMENT;
        });
    }

    private function creerSecretaire(): User
    {
        $secDir = Structure::where('code', 'SEC-DIR')->firstOrFail();
        $dg = Structure::where('code', 'DG')->firstOrFail();

        $directeur = User::factory()->create(['structure_id' => $dg->id]);
        $directeur->assignRole('directeur');

        $user = User::factory()->create(['structure_id' => $secDir->id]);
        $user->assignRole('secretaire_direction');

        return $user;
    }

    private function creerCourrierArrivee(User $user): Courrier
    {
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'recu')->firstOrFail();

        return Courrier::create([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => TypeCourrier::where('code', 'facture')->value('id'),
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => PrioriteCourrier::where('code', 'normale')->value('id'),
            'numero_registre' => random_int(100, 900),
            'numero_registre_annee' => (int) now()->year,
            'origine' => Courrier::ORIGINE_EXTERNE,
            'date_reception' => now()->toDateString(),
            'expediteur_libelle' => 'Test',
            'objet' => 'Courrier retard test',
            'createur_id' => $user->id,
            'structure_id' => $user->structure_id,
        ]);
    }
}
