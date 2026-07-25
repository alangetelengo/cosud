<?php

namespace Tests\Feature;

use App\Models\Courrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\Structure;
use App\Models\User;
use App\Notifications\CourrierExpediteurTraiteNotification;
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

class CourrierExpediteurContactClotureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleAndPermissionSeeder::class,
            StructureSeeder::class,
            CourrierReferentielSeeder::class,
            StatutDocumentSeeder::class,
            TypeDocumentSeeder::class,
        ]);
    }

    public function test_cloture_arrivee_externe_notifie_email_expediteur(): void
    {
        Notification::fake();
        config(['services.vonage.key' => 'test-key']);

        $secretaire = $this->creerSecretaire();
        $courrier = $this->creerArriveeExterneVentilee($secretaire, [
            'expediteur_email' => 'fournisseur@exemple.cg',
            'expediteur_telephone' => '+242061234567',
        ]);

        $this->actingAs($secretaire)
            ->post(route('courriers.cloturer', $courrier, absolute: false))
            ->assertRedirect();

        $this->assertSame('cloture', $courrier->fresh()->statutCourrier->code);

        Notification::assertSentOnDemand(CourrierExpediteurTraiteNotification::class, function ($notification, $channels, $notifiable) use ($courrier) {
            return $notification->courrier->is($courrier)
                && ($notifiable->routes['mail'] ?? null) === 'fournisseur@exemple.cg'
                && ($notifiable->routes['vonage'] ?? null) === '+242061234567';
        });
    }

    public function test_cloture_sans_contact_n_envoie_pas_de_notification(): void
    {
        Notification::fake();

        $secretaire = $this->creerSecretaire();
        $courrier = $this->creerArriveeExterneVentilee($secretaire);

        $this->actingAs($secretaire)
            ->post(route('courriers.cloturer', $courrier, absolute: false))
            ->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_creation_arrivee_accepte_contacts_optionnels(): void
    {
        Storage::fake('public');
        $secretaire = $this->creerSecretaire();

        $this->actingAs($secretaire)
            ->post(route('courriers.store', absolute: false), [
                'sens' => 'arrivee',
                'objet' => 'Demande avec contacts',
                'expediteur_libelle' => 'Entreprise X',
                'expediteur_email' => 'x@exemple.cg',
                'expediteur_telephone' => '+242060000001',
                'date_reception' => now()->toDateString(),
                'fichier' => UploadedFile::fake()->create('scan.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('courriers', [
            'objet' => 'Demande avec contacts',
            'expediteur_email' => 'x@exemple.cg',
            'expediteur_telephone' => '+242060000001',
        ]);
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

    private function creerSecretaire(): User
    {
        $user = User::factory()->create([
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
        ]);
        $user->assignRole('secretaire_direction');

        return $user;
    }
}
