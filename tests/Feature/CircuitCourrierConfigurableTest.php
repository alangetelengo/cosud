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
use Database\Seeders\CircuitCourrierSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Database\Seeders\TypeDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CircuitCourrierConfigurableTest extends TestCase
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

    public function test_seed_cree_circuits_facture_et_general(): void
    {
        $this->assertDatabaseHas('circuit_courriers', ['code' => 'facture_prestataire', 'actif' => true]);
        $this->assertDatabaseHas('circuit_courriers', ['code' => 'courrier_general', 'actif' => true]);

        $facture = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();
        $this->assertGreaterThanOrEqual(9, $facture->etapesActives()->count());
        $this->assertDatabaseHas('circuit_courrier_etapes', [
            'circuit_courrier_id' => $facture->id,
            'code' => 'preuve_paiement',
            'actif' => true,
        ]);
    }

    public function test_admin_peut_lister_les_circuits(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('parametres.circuits-courriers.index', absolute: false))
            ->assertOk()
            ->assertSee('Factures prestataires', false)
            ->assertSee('Courriers généraux', false);
    }

    public function test_demarrage_et_avancement_circuit_facture(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $courrier = $this->creerCourrierArrivee($admin, 'facture');
        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->demarrer($courrier, $circuit, $admin);

        $this->assertNotNull($courrier->circuit_etape_actuelle_id);
        // L’enregistrement est auto-validé à la création → étape instructions DG
        $this->assertSame('instructions_dg', $courrier->circuitEtapeActuelle->code);

        $courrier = $moteur->avancer($courrier, $admin, 'Instructions données');
        // Relais « Traitement dossiers → AC » validé automatiquement → étape AC.
        $this->assertSame('ac_etablit_cheque', $courrier->fresh()->circuitEtapeActuelle->code);
    }

    public function test_circuit_general_saute_automatiquement_l_etape_de_notification(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $courrier = $this->creerCourrierArrivee($admin, 'administratif');
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->demarrer($courrier, $circuit, $admin);

        $this->assertNotNull($courrier->circuit_etape_actuelle_id);
        // Enregistrement (auto) puis notification (auto, sans décision humaine) → on atterrit sur l'instruction DG.
        $this->assertSame('instruction_dg', $courrier->circuitEtapeActuelle->code);
    }

    public function test_dg_n_est_notifie_qu_une_seule_fois_malgre_l_enchainement_automatique(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $dg = $this->creerDg();

        $courrier = $this->creerCourrierArrivee($admin, 'administratif');
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();

        app(CircuitCourrierMoteurService::class)->demarrer($courrier, $circuit, $admin);

        // Un circuit est attaché : une seule notification « étape circuit », sur l’étape
        // réellement atteinte après l’enchaînement automatique (enregistrement, notification
        // auto-validés) — pas une par étape intermédiaire, et pas de notification
        // « enregistrement » redondante en plus.
        Notification::assertSentToTimes($dg, CourrierWorkflowNotification::class, 1);
        Notification::assertSentTo($dg, CourrierWorkflowNotification::class, function ($n) {
            return $n->type === CourrierNotificationService::ETAPE_CIRCUIT;
        });
    }

    public function test_dg_donne_ses_instructions_et_le_circuit_avance(): void
    {
        $dg = $this->creerDg();
        $courrier = $this->creerCourrierArrivee($dg, 'administratif');
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->demarrer($courrier, $circuit, $dg);

        $this->actingAs($dg)
            ->post(route('courriers.circuit.instruire', $courrier, absolute: false), [
                'instructions' => 'Traiter en priorité et transmettre au service concerné.',
            ])
            ->assertRedirect();

        $courrier->refresh();
        $this->assertSame('Traiter en priorité et transmettre au service concerné.', $courrier->instructions_dg);
        $this->assertSame('traitement_particuliere', $courrier->circuitEtapeActuelle->code);
    }

    public function test_creation_courrier_reponse_termine_automatiquement_le_circuit_general(): void
    {
        $dg = $this->creerDg();
        $particuliere = $this->creerParticuliere();
        $secDdsait = Structure::where('code', 'SEC-DDSAIT')->firstOrFail();

        $courrier = $this->creerCourrierArrivee($dg, 'administratif');
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->demarrer($courrier, $circuit, $dg);
        $courrier = $moteur->instruire($courrier, $dg, 'Répondre favorablement.');
        $this->assertSame('traitement_particuliere', $courrier->circuitEtapeActuelle->code);

        // Chemin A : la particulière crée le départ et le transmet pour signature.
        // Un objet libre posté ne doit pas être accepté (objet forcé côté serveur).
        $this->actingAs($particuliere)
            ->post(route('courriers.circuit.soumettre-reponse', $courrier, absolute: false), [
                'document_reponse' => UploadedFile::fake()->create('reponse.pdf', 30, 'application/pdf'),
                'objet' => 'projet de reponse en attente de validation',
            ])
            ->assertSessionHasErrors('objet');

        $this->actingAs($particuliere)
            ->post(route('courriers.circuit.soumettre-reponse', $courrier, absolute: false), [
                'document_reponse' => UploadedFile::fake()->create('reponse.pdf', 30, 'application/pdf'),
            ])
            ->assertRedirect();

        $courrier->refresh();
        $this->assertSame('validation_reponse_dg', $courrier->circuitEtapeActuelle->code);
        $reponse = Courrier::where('courrier_parent_id', $courrier->id)->firstOrFail();
        $this->assertSame('transmis_directeur', $reponse->statutCourrier->code);
        $this->assertSame($dg->id, $reponse->directeur_en_attente_id);
        $this->assertSame('Réponse — Dossier test circuit', $reponse->objet);

        // Le DG signe — la particulière peut ensuite expédier.
        $this->actingAs($dg)
            ->post(route('courriers.circuit.valider-reponse', $courrier, absolute: false), [])
            ->assertRedirect();

        $courrier->refresh();
        $reponse->refresh();
        $this->assertSame('expedition_reponse', $courrier->circuitEtapeActuelle->code);
        $this->assertSame('signe', $reponse->statutCourrier->code);
        $this->assertSame($dg->id, $reponse->signataire_id);

        // Expédition unique : clôture l’arrivée + circuit.
        $this->actingAs($particuliere)
            ->post(route('courriers.expedier-interne', $reponse, absolute: false), [
                'structure_destinataire_id' => $secDdsait->id,
                'numero_archives' => 'DG/DEP/2026/020',
            ])
            ->assertRedirect();

        $this->assertSame('expedie', $reponse->fresh()->statutCourrier->code);
        $this->assertSame('cloture', $courrier->fresh()->statutCourrier->code);
        $this->assertNull($courrier->fresh()->circuit_etape_actuelle_id);
        $this->assertSame($secDdsait->id, $reponse->fresh()->structure_destinataire_id);
    }

    public function test_soumission_reponse_notifie_le_dg_une_seule_fois(): void
    {
        Notification::fake();

        $dg = $this->creerDg();
        $particuliere = $this->creerParticuliere();
        $courrier = $this->creerCourrierArrivee($dg, 'administratif');
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->instruire($moteur->demarrer($courrier, $circuit, $dg), $dg, 'Préparer la note.');

        $this->actingAs($particuliere)
            ->post(route('courriers.circuit.soumettre-reponse', $courrier, absolute: false), [
                'document_reponse' => UploadedFile::fake()->create('note.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        Notification::assertSentToTimes($dg, CourrierWorkflowNotification::class, 1);
        Notification::assertSentTo($dg, CourrierWorkflowNotification::class, function ($n) {
            return $n->type === CourrierNotificationService::REPONSE_A_VALIDER;
        });
    }

    public function test_flash_succes_courrier_utilise_le_bandeau_avec_icone_et_fermeture(): void
    {
        $dg = $this->creerDg();
        $courrier = $this->creerCourrierArrivee($dg, 'administratif');
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();
        $courrier = app(CircuitCourrierMoteurService::class)->demarrer($courrier, $circuit, $dg);

        $this->actingAs($dg)
            ->from(route('courriers.show', $courrier, absolute: false))
            ->post(route('courriers.circuit.instruire', $courrier, absolute: false), [
                'instructions' => 'Préparer une note de stage.',
            ])
            ->assertRedirect();

        $this->actingAs($dg)
            ->withSession(['success' => 'Instructions enregistrées et transmises pour traitement.'])
            ->get(route('courriers.show', $courrier->fresh(), absolute: false))
            ->assertOk()
            ->assertSee('Instructions enregistrées et transmises pour traitement.', false)
            ->assertSee('rounded-2xl bg-emerald-50', false)
            ->assertSee('aria-label="Fermer"', false)
            ->assertSee('✓', false);
    }

    public function test_particuliere_ne_choisit_ni_confidentialite_ni_destinataire_precis_sur_courrier_confidentiel(): void
    {
        $dg = $this->creerDg();
        $particuliere = $this->creerParticuliere();
        $agent = User::factory()->create(['structure_id' => Structure::where('code', 'DDSAIT')->value('id')]);
        $agent->assignRole('directeur');

        $courrier = $this->creerCourrierArrivee($dg, 'administratif');
        $courrier->update(['est_confidentiel' => true]);
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->instruire($moteur->demarrer($courrier->fresh(), $circuit, $dg), $dg, 'À traiter en confidentiel.');

        // La particulière ne peut pas décider la confidentialité ni le destinataire
        // (structure ou agent) : ces champs sont rejetés même si elle tente de les forcer.
        $this->actingAs($particuliere)
            ->post(route('courriers.circuit.soumettre-reponse', $courrier, absolute: false), [
                'document_reponse' => UploadedFile::fake()->create('reponse.pdf', 30, 'application/pdf'),
                'reponse_confidentielle' => '0',
                'destinataire_agent_id' => $agent->id,
                'structure_destinataire_id' => Structure::where('code', 'SEC-DDSAIT')->value('id'),
            ])
            ->assertSessionHasErrors(['reponse_confidentielle', 'destinataire_agent_id', 'structure_destinataire_id']);

        // Sans ces champs interdits, la soumission crée le départ en attente de signature.
        $this->actingAs($particuliere)
            ->post(route('courriers.circuit.soumettre-reponse', $courrier, absolute: false), [
                'document_reponse' => UploadedFile::fake()->create('reponse.pdf', 30, 'application/pdf'),
            ])
            ->assertRedirect();

        $courrier->refresh();
        $this->assertSame('validation_reponse_dg', $courrier->circuitEtapeActuelle->code);
        $reponse = Courrier::where('courrier_parent_id', $courrier->id)->firstOrFail();
        $this->assertSame('transmis_directeur', $reponse->statutCourrier->code);

        $this->actingAs($dg)
            ->post(route('courriers.circuit.valider-reponse', $courrier, absolute: false), [])
            ->assertRedirect();

        $this->assertSame('expedition_reponse', $courrier->fresh()->circuitEtapeActuelle->code);
        $this->assertSame('signe', $reponse->fresh()->statutCourrier->code);
        $this->assertSame($dg->id, $reponse->fresh()->signataire_id);
    }

    public function test_dg_rejette_le_projet_de_reponse_et_le_circuit_retourne_a_traitement_particuliere(): void
    {
        $dg = $this->creerDg();
        $particuliere = $this->creerParticuliere();

        $courrier = $this->creerCourrierArrivee($dg, 'administratif');
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->demarrer($courrier, $circuit, $dg);
        $courrier = $moteur->instruire($courrier, $dg, 'Répondre favorablement.');

        $this->actingAs($particuliere)
            ->post(route('courriers.circuit.soumettre-reponse', $courrier, absolute: false), [
                'document_reponse' => UploadedFile::fake()->create('reponse.pdf', 30, 'application/pdf'),
            ])
            ->assertRedirect();

        $courrier->refresh();
        $this->assertSame('validation_reponse_dg', $courrier->circuitEtapeActuelle->code);
        $reponse = Courrier::where('courrier_parent_id', $courrier->id)->firstOrFail();

        // La particulière ne peut pas rejeter : c’est réservé au DG.
        $this->actingAs($particuliere)
            ->post(route('courriers.circuit.rejeter-reponse', $courrier, absolute: false), [
                'motif_rejet' => 'Tentative non autorisée',
            ])
            ->assertForbidden();

        $this->actingAs($dg)
            ->post(route('courriers.circuit.rejeter-reponse', $courrier, absolute: false), [
                'motif_rejet' => 'Le destinataire proposé n’est pas correct.',
            ])
            ->assertRedirect();

        $courrier->refresh();
        $this->assertSame('traitement_particuliere', $courrier->circuitEtapeActuelle->code);
        $this->assertNull($courrier->document_reponse_id);
        $this->assertSame('Le destinataire proposé n’est pas correct.', $courrier->motif_rejet);
        $this->assertSame($dg->id, $courrier->rejete_par_id);
        $this->assertSame('rejete_directeur', $reponse->fresh()->statutCourrier->code);
    }

    public function test_apres_signature_particuliere_voit_lexpedition_pas_creer_depart(): void
    {
        $dg = $this->creerDg();
        $particuliere = $this->creerParticuliere();

        $courrier = $this->creerCourrierArrivee($dg, 'administratif');
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->instruire($moteur->demarrer($courrier, $circuit, $dg), $dg, 'Répondre.');
        $this->actingAs($particuliere)
            ->post(route('courriers.circuit.soumettre-reponse', $courrier, absolute: false), [
                'document_reponse' => UploadedFile::fake()->create('reponse.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();
        $this->actingAs($dg)
            ->post(route('courriers.circuit.valider-reponse', $courrier->fresh(), absolute: false), [])
            ->assertRedirect();

        $this->actingAs($dg)
            ->get(route('courriers.show', $courrier->fresh(), absolute: false))
            ->assertOk()
            ->assertDontSee('Créer le courrier départ (signé)', false)
            ->assertDontSee('Répondre moi-même et signer', false);

        $this->actingAs($particuliere)
            ->get(route('courriers.show', $courrier->fresh(), absolute: false))
            ->assertOk()
            ->assertSee('Ouvrir le départ pour l’expédier', false)
            ->assertDontSee('Créer le courrier départ (signé)', false);
    }

    public function test_dg_sur_circuit_facture_ne_peut_pas_repondre_lui_meme(): void
    {
        $dg = $this->creerDg();
        $secDdsait = Structure::where('code', 'SEC-DDSAIT')->firstOrFail();

        $courrier = $this->creerCourrierArrivee($dg, 'facture');
        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->demarrer($courrier, $circuit, $dg);
        $this->assertSame('instructions_dg', $courrier->circuitEtapeActuelle->code);

        $this->actingAs($dg)
            ->get(route('courriers.show', $courrier, absolute: false))
            ->assertOk()
            ->assertSee('A — Instruire le dossier', false)
            ->assertDontSee('Répondre moi-même et signer', false)
            ->assertDontSee('avant de pouvoir préparer une réponse', false);

        $this->actingAs($dg)
            ->post(route('courriers.creer-reponse', $courrier, absolute: false), [
                'signer_immediatement' => '1',
                'document_reponse' => UploadedFile::fake()->create('reponse-facture.pdf', 20, 'application/pdf'),
                'structure_destinataire_id' => $secDdsait->id,
            ])
            ->assertForbidden();

        $this->assertSame('instructions_dg', $courrier->fresh()->circuitEtapeActuelle?->code);
        $this->assertNull(Courrier::where('courrier_parent_id', $courrier->id)->first());
    }

    public function test_dg_peut_repondre_lui_meme_sans_passer_par_la_particuliere(): void
    {
        $dg = $this->creerDg();
        $secDdsait = Structure::where('code', 'SEC-DDSAIT')->firstOrFail();

        $courrier = $this->creerCourrierArrivee($dg, 'administratif');
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->demarrer($courrier, $circuit, $dg);
        $this->assertTrue(in_array($courrier->circuitEtapeActuelle->code, ['instruction_dg', 'instructions_dg'], true)
            || $courrier->circuitEtapeActuelle->action === 'instruire');

        // Chemin B : uniquement à l’étape d’instructions — le DG établit et signe lui-même.
        $this->actingAs($dg)
            ->post(route('courriers.creer-reponse', $courrier, absolute: false), [
                'signer_immediatement' => '1',
                'document_reponse' => UploadedFile::fake()->create('reponse-dg.pdf', 20, 'application/pdf'),
                'structure_destinataire_id' => $secDdsait->id,
            ])
            ->assertRedirect();

        $this->assertNull($courrier->fresh()->circuit_etape_actuelle_id);
        $reponse = Courrier::where('courrier_parent_id', $courrier->id)->firstOrFail();
        $this->assertSame('signe', $reponse->statutCourrier->code);
        $this->assertSame($dg->id, $reponse->signataire_id);
        $this->assertSame($secDdsait->id, $reponse->structure_destinataire_id);
        $this->assertSame('Réponse — Dossier test circuit', $reponse->objet);
    }

    public function test_dg_peut_repondre_de_maniere_confidentielle_a_un_agent_precis(): void
    {
        $dg = $this->creerDg();
        $agent = User::factory()->create(['structure_id' => Structure::where('code', 'DDSAIT')->value('id')]);
        $agent->assignRole('directeur');

        $courrier = $this->creerCourrierArrivee($dg, 'administratif');
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->demarrer($courrier, $circuit, $dg);

        $this->actingAs($dg)
            ->post(route('courriers.creer-reponse', $courrier, absolute: false), [
                'signer_immediatement' => '1',
                'document_reponse' => UploadedFile::fake()->create('reponse-confidentielle.pdf', 20, 'application/pdf'),
                'reponse_confidentielle' => '1',
                'destinataire_agent_id' => $agent->id,
            ])
            ->assertRedirect();

        $reponse = Courrier::where('courrier_parent_id', $courrier->id)->firstOrFail();
        $this->assertSame('signe', $reponse->statutCourrier->code);
        $this->assertSame($agent->id, $reponse->destinataire_agent_id);
        $this->assertNull($reponse->structure_destinataire_id);
        $this->assertSame('Réponse — Dossier test circuit', $reponse->objet);

        // L’agent destinataire confidentiel doit pouvoir consulter son courrier départ.
        $this->actingAs($agent)
            ->get(route('courriers.show', $reponse, absolute: false))
            ->assertOk();
    }

    public function test_relancer_masque_sur_etape_dg_circuit_general(): void
    {
        $dg = $this->creerDg();
        $courrier = $this->creerCourrierArrivee($dg, 'administratif');
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();
        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->demarrer($courrier, $circuit, $dg);

        // Étape d’instruction DG : pas de relance (ce serait se relancer soi-même).
        $this->assertTrue(in_array($courrier->circuitEtapeActuelle->code, ['instruction_dg', 'instructions_dg'], true)
            || $courrier->circuitEtapeActuelle->action === 'instruire');

        $this->actingAs($dg)
            ->get(route('courriers.show', $courrier, absolute: false))
            ->assertOk()
            ->assertDontSee('Relancer le responsable', false);

        $courrier = $moteur->instruire($courrier, $dg, 'Préparer une réponse.');
        $this->assertSame('traitement_particuliere', $courrier->circuitEtapeActuelle->code);

        $this->actingAs($dg)
            ->get(route('courriers.show', $courrier, absolute: false))
            ->assertOk()
            ->assertSee('Relancer le responsable', false);
    }

    public function test_bouton_creer_reponse_masque_tant_que_les_instructions_dg_ne_sont_pas_donnees(): void
    {
        $dg = $this->creerDg();
        $courrier = $this->creerCourrierArrivee($dg, 'administratif');
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();

        $moteur = app(CircuitCourrierMoteurService::class);
        $moteur->demarrer($courrier, $circuit, $dg);

        $this->actingAs($dg)
            ->get(route('courriers.show', $courrier, absolute: false))
            ->assertOk()
            ->assertDontSee('Soumettre le projet de réponse au DG', false)
            ->assertDontSee('Transmettre pour signature', false)
            ->assertSee('A — Instruire', false)
            ->assertSee('Répondre moi-même et signer', false);

        $moteur->instruire($courrier->fresh(), $dg, 'Traiter ce dossier.');

        $this->actingAs($dg)
            ->get(route('courriers.show', $courrier->fresh(), absolute: false))
            ->assertOk()
            ->assertDontSee('Répondre moi-même et signer', false)
            ->assertDontSee('Préparer une réponse', false);
    }

    public function test_secretaire_ne_voit_pas_le_message_dg_tant_que_les_instructions_ne_sont_pas_donnees(): void
    {
        $secretaire = $this->creerSecretaire();
        $courrier = $this->creerCourrierArrivee($secretaire, 'facture');
        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();

        app(CircuitCourrierMoteurService::class)->demarrer($courrier, $circuit, $secretaire);
        $this->assertSame('instructions_dg', $courrier->fresh()->circuitEtapeActuelle->code);

        $this->actingAs($secretaire)
            ->get(route('courriers.show', $courrier->fresh(), absolute: false))
            ->assertOk()
            ->assertSee('En attente des instructions du DG / directeur.', false)
            ->assertDontSee('A — Instruire', false)
            ->assertDontSee('Répondre moi-même et signer', false);
    }

    public function test_dg_ne_voit_plus_preparer_ni_repondre_apres_avoir_instruit(): void
    {
        $dg = $this->creerDg();
        $courrier = $this->creerCourrierArrivee($dg, 'administratif');
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();

        $moteur = app(CircuitCourrierMoteurService::class);
        $moteur->demarrer($courrier, $circuit, $dg);
        $moteur->instruire($courrier->fresh(), $dg, 'Traiter ce dossier.');

        // Après instruction, c’est le tour de la particulière : le DG ne doit plus voir
        // « Préparer une réponse » ni « Répondre moi-même », seulement la relance.
        $this->actingAs($dg)
            ->get(route('courriers.show', $courrier->fresh(), absolute: false))
            ->assertOk()
            ->assertDontSee('Préparer une réponse', false)
            ->assertDontSee('Répondre moi-même et signer', false)
            ->assertSee('Relancer le responsable', false);
    }

    public function test_particuliere_ne_voit_pas_le_mode_degrade_quand_c_est_son_tour(): void
    {
        $dg = $this->creerDg();
        $particuliere = $this->creerParticuliere();
        $courrier = $this->creerCourrierArrivee($dg, 'administratif');
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();

        $moteur = app(CircuitCourrierMoteurService::class);
        $moteur->demarrer($courrier, $circuit, $dg);
        $moteur->instruire($courrier->fresh(), $dg, 'Traiter ce dossier.');

        $this->actingAs($particuliere)
            ->get(route('courriers.show', $courrier->fresh(), absolute: false))
            ->assertOk()
            ->assertDontSee('pas votre tour', false);
    }

    public function test_traitement_particuliere_ne_propose_pas_de_validation_generique_de_l_etape(): void
    {
        $dg = $this->creerDg();
        $particuliere = $this->creerParticuliere();
        $courrier = $this->creerCourrierArrivee($dg, 'administratif');
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();

        $moteur = app(CircuitCourrierMoteurService::class);
        $moteur->demarrer($courrier, $circuit, $dg);
        $moteur->instruire($courrier->fresh(), $dg, 'Traiter ce dossier.');

        // À cette étape, seule la transmission du courrier de réponse pour signature
        // doit faire avancer le circuit : le bouton générique « Valider l’étape » ne doit pas être proposé.
        $this->actingAs($particuliere)
            ->get(route('courriers.show', $courrier->fresh(), absolute: false))
            ->assertOk()
            ->assertDontSee('Valider l’étape', false)
            ->assertSee('Transmettre pour signature', false)
            ->assertSee('Courrier de réponse à transmettre pour signature', false)
            ->assertSee('Document', false)
            ->assertSee('Cette étape se termine en transmettant le courrier de réponse pour signature', false);
    }

    public function test_correction_enregistrement_est_repliee_dans_autres_actions(): void
    {
        $dg = $this->creerDg();
        $particuliere = $this->creerParticuliere();
        $courrier = $this->creerCourrierArrivee($dg, 'administratif');
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();

        $moteur = app(CircuitCourrierMoteurService::class);
        $moteur->demarrer($courrier, $circuit, $dg);
        $moteur->instruire($courrier->fresh(), $dg, 'Traiter ce dossier.');

        // La correction d’enregistrement ne doit plus apparaître comme une action principale
        // (elle n’est pas une étape du circuit) : elle est reléguée sous « Autres actions »,
        // repliée par défaut, avec un intitulé explicite.
        $this->actingAs($particuliere)
            ->get(route('courriers.show', $courrier->fresh(), absolute: false))
            ->assertOk()
            ->assertSee('Autres actions', false)
            ->assertSee('Corriger l’enregistrement', false)
            ->assertSee('x-show="autresActions"', false)
            ->assertDontSee('>Modifier</a>', false);
    }

    public function test_instruction_circuit_general_route_vers_le_directeur_de_la_structure_destinataire(): void
    {
        Notification::fake();

        $dg = $this->creerDg();
        $ddsait = Structure::where('code', 'DDSAIT')->firstOrFail();
        $directeurDdsait = User::factory()->create(['structure_id' => $ddsait->id]);
        $directeurDdsait->assignRole('directeur');

        $secretaire = $this->creerSecretaire();
        $courrier = $this->creerCourrierArrivee($secretaire, 'administratif');
        $courrier->update(['structure_destinataire_id' => $ddsait->id]);
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->demarrer($courrier->fresh(), $circuit, $secretaire);

        $this->assertSame('instruction_dg', $courrier->circuitEtapeActuelle->code);

        // Le courrier est adressé à la direction DDSAIT : c’est le directeur de cette
        // direction qui est l’acteur attendu (le DG garde néanmoins son accès total,
        // au même titre qu’un administrateur, et peut agir en cas de besoin).
        $this->assertSame($directeurDdsait->id, $moteur->resoudreActeurDirecteur($courrier)?->id);
        $this->assertTrue($moteur->peutAgir($courrier, $directeurDdsait));

        Notification::assertSentTo($directeurDdsait, CourrierWorkflowNotification::class, function ($n) {
            return $n->type === CourrierNotificationService::ETAPE_CIRCUIT;
        });
        Notification::assertNotSentTo($dg, CourrierWorkflowNotification::class, function ($n) {
            return $n->type === CourrierNotificationService::ETAPE_CIRCUIT;
        });

        $courrier = $moteur->instruire($courrier, $directeurDdsait, 'Traiter et transmettre au service concerné.');
        $this->assertSame('traitement_particuliere', $courrier->circuitEtapeActuelle->code);
    }

    public function test_instruction_circuit_general_retombe_sur_le_dg_sans_destinataire_precise(): void
    {
        $dg = $this->creerDg();
        $courrier = $this->creerCourrierArrivee($dg, 'administratif');
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->demarrer($courrier, $circuit, $dg);

        $this->assertSame('instruction_dg', $courrier->circuitEtapeActuelle->code);
        $this->assertTrue($moteur->peutAgir($courrier, $dg));
        $this->assertSame($dg->id, $moteur->resoudreActeurDirecteur($courrier)?->id);
    }

    public function test_directeur_destinataire_peut_instruire_via_http(): void
    {
        $ddsait = Structure::where('code', 'DDSAIT')->firstOrFail();
        $directeurDdsait = User::factory()->create(['structure_id' => $ddsait->id]);
        $directeurDdsait->assignRole('directeur');

        $secretaire = $this->creerSecretaire();
        $courrier = $this->creerCourrierArrivee($secretaire, 'administratif');
        $courrier->update(['structure_destinataire_id' => $ddsait->id]);
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();

        app(CircuitCourrierMoteurService::class)->demarrer($courrier->fresh(), $circuit, $secretaire);

        $this->actingAs($directeurDdsait)
            ->post(route('courriers.circuit.instruire', $courrier, absolute: false), [
                'instructions' => 'À traiter par le service technique.',
            ])
            ->assertRedirect();

        $courrier->refresh();
        $this->assertSame('À traiter par le service technique.', $courrier->instructions_dg);
        $this->assertSame('traitement_particuliere', $courrier->circuitEtapeActuelle->code);
    }

    public function test_creation_courrier_resout_le_circuit_depuis_le_type_via_http(): void
    {
        $user = $this->creerSecretaire();
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();
        $type = TypeCourrier::where('code', 'administratif')->firstOrFail();

        $this->actingAs($user)
            ->post(route('courriers.store', absolute: false), [
                'sens' => 'arrivee',
                'numero_fulgurant' => 'REG-0932bed9/2026',
                'objet' => 'Note de service test circuit',
                'expediteur_libelle' => 'Service X',
                'date_reception' => now()->toDateString(),
                'type_courrier_id' => $type->id,
                'fichier' => UploadedFile::fake()->create('scan.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect();

        $courrier = Courrier::where('objet', 'Note de service test circuit')->firstOrFail();
        $this->assertSame($circuit->id, (int) $courrier->circuit_courrier_id);
        $this->assertNotNull($courrier->circuit_etape_actuelle_id);
    }

    public function test_creation_depart_n_attache_jamais_de_circuit(): void
    {
        $user = $this->creerSecretaire();
        $type = TypeCourrier::where('code', 'administratif')->firstOrFail();

        $this->actingAs($user)
            ->post(route('courriers.store', absolute: false), [
                'sens' => 'depart',
                'objet' => 'Note départ sans circuit arrivée',
                'type_courrier_id' => $type->id,
            ])
            ->assertRedirect();

        $courrier = Courrier::where('objet', 'Note départ sans circuit arrivée')->firstOrFail();
        $this->assertNull($courrier->circuit_courrier_id);
        $this->assertNull($courrier->circuit_etape_actuelle_id);

        $this->actingAs($user)
            ->get(route('courriers.show', $courrier, absolute: false))
            ->assertOk()
            ->assertDontSee('Valider l’étape', false)
            ->assertDontSee('Courriers généraux / notes / instructions', false);
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
            'numero_registre' => 99,
            'numero_registre_annee' => (int) now()->year,
            'origine' => Courrier::ORIGINE_EXTERNE,
            'date_reception' => now()->toDateString(),
            'expediteur_libelle' => 'Fournisseur test',
            'objet' => 'Dossier test circuit',
            'createur_id' => $user->id,
            'structure_id' => $user->structure_id,
            'service_demandeur_structure_id' => in_array($typeCode, ['facture', 'mad'], true)
                ? Structure::where('code', 'DAF')->value('id')
                : null,
        ]);
    }
}
