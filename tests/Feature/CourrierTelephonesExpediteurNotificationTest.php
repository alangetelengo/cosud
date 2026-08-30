<?php

namespace Tests\Feature;

use App\Models\Courrier;
use App\Models\FournisseurPrestataire;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\Structure;
use App\Models\TypeCourrier;
use App\Models\User;
use App\Notifications\CourrierExpediteurTraiteNotification;
use Database\Seeders\CircuitCourrierSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StatutDocumentSeeder;
use Database\Seeders\StructureSeeder;
use Database\Seeders\TypeDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourrierTelephonesExpediteurNotificationTest extends TestCase
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
            StatutDocumentSeeder::class,
            TypeDocumentSeeder::class,
        ]);
    }

    public function test_creation_arrivee_persiste_telephone_2_et_flags_notifier(): void
    {
        Storage::fake('public');
        $secretaire = $this->creerSecretaire();

        $this->actingAs($secretaire)
            ->post(route('courriers.store', absolute: false), [
                'sens' => 'arrivee',
                'numero_fulgurant' => 'REG-tel2/2026',
                'objet' => 'Demande deux téléphones',
                'expediteur_libelle' => 'Entreprise Y',
                'expediteur_telephone' => '+242060000001',
                'expediteur_telephone_2' => '+242060000002',
                'expediteur_notifier_telephone' => '1',
                'expediteur_notifier_telephone_2' => '0',
                'date_reception' => now()->toDateString(),
                'fichier' => UploadedFile::fake()->create('scan.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('courriers', [
            'objet' => 'Demande deux téléphones',
            'expediteur_telephone' => '+242060000001',
            'expediteur_telephone_2' => '+242060000002',
            'expediteur_notifier_telephone' => 1,
            'expediteur_notifier_telephone_2' => 0,
        ]);
    }

    public function test_cloture_notifie_uniquement_les_numeros_coches(): void
    {
        Notification::fake();
        config([
            'cosud.sms.provider' => 'wirepick',
            'cosud.sms.wirepick.client' => 'test-client',
            'cosud.sms.wirepick.password' => 'secret-password',
            'cosud.whatsapp.driver' => '',
        ]);

        $secretaire = $this->creerSecretaire();
        $courrier = $this->creerArriveeExterneVentilee($secretaire, [
            'expediteur_email' => 'contact@exemple.cg',
            'expediteur_telephone' => '+242061111111',
            'expediteur_telephone_2' => '+242062222222',
            'expediteur_notifier_telephone' => true,
            'expediteur_notifier_telephone_2' => false,
        ]);

        $this->actingAs($secretaire)
            ->post(route('courriers.cloturer', $courrier, absolute: false))
            ->assertRedirect();

        Notification::assertSentOnDemand(CourrierExpediteurTraiteNotification::class, function ($notification, $channels, $notifiable) use ($courrier) {
            return $notification->courrier->is($courrier)
                && ($notifiable->routes['mail'] ?? null) === 'contact@exemple.cg'
                && ($notifiable->routes['cosud_sms'] ?? null) === '+242061111111';
        });

        Notification::assertSentOnDemandTimes(CourrierExpediteurTraiteNotification::class, 1);
    }

    public function test_cloture_notifie_les_deux_numeros_si_coches(): void
    {
        Notification::fake();
        config([
            'cosud.sms.provider' => 'wirepick',
            'cosud.sms.wirepick.client' => 'test-client',
            'cosud.sms.wirepick.password' => 'secret-password',
            'cosud.whatsapp.driver' => '',
        ]);

        $secretaire = $this->creerSecretaire();
        $courrier = $this->creerArriveeExterneVentilee($secretaire, [
            'expediteur_telephone' => '+242061111111',
            'expediteur_telephone_2' => '+242062222222',
            'expediteur_notifier_telephone' => true,
            'expediteur_notifier_telephone_2' => true,
        ]);

        $this->actingAs($secretaire)
            ->post(route('courriers.cloturer', $courrier, absolute: false))
            ->assertRedirect();

        Notification::assertSentOnDemand(CourrierExpediteurTraiteNotification::class, function ($notification, $channels, $notifiable) use ($courrier) {
            return $notification->courrier->is($courrier)
                && ($notifiable->routes['cosud_sms'] ?? null) === '+242061111111';
        });

        Notification::assertSentOnDemand(CourrierExpediteurTraiteNotification::class, function ($notification, $channels, $notifiable) use ($courrier) {
            return $notification->courrier->is($courrier)
                && ($notifiable->routes['cosud_sms'] ?? null) === '+242062222222';
        });

        Notification::assertSentOnDemandTimes(CourrierExpediteurTraiteNotification::class, 2);
    }

    public function test_fiche_fournisseur_persiste_telephone_2_et_prefill_facture(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $user->assignRole('secretaire_direction');

        $dg = User::factory()->create(['structure_id' => Structure::where('code', 'DG')->value('id')]);
        $dg->assignRole('dg');

        $this->actingAs($dg)
            ->post(route('fournisseurs-prestataires.store', absolute: false), [
                'nom' => 'Tel Deux SARL',
                'type' => 'fournisseur',
                'telephone' => '+242060000010',
                'telephone_2' => '+242060000020',
                'notifier_telephone' => '1',
                'notifier_telephone_2' => '0',
            ])
            ->assertRedirect();

        $fiche = FournisseurPrestataire::query()
            ->where('nom_normalise', FournisseurPrestataire::normaliserNom('Tel Deux SARL'))
            ->firstOrFail();

        $this->assertSame('+242060000010', $fiche->telephone);
        $this->assertSame('+242060000020', $fiche->telephone_2);
        $this->assertTrue($fiche->notifier_telephone);
        $this->assertFalse($fiche->notifier_telephone_2);

        $this->actingAs($user)
            ->post(route('courriers.store', absolute: false), [
                'sens' => 'arrivee',
                'numero_fulgurant' => 'REG-fp-tel2/2026',
                'type_courrier_id' => TypeCourrier::where('code', 'facture')->value('id'),
                'objet' => 'Facture préremplie tél 2',
                'fournisseur_prestataire_id' => $fiche->id,
                'service_demandeur_structure_id' => Structure::where('code', 'DAF')->value('id'),
                'montant_facture' => '100000',
                'date_reception' => now()->toDateString(),
                'fichier' => UploadedFile::fake()->create('f.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        $courrier = Courrier::query()->where('objet', 'Facture préremplie tél 2')->firstOrFail();
        $this->assertSame('+242060000010', $courrier->expediteur_telephone);
        $this->assertSame('+242060000020', $courrier->expediteur_telephone_2);
    }

    public function test_telephones_expediteur_pour_notification_respecte_les_flags(): void
    {
        $courrier = new Courrier([
            'expediteur_telephone' => '+242061111111',
            'expediteur_telephone_2' => '+242062222222',
            'expediteur_notifier_telephone' => false,
            'expediteur_notifier_telephone_2' => true,
        ]);

        $this->assertSame(['+242062222222'], $courrier->telephonesExpediteurPourNotification());
    }

    private function creerSecretaire(): User
    {
        $user = User::factory()->create([
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
        ]);
        $user->assignRole('secretaire_direction');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function creerArriveeExterneVentilee(User $user, array $extra = []): Courrier
    {
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'ventile')->firstOrFail();

        return Courrier::create(array_merge([
            'sens_courrier_id' => $sens->id,
            'statut_courrier_id' => $statut->id,
            'numero_registre' => random_int(200, 900),
            'numero_registre_annee' => (int) now()->year,
            'origine' => Courrier::ORIGINE_EXTERNE,
            'est_expediteur_externe' => true,
            'date_reception' => now()->toDateString(),
            'expediteur_libelle' => 'Fournisseur Test',
            'objet' => 'Dossier à clôturer',
            'createur_id' => $user->id,
            'structure_id' => $user->structure_id,
        ], $extra));
    }
}
