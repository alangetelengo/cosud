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
use App\Notifications\CourrierExpediteurTraiteNotification;
use App\Notifications\CourrierExpediteurValideNotification;
use App\Services\CircuitCourrierMoteurService;
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
            CircuitCourrierSeeder::class,
            StatutDocumentSeeder::class,
            TypeDocumentSeeder::class,
        ]);
    }

    public function test_cloture_arrivee_externe_notifie_email_expediteur(): void
    {
        Notification::fake();
        config([
            'ged.sms.provider' => 'wirepick',
            'ged.sms.wirepick.client' => 'test-client',
            'ged.sms.wirepick.password' => 'secret-password',
        ]);

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
                && ($notifiable->routes['ged_sms'] ?? null) === '+242061234567';
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

    public function test_validation_projet_notifie_expediteur_externe(): void
    {
        Notification::fake();
        config([
            'ged.sms.provider' => 'wirepick',
            'ged.sms.wirepick.client' => 'test-client',
            'ged.sms.wirepick.password' => 'secret-password',
        ]);

        $dg = $this->creerDg();
        $particuliere = $this->creerParticuliere();
        $courrier = $this->creerArriveeExternePourCircuit($dg, [
            'expediteur_email' => 'stagiaire@exemple.cg',
            'expediteur_telephone' => '+242066835332',
            'objet' => 'demande de stage',
        ]);

        $moteur = app(CircuitCourrierMoteurService::class);
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();
        $courrier = $moteur->instruire($moteur->demarrer($courrier, $circuit, $dg), $dg, 'Préparer la note.');

        $this->actingAs($particuliere)
            ->post(route('courriers.circuit.soumettre-reponse', $courrier, absolute: false), [
                'document_reponse' => UploadedFile::fake()->create('note.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->actingAs($dg)
            ->post(route('courriers.circuit.valider-reponse', $courrier->fresh(), absolute: false), [])
            ->assertRedirect();

        Notification::assertSentOnDemand(CourrierExpediteurValideNotification::class, function ($notification, $channels, $notifiable) use ($courrier) {
            if (! $notification->courrier->is($courrier)
                || ($notifiable->routes['mail'] ?? null) !== 'stagiaire@exemple.cg'
                || ($notifiable->routes['ged_sms'] ?? null) !== '+242066835332') {
                return false;
            }

            $mail = $notification->toMail($notifiable);
            $sms = $notification->toGedSms($notifiable);

            return str_contains(implode(' ', $mail->introLines), 'validé')
                && str_contains(implode(' ', $mail->introLines), 'aucune démarche')
                && str_contains($sms, 'VALIDÉ')
                && str_contains($sms, 'Aucune action');
        });
    }

    public function test_expedition_reponse_cloture_automatiquement_l_arrivee(): void
    {
        Notification::fake();
        config([
            'ged.sms.provider' => 'wirepick',
            'ged.sms.wirepick.client' => 'test-client',
            'ged.sms.wirepick.password' => 'secret-password',
        ]);

        $dg = $this->creerDg();
        $particuliere = $this->creerParticuliere();
        $secDdsait = Structure::where('code', 'SEC-DDSAIT')->firstOrFail();
        $courrier = $this->creerArriveeExternePourCircuit($dg, [
            'expediteur_email' => 'stagiaire@exemple.cg',
            'expediteur_telephone' => '+242066835332',
            'objet' => 'demande de stage',
        ]);

        $moteur = app(CircuitCourrierMoteurService::class);
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();
        $courrier = $moteur->instruire($moteur->demarrer($courrier, $circuit, $dg), $dg, 'Préparer la note.');

        $this->actingAs($particuliere)
            ->post(route('courriers.circuit.soumettre-reponse', $courrier, absolute: false), [
                'document_reponse' => UploadedFile::fake()->create('note.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();
        $this->actingAs($dg)
            ->post(route('courriers.circuit.valider-reponse', $courrier->fresh(), absolute: false), [])
            ->assertRedirect();

        $reponse = Courrier::where('courrier_parent_id', $courrier->id)->firstOrFail();
        $this->assertSame('signe', $reponse->statutCourrier->code);

        $this->actingAs($particuliere)
            ->post(route('courriers.expedier-interne', $reponse, absolute: false), [
                'structure_destinataire_id' => $secDdsait->id,
                'numero_archives' => 'DG/DEP/2026/010',
            ])
            ->assertRedirect();

        $this->assertSame('expedie', $reponse->fresh()->statutCourrier->code);
        $this->assertSame('cloture', $courrier->fresh()->statutCourrier->code);
        $this->assertNull($courrier->fresh()->circuit_etape_actuelle_id);

        Notification::assertSentOnDemand(CourrierExpediteurTraiteNotification::class, function ($notification, $channels, $notifiable) use ($courrier) {
            if (! $notification->courrier->is($courrier)
                || ($notifiable->routes['ged_sms'] ?? null) !== '+242066835332') {
                return false;
            }

            $sms = $notification->toGedSms($notifiable);

            return str_contains($sms, 'CLÔTURÉ')
                && str_contains($sms, 'Aucune action');
        });
    }

    public function test_cloture_manuelle_refusee_sur_arrivee_encore_en_recu(): void
    {
        $secretaire = $this->creerSecretaire();
        $courrier = $this->creerArriveeExternePourCircuit($secretaire);

        $this->actingAs($secretaire)
            ->post(route('courriers.cloturer', $courrier, absolute: false))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('recu', $courrier->fresh()->statutCourrier->code);
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

    /**
     * @param  array<string, mixed>  $extra
     */
    private function creerArriveeExternePourCircuit(User $user, array $extra = []): Courrier
    {
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'recu')->firstOrFail();

        return Courrier::create(array_merge([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => TypeCourrier::where('code', 'administratif')->value('id'),
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => PrioriteCourrier::where('code', 'normale')->value('id'),
            'numero_registre' => random_int(200, 900),
            'numero_registre_annee' => (int) now()->year,
            'origine' => Courrier::ORIGINE_EXTERNE,
            'est_expediteur_externe' => true,
            'date_reception' => now()->toDateString(),
            'expediteur_libelle' => 'Demandeur externe',
            'objet' => 'Dossier circuit',
            'createur_id' => $user->id,
            'structure_id' => $user->structure_id,
        ], $extra));
    }

    private function creerDg(): User
    {
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'DG')->value('id')]);
        $user->assignRole('dg');

        return $user;
    }

    private function creerParticuliere(): User
    {
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $user->assignRole('particulier_dg');

        return $user;
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
