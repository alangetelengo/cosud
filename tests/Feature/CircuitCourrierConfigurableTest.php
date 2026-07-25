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
        $this->assertSame('traitement_dossiers_vers_ac', $courrier->fresh()->circuitEtapeActuelle->code);
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

        // Chemin A : la particulière soumet uniquement le document — le circuit avance
        // vers la validation du DG, aucun départ n’est créé.
        $this->actingAs($particuliere)
            ->post(route('courriers.circuit.soumettre-reponse', $courrier, absolute: false), [
                'document_reponse' => UploadedFile::fake()->create('reponse.pdf', 30, 'application/pdf'),
                'objet' => 'Réponse favorable',
            ])
            ->assertRedirect();

        $courrier->refresh();
        $this->assertSame('validation_reponse_dg', $courrier->circuitEtapeActuelle->code);
        $this->assertNotNull($courrier->document_reponse_id);
        $this->assertNull($courrier->reponse_structure_destinataire_id);
        $this->assertNull(Courrier::where('courrier_parent_id', $courrier->id)->first());

        // Le DG valide et renvoie à la particulière (aucun départ créé à ce stade).
        $this->actingAs($dg)
            ->post(route('courriers.circuit.valider-reponse', $courrier, absolute: false), [])
            ->assertRedirect();

        $courrier->refresh();
        $this->assertSame('creation_depart_particuliere', $courrier->circuitEtapeActuelle->code);
        $this->assertNotNull($courrier->document_reponse_id);

        // Lucienne crée le courrier départ en brouillon avec le destinataire indiqué.
        $this->actingAs($particuliere)
            ->post(route('courriers.creer-reponse', $courrier, absolute: false), [
                'structure_destinataire_id' => $secDdsait->id,
            ])
            ->assertRedirect();

        $this->assertNull($courrier->fresh()->circuit_etape_actuelle_id);
        $reponse = Courrier::where('courrier_parent_id', $courrier->id)->firstOrFail();
        $this->assertSame('brouillon', $reponse->statutCourrier->code);
        $this->assertNull($reponse->signataire_id);
        $this->assertSame($secDdsait->id, $reponse->structure_destinataire_id);
        $this->assertSame($particuliere->id, $reponse->createur_id);
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

        // Sans ces champs interdits, la soumission fonctionne : la confidentialité est reprise
        // automatiquement du courrier (décidée à l'orientation), sans agent destinataire choisi.
        $this->actingAs($particuliere)
            ->post(route('courriers.circuit.soumettre-reponse', $courrier, absolute: false), [
                'document_reponse' => UploadedFile::fake()->create('reponse.pdf', 30, 'application/pdf'),
            ])
            ->assertRedirect();

        $courrier->refresh();
        $this->assertTrue((bool) $courrier->reponse_confidentielle);
        $this->assertNull($courrier->destinataire_agent_id);
        $this->assertSame('validation_reponse_dg', $courrier->circuitEtapeActuelle->code);

        // Le DG valide et renvoie à Lucienne ; c’est elle qui crée le départ (confidentiel → agent).
        $this->actingAs($dg)
            ->post(route('courriers.circuit.valider-reponse', $courrier, absolute: false), [])
            ->assertRedirect();

        $this->assertSame('creation_depart_particuliere', $courrier->fresh()->circuitEtapeActuelle->code);

        $this->actingAs($particuliere)
            ->post(route('courriers.creer-reponse', $courrier->fresh(), absolute: false), [
                'reponse_confidentielle' => '1',
                'destinataire_agent_id' => $agent->id,
            ])
            ->assertRedirect();

        $reponse = Courrier::where('courrier_parent_id', $courrier->id)->firstOrFail();
        $this->assertSame('brouillon', $reponse->statutCourrier->code);
        $this->assertSame($agent->id, $reponse->destinataire_agent_id);
        $this->assertNull($reponse->structure_destinataire_id);
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
                'objet' => 'Réponse favorable',
            ])
            ->assertRedirect();

        $courrier->refresh();
        $this->assertSame('validation_reponse_dg', $courrier->circuitEtapeActuelle->code);
        $documentReponseId = $courrier->document_reponse_id;

        // La particulière ne peut pas rejeter sa propre soumission : c’est réservé à l’acteur
        // de l’étape « validation_reponse_dg » (le DG).
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
        $this->assertNotNull($documentReponseId);
        $this->assertSame('Le destinataire proposé n’est pas correct.', $courrier->motif_rejet);
        $this->assertSame($dg->id, $courrier->rejete_par_id);
        $this->assertNull(Courrier::where('courrier_parent_id', $courrier->id)->first());
    }

    public function test_dg_ne_voit_pas_creer_depart_brouillon_apres_validation(): void
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

        // Après validation, c’est à la particulière de créer le départ : le DG ne doit plus
        // voir les boutons de création brouillon (même en mode « pas votre tour »).
        $this->actingAs($dg)
            ->get(route('courriers.show', $courrier->fresh(), absolute: false))
            ->assertOk()
            ->assertDontSee('Créer le courrier départ (brouillon)', false)
            ->assertDontSee('Créer le courrier départ', false)
            ->assertDontSee('Répondre moi-même et signer', false);

        $this->actingAs($particuliere)
            ->get(route('courriers.show', $courrier->fresh(), absolute: false))
            ->assertOk()
            ->assertSee('Créer le courrier départ (brouillon)', false);
    }

    public function test_dg_sur_circuit_facture_peut_repondre_lui_meme_et_cloturer_le_circuit(): void
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
            ->assertSee('Répondre moi-même et signer', false)
            ->assertSee('A — Instruire', false)
            ->assertDontSee('avant de pouvoir préparer une réponse', false);

        $this->actingAs($dg)
            ->post(route('courriers.creer-reponse', $courrier, absolute: false), [
                'signer_immediatement' => '1',
                'document_reponse' => UploadedFile::fake()->create('reponse-facture.pdf', 20, 'application/pdf'),
                'objet' => 'Réponse DG sur facture',
                'structure_destinataire_id' => $secDdsait->id,
            ])
            ->assertRedirect();

        $this->assertNull($courrier->fresh()->circuit_etape_actuelle_id);
        $reponse = Courrier::where('courrier_parent_id', $courrier->id)->firstOrFail();
        $this->assertSame('signe', $reponse->statutCourrier->code);
        $this->assertSame($dg->id, $reponse->signataire_id);
        // Clôture directe : pas de passage vers l’étape AC / chèque.
        $this->assertDatabaseHas('circuit_courrier_historiques', [
            'courrier_id' => $courrier->id,
            'evenement' => 'cloture_circuit',
        ]);
    }

    public function test_dg_peut_repondre_lui_meme_sans_passer_par_la_particuliere(): void
    {
        $dg = $this->creerDg();
        $secDdsait = Structure::where('code', 'SEC-DDSAIT')->firstOrFail();

        $courrier = $this->creerCourrierArrivee($dg, 'administratif');
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->instruire(
            $moteur->demarrer($courrier, $circuit, $dg),
            $dg,
            'Répondre favorablement.'
        );
        $this->assertSame('traitement_particuliere', $courrier->circuitEtapeActuelle->code);

        // Chemin B : le DG établit et signe lui-même la réponse, sans attendre la particulière
        // ni une validation séparée, avec le destinataire de son choix.
        $this->actingAs($dg)
            ->post(route('courriers.creer-reponse', $courrier, absolute: false), [
                'signer_immediatement' => '1',
                'document_reponse' => UploadedFile::fake()->create('reponse-dg.pdf', 20, 'application/pdf'),
                'objet' => 'Réponse directe du DG',
                'structure_destinataire_id' => $secDdsait->id,
            ])
            ->assertRedirect();

        $this->assertNull($courrier->fresh()->circuit_etape_actuelle_id);
        $reponse = Courrier::where('courrier_parent_id', $courrier->id)->firstOrFail();
        $this->assertSame('signe', $reponse->statutCourrier->code);
        $this->assertSame($dg->id, $reponse->signataire_id);
        $this->assertSame($secDdsait->id, $reponse->structure_destinataire_id);
        $this->assertSame('Réponse directe du DG', $reponse->objet);
    }

    public function test_dg_peut_repondre_de_maniere_confidentielle_a_un_agent_precis(): void
    {
        $dg = $this->creerDg();
        $agent = User::factory()->create(['structure_id' => Structure::where('code', 'DDSAIT')->value('id')]);
        $agent->assignRole('directeur');

        $courrier = $this->creerCourrierArrivee($dg, 'administratif');
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->instruire(
            $moteur->demarrer($courrier, $circuit, $dg),
            $dg,
            'Traiter en confidentiel.'
        );

        $this->actingAs($dg)
            ->post(route('courriers.creer-reponse', $courrier, absolute: false), [
                'signer_immediatement' => '1',
                'document_reponse' => UploadedFile::fake()->create('reponse-confidentielle.pdf', 20, 'application/pdf'),
                'objet' => 'Réponse confidentielle du DG',
                'reponse_confidentielle' => '1',
                'destinataire_agent_id' => $agent->id,
            ])
            ->assertRedirect();

        $reponse = Courrier::where('courrier_parent_id', $courrier->id)->firstOrFail();
        $this->assertSame('signe', $reponse->statutCourrier->code);
        $this->assertSame($agent->id, $reponse->destinataire_agent_id);
        $this->assertNull($reponse->structure_destinataire_id);

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
            ->assertSee('A — Instruire', false)
            ->assertSee('Répondre moi-même et signer', false);

        $moteur->instruire($courrier->fresh(), $dg, 'Traiter ce dossier.');

        $this->actingAs($dg)
            ->get(route('courriers.show', $courrier->fresh(), absolute: false))
            ->assertOk()
            ->assertSee('Soumettre le projet de réponse au DG', false);
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

    public function test_dg_voit_les_boutons_en_mode_degrade_une_fois_les_instructions_donnees(): void
    {
        $dg = $this->creerDg();
        $courrier = $this->creerCourrierArrivee($dg, 'administratif');
        $circuit = CircuitCourrier::where('code', 'courrier_general')->firstOrFail();

        $moteur = app(CircuitCourrierMoteurService::class);
        $moteur->demarrer($courrier, $circuit, $dg);
        $moteur->instruire($courrier->fresh(), $dg, 'Traiter ce dossier.');

        // Le circuit est désormais à l’étape « traitement_particuliere » : ce n’est plus le
        // tour du DG (aAccesTotal lui laisse tout de même le droit d’agir), donc l’UI doit le
        // signaler par un style dégradé + une confirmation renforcée plutôt que masquer l’action.
        $response = $this->actingAs($dg)
            ->get(route('courriers.show', $courrier->fresh(), absolute: false))
            ->assertOk();

        $response->assertSee('pas votre tour', false);
        $response->assertSee('Ce n’est pas votre tour dans le circuit', false);
        $response->assertSee('Préparer une réponse', false);
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

        // À cette étape, seule la soumission du projet de réponse (avec document + destinataire)
        // doit faire avancer le circuit : le bouton générique « Valider l’étape » (qui avancerait
        // sans aucune de ces informations) ne doit pas être proposé.
        $this->actingAs($particuliere)
            ->get(route('courriers.show', $courrier->fresh(), absolute: false))
            ->assertOk()
            ->assertDontSee('Valider l’étape', false)
            ->assertSee('Soumettre le projet de réponse au DG', false)
            ->assertSee('Projet de réponse à soumettre au DG', false)
            ->assertSee('Document de réponse', false)
            ->assertSee('Cette étape se termine par la soumission du projet de réponse au DG', false);
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
        ]);
    }
}
