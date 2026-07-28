<?php

namespace Tests\Feature;

use App\Models\CircuitCourrier;
use App\Models\Courrier;
use App\Models\Document;
use App\Models\PrioriteCourrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\Structure;
use App\Models\SuiviPaiement;
use App\Models\TypeCourrier;
use App\Models\TypeDocument;
use App\Models\User;
use App\Notifications\CourrierFournisseurRecouvrementNotification;
use App\Notifications\CourrierWorkflowNotification;
use App\Services\CircuitCourrierMoteurService;
use App\Services\CourrierNotificationService;
use Database\Seeders\CircuitCourrierSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Database\Seeders\TypeDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CircuitCourrierInstructionAgentTest extends TestCase
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
    }

    public function test_instruire_sans_agent_passe_automatiquement_a_l_ac(): void
    {
        Notification::fake();

        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        $courrier = $this->demarrerFacture($dg);

        $courrier = app(CircuitCourrierMoteurService::class)
            ->instruire($courrier, $dg, 'À payer avant le 30 du mois.');

        $this->assertNull($courrier->agent_confie_id);
        // Plus de « Valider l’étape » chez Anne : relais dossiers → AC auto.
        $this->assertSame('ac_etablit_cheque', $courrier->circuitEtapeActuelle->code);
        $this->assertSame('À payer avant le 30 du mois.', $courrier->instructions_dg);
        Notification::assertSentTo($ac, CourrierWorkflowNotification::class);
    }

    public function test_instruire_avec_agent_comptable_saute_vers_etape_ac(): void
    {
        Notification::fake();

        $dg = $this->creerDg();
        $ac = User::factory()->create(['structure_id' => Structure::where('code', 'DAF')->value('id')]);
        $ac->assignRole('agent_comptable');

        $courrier = $this->demarrerFacture($dg);

        $courrier = app(CircuitCourrierMoteurService::class)->instruire(
            $courrier,
            $dg,
            'Transmettre à l’AC pour établissement du chèque.',
            $ac->id,
        );

        $this->assertSame($ac->id, $courrier->agent_confie_id);
        $this->assertSame('ac_etablit_cheque', $courrier->circuitEtapeActuelle->code);
        $this->assertTrue(
            app(CircuitCourrierMoteurService::class)->peutAgir($courrier, $ac)
        );

        $responsable = User::factory()->create();
        $responsable->assignRole('responsable_dossiers_prestataires');
        $this->assertFalse(
            app(CircuitCourrierMoteurService::class)->userCorrespondActeur($responsable, $courrier->circuitEtapeActuelle, $courrier)
        );

        Notification::assertSentTo($ac, CourrierWorkflowNotification::class);
    }

    public function test_instruire_avec_agent_hors_circuit_override_letape_suivante(): void
    {
        $dg = $this->creerDg();
        // Agent sans rôle du circuit facture → pas de saut d’étape, override sur l’étape suivante.
        $agentLibre = User::factory()->create(['structure_id' => Structure::where('code', 'DAF')->value('id')]);

        $courrier = $this->demarrerFacture($dg);
        $moteur = app(CircuitCourrierMoteurService::class);

        $courrier = $moteur->instruire($courrier, $dg, 'À traiter par cet agent.', $agentLibre->id);

        $this->assertSame($agentLibre->id, $courrier->agent_confie_id);
        $this->assertSame('traitement_dossiers_vers_ac', $courrier->circuitEtapeActuelle->code);
        $this->assertTrue($moteur->peutAgir($courrier, $agentLibre));
        $this->assertSame('Collaborateur confié — '.$agentLibre->name, $moteur->libelleActeurPour($courrier, $courrier->circuitEtapeActuelle));

        $responsable = User::factory()->create();
        $responsable->assignRole('responsable_dossiers_prestataires');
        $this->assertFalse($moteur->userCorrespondActeur($responsable, $courrier->circuitEtapeActuelle, $courrier));

        $courrier = $moteur->avancer($courrier, $agentLibre, 'Traité par l’agent.');
        $this->assertNull($courrier->agent_confie_id);
        $this->assertSame('ac_etablit_cheque', $courrier->circuitEtapeActuelle->code);
    }

    public function test_formulaire_http_accepte_agent_confie_facultatif(): void
    {
        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');
        $courrier = $this->demarrerFacture($dg);

        $this->actingAs($dg)
            ->post(route('courriers.circuit.instruire', $courrier, absolute: false), [
                'instructions' => 'À l’AC pour chèque.',
                'agent_confie_id' => $ac->id,
            ])
            ->assertRedirect();

        $courrier->refresh();
        $this->assertSame($ac->id, $courrier->agent_confie_id);
        $this->assertSame('ac_etablit_cheque', $courrier->circuitEtapeActuelle->code);
    }

    public function test_agent_confie_peut_voir_les_pieces_jointes_du_courrier(): void
    {
        $dg = $this->creerDg();
        $ac = User::factory()->create(['structure_id' => Structure::where('code', 'DAF')->value('id')]);
        $ac->assignRole('agent_comptable');

        $secretaire = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $secretaire->assignRole('secretaire_direction');

        $document = Document::create([
            'type_document_id' => TypeDocument::where('code', 'COURRIER_IN')->value('id')
                ?? TypeDocument::where('code', 'LETTRE')->value('id'),
            'proprietaire_id' => $secretaire->id,
            'createur_id' => $secretaire->id,
            'user_id' => $secretaire->id,
            'nom_original' => 'facture-kombo.png',
            'chemin' => 'documents/courriers/facture-kombo.png',
            'extension' => 'png',
            'taille_octets' => 1024,
            'statut' => 'brouillon',
            'confidentiel' => true,
            'en_corbeille' => false,
        ]);

        $courrier = $this->demarrerFacture($dg);
        $courrier->documents()->attach($document->id);

        $this->assertFalse($document->fresh()->visiblePar($ac));
        $this->assertFalse($ac->can('view', $document));

        app(CircuitCourrierMoteurService::class)->instruire(
            $courrier,
            $dg,
            'Établir le chèque pour le paiement de cette facture.',
            $ac->id,
        );

        $document = $document->fresh();
        $this->assertTrue($document->visiblePar($ac));
        $this->assertTrue($ac->can('view', $document));
        $this->assertTrue(Document::visibleBy($ac)->whereKey($document->id)->exists());

        $this->actingAs($ac)
            ->get(route('courriers.show', $courrier->fresh(), absolute: false))
            ->assertOk()
            ->assertSee('facture-kombo.png', false)
            ->assertDontSee('accès restreint', false);
    }

    public function test_ac_envoie_cheque_au_dg_sans_courrier_depart(): void
    {
        Notification::fake();

        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');
        $particuliereDg = User::factory()->create();
        $particuliereDg->assignRole('particulier_dg');
        $particuliereAc = User::factory()->create();
        $particuliereAc->assignRole('particulier_ac');

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->instruire(
            $this->demarrerFacture($dg),
            $dg,
            'Établir le chèque.',
            $ac->id,
        );
        $this->assertSame('ac_etablit_cheque', $courrier->circuitEtapeActuelle->code);

        $this->actingAs($ac)
            ->get(route('courriers.show', $courrier, absolute: false))
            ->assertOk()
            ->assertSee('Envoyer le chèque au DG', false)
            ->assertDontSee('Valider l’étape', false);

        $this->actingAs($ac)
            ->post(route('courriers.circuit.envoyer-cheque', $courrier, absolute: false), [
                'message' => 'Chèque établi, prêt pour signature.',
                'montant' => 1949700,
                'scan_cheque' => UploadedFile::fake()->create('cheque.pdf', 30, 'application/pdf'),
            ])
            ->assertRedirect();

        $courrier->refresh();
        $this->assertSame('dg_signe_cheque', $courrier->circuitEtapeActuelle->code);
        $this->assertSame('Chèque établi, prêt pour signature.', $courrier->message_ac);
        $this->assertNull($courrier->agent_confie_id);
        $this->assertSame(0, Courrier::where('courrier_parent_id', $courrier->id)->count());
        $this->assertTrue($courrier->documents()->where('nom_original', 'cheque.pdf')->exists());
        $this->assertDatabaseHas('suivi_paiements', [
            'courrier_id' => $courrier->id,
            'type' => SuiviPaiement::TYPE_FSP_FACTURE,
            'montant' => 1949700,
        ]);

        Notification::assertSentTo($particuliereDg, CourrierWorkflowNotification::class);
        Notification::assertSentTo($particuliereAc, CourrierWorkflowNotification::class);
    }

    public function test_ac_etablit_cheque_notifie_responsable_suivi_depenses_et_cree_fsp(): void
    {
        Notification::fake();

        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');
        $suivi = User::factory()->create();
        $suivi->assignRole('responsable_suivi_depenses');

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->instruire(
            $this->demarrerFacture($dg),
            $dg,
            'Bon pour accord.',
            $ac->id,
        );

        $courrier = $moteur->envoyerChequeAuDg($courrier, $ac, 'Chèque établi.', 599294);

        $this->assertSame('dg_signe_cheque', $courrier->circuitEtapeActuelle->code);
        $this->assertDatabaseHas('suivi_paiements', [
            'courrier_id' => $courrier->id,
            'type' => SuiviPaiement::TYPE_FSP_FACTURE,
            'montant' => 599294,
            'instruction_dg' => 'Bon pour accord.',
            'etabli_par_id' => $ac->id,
        ]);

        Notification::assertSentTo(
            $suivi,
            CourrierWorkflowNotification::class,
            function (CourrierWorkflowNotification $notification) use ($courrier): bool {
                return $notification->type === CourrierNotificationService::ENTREE_CHEQUE_SUIVI_DEPENSE
                    && $notification->courrier->id === $courrier->id;
            }
        );
    }

    public function test_ac_etablit_cheque_mad_cree_fsp_mad(): void
    {
        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        $courrier = $this->creerCourrierArrivee($dg, 'mad');
        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();
        $courrier = app(CircuitCourrierMoteurService::class)->demarrer($courrier, $circuit, $dg);

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->instruire($courrier, $dg, 'Bon pour accord.', $ac->id);
        $courrier = $moteur->envoyerChequeAuDg($courrier, $ac, 'MAD établie.', 1926000);

        $this->assertDatabaseHas('suivi_paiements', [
            'courrier_id' => $courrier->id,
            'type' => SuiviPaiement::TYPE_FSP_MAD,
            'montant' => 1926000,
            'responsable_dossier_id' => $ac->id,
        ]);
    }

    public function test_dg_signe_cheque_notifie_fournisseur_et_avance(): void
    {
        Notification::fake();

        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->instruire($this->demarrerFacture($dg), $dg, 'Établir le chèque.', $ac->id);
        $courrier = $moteur->envoyerChequeAuDg($courrier, $ac, 'Chèque prêt.', 8944253);
        $courrier->update([
            'expediteur_email' => 'fournisseur@example.com',
            'expediteur_libelle' => 'ETS KOMBO',
            'origine' => 'externe',
        ]);

        $this->actingAs($dg)
            ->get(route('courriers.show', $courrier->fresh(), absolute: false))
            ->assertOk()
            ->assertSee('Signer le chèque', false)
            ->assertSee('Enregistrer le chèque signé', false)
            ->assertDontSee('Valider l’étape', false)
            ->assertDontSee('création du courrier réponse', false);

        $this->actingAs($dg)
            ->post(route('courriers.circuit.signer-cheque', $courrier, absolute: false), [
                'scan_cheque_signe' => UploadedFile::fake()->create('cheque-signe.pdf', 40, 'application/pdf'),
                'message' => 'Signé ce jour.',
                'notifier_fournisseur' => '1',
            ])
            ->assertRedirect();

        $courrier->refresh();
        // Après signature : relais AC→caissiers et retour caisse en auto → preuve de paiement.
        $this->assertSame('preuve_paiement', $courrier->circuitEtapeActuelle->code);
        $this->assertTrue($courrier->documents()->where('nom_original', 'cheque-signe.pdf')->exists());

        Notification::assertSentOnDemand(
            CourrierFournisseurRecouvrementNotification::class,
            function ($notification, $channels, $notifiable) {
                return ($notifiable->routes['mail'] ?? null) === 'fournisseur@example.com';
            }
        );
    }

    public function test_preuve_paiement_cloture_le_dossier(): void
    {
        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');
        $suivi = User::factory()->create();
        $suivi->assignRole('responsable_suivi_depenses');

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->instruire($this->demarrerFacture($dg), $dg, 'Payer.', $ac->id);
        $courrier = $moteur->envoyerChequeAuDg($courrier, $ac, 'Chèque prêt.', 8944253);
        $courrier = $moteur->signerChequeDg($courrier, $dg, 'Signé.', false);
        $this->assertSame('preuve_paiement', $courrier->circuitEtapeActuelle->code);

        $this->actingAs($suivi)
            ->post(route('courriers.circuit.deposer-preuve-paiement', $courrier, absolute: false), [
                'preuve_paiement' => UploadedFile::fake()->create('preuve.pdf', 20, 'application/pdf'),
                'message' => 'Payé le 25/07.',
            ])
            ->assertRedirect();

        $courrier->refresh();
        $this->assertNull($courrier->circuit_etape_actuelle_id);
        $this->assertTrue($courrier->documents()->where('nom_original', 'preuve.pdf')->exists());
        $this->assertDatabaseHas('circuit_courrier_historiques', [
            'courrier_id' => $courrier->id,
            'evenement' => 'cloture_circuit',
        ]);
    }

    public function test_relancer_masque_quand_c_est_le_tour_du_dg(): void
    {
        $dg = $this->creerDg();
        $courrier = $this->demarrerFacture($dg);
        $this->assertSame('instructions_dg', $courrier->circuitEtapeActuelle->code);

        $this->actingAs($dg)
            ->get(route('courriers.show', $courrier, absolute: false))
            ->assertOk()
            ->assertDontSee('Relancer le responsable', false);

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->instruire($courrier, $dg, 'Traiter le dossier.');
        $this->assertSame('ac_etablit_cheque', $courrier->circuitEtapeActuelle->code);

        $this->actingAs($dg)
            ->get(route('courriers.show', $courrier, absolute: false))
            ->assertOk()
            ->assertSee('Relancer le responsable', false);
    }

    public function test_ui_separe_option_a_et_b_pour_le_dg(): void
    {
        $dg = $this->creerDg();
        $courrier = $this->demarrerFacture($dg);

        $this->actingAs($dg)
            ->get(route('courriers.show', $courrier, absolute: false))
            ->assertOk()
            ->assertSee('A — Instruire le dossier', false)
            ->assertSee('Confier à un collaborateur', false)
            ->assertSee('À payer avant le 30 du mois', false)
            ->assertDontSee('préparer un projet de note', false)
            ->assertSee('B — Répondre moi-même', false)
            ->assertSee('Répondre moi-même et signer', false);
    }

    public function test_placeholder_instructions_adapte_au_circuit_general(): void
    {
        $dg = $this->creerDg();
        $courrier = $this->creerCourrierArrivee($dg, 'administratif');
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();
        $courrier = app(CircuitCourrierMoteurService::class)->demarrer($courrier, $circuit, $dg);

        $this->actingAs($dg)
            ->get(route('courriers.show', $courrier, absolute: false))
            ->assertOk()
            ->assertSee('préparer un projet de note', false)
            ->assertDontSee('À payer avant le 30 du mois', false);
    }

    private function demarrerFacture(User $acteur): Courrier
    {
        $courrier = $this->creerCourrierArrivee($acteur, 'facture');
        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();

        return app(CircuitCourrierMoteurService::class)->demarrer($courrier, $circuit, $acteur);
    }

    private function creerDg(): User
    {
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'DG')->value('id')]);
        $user->assignRole('dg');

        return $user;
    }

    private function creerCourrierArrivee(User $user, string $typeCode): Courrier
    {
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'recu')->firstOrFail();
        $type = TypeCourrier::where('code', $typeCode)->firstOrFail();

        return Courrier::create([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => $type->id,
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => PrioriteCourrier::where('code', 'normale')->value('id'),
            'numero_registre' => 88,
            'numero_registre_annee' => 2026,
            'origine' => 'externe',
            'date_reception' => now()->toDateString(),
            'objet' => 'Facture test agent confié',
            'createur_id' => $user->id,
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
        ]);
    }
}
