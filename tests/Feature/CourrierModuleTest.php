<?php

namespace Tests\Feature;

use App\Models\Courrier;
use App\Models\CourrierVentilationDestinataire;
use App\Models\Document;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\Structure;
use App\Models\TypeDocument;
use App\Models\User;
use App\Notifications\CourrierWorkflowNotification;
use App\Services\CourrierFilService;
use App\Services\CourrierNotificationService;
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

class CourrierModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleAndPermissionSeeder::class,
            StructureSeeder::class,
            StatutDocumentSeeder::class,
            TypeDocumentSeeder::class,
            CourrierReferentielSeeder::class,
        ]);
    }

    public function test_utilisateur_standard_ne_peut_pas_acceder_aux_courriers(): void
    {
        $user = User::factory()->create();
        $user->assignRole('utilisateur');

        $this->actingAs($user)
            ->get(route('courriers.index', ['sens' => 'arrivee'], absolute: false))
            ->assertForbidden();
    }

    public function test_secretaire_peut_lister_courriers(): void
    {
        $user = $this->creerSecretaire();

        $this->actingAs($user)
            ->get(route('courriers.index', ['sens' => 'arrivee'], absolute: false))
            ->assertOk()
            ->assertSee('Courriers', false);
    }

    public function test_creation_courrier_arrivee_externe_exige_scan(): void
    {
        $user = $this->creerSecretaire();

        $this->actingAs($user)
            ->post(route('courriers.store', absolute: false), [
                'sens' => 'arrivee',
                'objet' => 'Demande sans scan',
            ])
            ->assertSessionHasErrors('fichier');
    }

    public function test_creation_courrier_arrivee_avec_scan(): void
    {
        Storage::fake('public');
        $user = $this->creerSecretaire();
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();

        $this->actingAs($user)
            ->post(route('courriers.store', absolute: false), [
                'sens' => 'arrivee',
                'objet' => 'Demande de renseignements',
                'expediteur_libelle' => 'Ministère X',
                'date_reception' => now()->toDateString(),
                'fichier' => UploadedFile::fake()->create('courrier.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('courriers', [
            'sens_courrier_id' => $sens->id,
            'objet' => 'Demande de renseignements',
            'origine' => 'externe',
            'numero_registre' => 1,
        ]);
    }

    public function test_workflow_depart_secretaire_directeur_expedition_reception(): void
    {
        $secDir = Structure::where('code', 'SEC-DIR')->firstOrFail();
        $secDdsait = Structure::where('code', 'SEC-DDSAIT')->firstOrFail();
        $dg = Structure::where('code', 'DG')->firstOrFail();

        $secretaire = User::factory()->create(['structure_id' => $secDir->id]);
        $secretaire->assignRole('secretaire_direction');

        $directeur = User::factory()->create(['structure_id' => $dg->id]);
        $directeur->assignRole('directeur');

        $secretaireDest = User::factory()->create(['structure_id' => $secDdsait->id]);
        $secretaireDest->assignRole('secretaire_direction');

        $this->actingAs($secretaire)
            ->post(route('courriers.store', absolute: false), [
                'sens' => 'depart',
                'objet' => 'Transmission interne DDSAIT',
            ])
            ->assertRedirect();

        $depart = Courrier::where('objet', 'Transmission interne DDSAIT')->firstOrFail();
        $this->assertSame('brouillon', $depart->statutCourrier->code);
        $this->assertNull($depart->structure_destinataire_id);

        $this->actingAs($secretaire)
            ->post(route('courriers.transmettre-directeur', $depart, absolute: false))
            ->assertRedirect();

        $depart->refresh();
        $this->assertSame('transmis_directeur', $depart->statutCourrier->code);
        $this->assertSame($directeur->id, $depart->directeur_en_attente_id);

        $this->actingAs($directeur)
            ->post(route('courriers.signer', $depart, absolute: false))
            ->assertRedirect();

        $depart->refresh();
        $this->assertSame('signe', $depart->statutCourrier->code);

        Notification::fake();

        $this->actingAs($secretaire)
            ->post(route('courriers.expedier-interne', $depart, absolute: false), [
                'structure_destinataire_id' => $secDdsait->id,
                'numero_archives' => 'DG/DEP/2026/001',
                'observations' => 'Transmission vers DDSAIT',
            ])
            ->assertRedirect();

        $depart->refresh();
        $this->assertSame('expedie', $depart->statutCourrier->code);
        $this->assertSame($secDdsait->id, $depart->structure_destinataire_id);
        $this->assertSame('DG/DEP/2026/001', $depart->numero_archives);
        $this->assertSame('Transmission vers DDSAIT', $depart->observations);

        Notification::assertSentTo(
            $secretaireDest,
            CourrierWorkflowNotification::class,
            function (CourrierWorkflowNotification $n) use ($depart, $secretaireDest) {
                if ($n->type !== CourrierNotificationService::EXPEDITION) {
                    return false;
                }
                $payload = $n->toArray($secretaireDest);

                return str_contains($payload['url'], 'a-recevoir')
                    && str_contains($payload['url'], 'highlight='.$depart->id);
            }
        );

        $this->actingAs($secretaireDest)
            ->get(route('courriers.a-recevoir', ['highlight' => $depart->id], absolute: false))
            ->assertOk()
            ->assertSee('Transmission interne DDSAIT', false)
            ->assertSee('courrier-'.$depart->id, false);

        $this->actingAs($secretaireDest)
            ->post(route('courriers.accepter-reception', $depart, absolute: false))
            ->assertRedirect();

        $depart->refresh();
        $this->assertNotNull($depart->courrier_arrivee_lie_id);

        $arrivee = Courrier::find($depart->courrier_arrivee_lie_id);
        $this->assertSame('interne', $arrivee->origine);
        $this->assertSame($depart->id, $arrivee->courrier_depart_source_id);
    }

    public function test_directeur_peut_rejeter_courrier_depart(): void
    {
        $secDir = Structure::where('code', 'SEC-DIR')->firstOrFail();
        $secDdsait = Structure::where('code', 'SEC-DDSAIT')->firstOrFail();
        $dg = Structure::where('code', 'DG')->firstOrFail();

        $secretaire = User::factory()->create(['structure_id' => $secDir->id]);
        $secretaire->assignRole('secretaire_direction');

        $directeur = User::factory()->create(['structure_id' => $dg->id]);
        $directeur->assignRole('directeur');

        $depart = Courrier::create([
            'sens_courrier_id' => SensCourrier::where('code', 'depart')->value('id'),
            'statut_courrier_id' => StatutCourrier::where('code', 'transmis_directeur')->value('id'),
            'numero_registre' => 1,
            'numero_registre_annee' => (int) now()->format('Y'),
            'objet' => 'À rejeter',
            'directeur_en_attente_id' => $directeur->id,
            'createur_id' => $secretaire->id,
            'structure_id' => $secDir->id,
        ]);

        $this->actingAs($directeur)
            ->post(route('courriers.rejeter-depart', $depart, absolute: false), [
                'motif_rejet' => 'Informations incomplètes',
            ])
            ->assertRedirect();

        $depart->refresh();
        $this->assertSame('rejete_directeur', $depart->statutCourrier->code);
        $this->assertSame('Informations incomplètes', $depart->motif_rejet);
    }

    public function test_directeur_en_attente_peut_voir_document_courrier_depart(): void
    {
        $secDir = Structure::where('code', 'SEC-DIR')->firstOrFail();
        $dg = Structure::where('code', 'DG')->firstOrFail();

        $secretaire = User::factory()->create(['structure_id' => $secDir->id]);
        $secretaire->assignRole('secretaire_direction');

        $directeur = User::factory()->create(['structure_id' => $dg->id]);
        $directeur->assignRole('directeur');

        $typeDocId = TypeDocument::where('code', 'LETTRE')->value('id')
            ?? TypeDocument::where('code', 'COURRIER_OUT')->value('id');

        $document = Document::create([
            'type_document_id' => $typeDocId,
            'proprietaire_id' => $secretaire->id,
            'createur_id' => $secretaire->id,
            'user_id' => $secretaire->id,
            'nom_original' => 'rapport-atelier.docx',
            'chemin' => 'documents/parapheur-depart/rapport.docx',
            'extension' => 'docx',
            'taille_octets' => 2048,
            'statut' => 'brouillon',
            'confidentiel' => false,
            'en_corbeille' => false,
        ]);

        $depart = Courrier::create([
            'sens_courrier_id' => SensCourrier::where('code', 'depart')->value('id'),
            'statut_courrier_id' => StatutCourrier::where('code', 'transmis_directeur')->value('id'),
            'numero_registre' => 60,
            'numero_registre_annee' => (int) now()->format('Y'),
            'objet' => 'Rapport atelier',
            'createur_id' => $secretaire->id,
            'structure_id' => $secDir->id,
            'directeur_en_attente_id' => $directeur->id,
        ]);
        $depart->documents()->attach($document->id);

        $this->assertTrue($document->fresh()->visiblePar($directeur));
        $this->assertTrue($directeur->can('view', $document));
        // Le scope de liste/recherche doit lui aussi exposer ce document au directeur en attente
        // de signature (auparavant absent de scopeVisibleBy, malgré un accès individuel valide).
        $this->assertTrue(Document::visibleBy($directeur)->whereKey($document->id)->exists());

        $this->actingAs($directeur)
            ->get(route('courriers.show', $depart, absolute: false))
            ->assertOk()
            ->assertSee('rapport-atelier.docx', false)
            ->assertDontSee('accès restreint', false);
    }

    public function test_ventilation_donne_acces_piece_seule_au_document(): void
    {
        $user = User::factory()->create();
        $user->assignRole('utilisateur');

        $directeur = User::factory()->create();
        $directeur->assignRole('directeur');

        $typeDocId = TypeDocument::where('code', 'COURRIER_IN')->value('id');
        $document = Document::create([
            'type_document_id' => $typeDocId,
            'proprietaire_id' => $directeur->id,
            'createur_id' => $directeur->id,
            'user_id' => $directeur->id,
            'nom_original' => 'piece-ventilee.pdf',
            'chemin' => 'documents/test.pdf',
            'extension' => 'pdf',
            'taille_octets' => 1024,
            'statut' => 'valide',
            'confidentiel' => true,
            'en_corbeille' => false,
        ]);

        $courrier = Courrier::create([
            'sens_courrier_id' => SensCourrier::where('code', 'arrivee')->value('id'),
            'statut_courrier_id' => StatutCourrier::where('code', 'oriente')->value('id'),
            'numero_registre' => 2,
            'numero_registre_annee' => (int) now()->format('Y'),
            'objet' => 'Ventilation test',
            'createur_id' => $directeur->id,
        ]);
        $courrier->documents()->attach($document->id);

        CourrierVentilationDestinataire::create([
            'courrier_id' => $courrier->id,
            'user_id' => $user->id,
            'document_id' => $document->id,
        ]);

        $this->assertTrue($document->fresh()->visiblePar($user));
        $this->assertTrue($courrier->visiblePar($user));
        // Le destinataire de la ventilation doit pouvoir consulter la pièce confidentielle
        // sans avoir la permission générale dossiers.view-confidentiel.
        $this->assertTrue($user->can('view', $document->fresh()));
        // Le scope de liste/recherche doit rester cohérent avec l'accès individuel ci-dessus :
        // la pièce ventilée doit apparaître dans Document::visibleBy($user) malgré confidentiel=true.
        $this->assertTrue(Document::visibleBy($user)->whereKey($document->id)->exists());
    }

    public function test_types_document_courrier_in_et_out_exist(): void
    {
        $this->assertDatabaseHas('type_documents', ['code' => 'COURRIER_IN', 'actif' => true]);
        $this->assertDatabaseHas('type_documents', ['code' => 'COURRIER_OUT', 'actif' => true]);
    }

    public function test_formulaire_depart_n_affiche_que_le_parapheur(): void
    {
        $secretaire = $this->creerSecretaire();
        $typeLettre = TypeDocument::where('code', 'LETTRE')->firstOrFail();

        Document::create([
            'type_document_id' => $typeLettre->id,
            'proprietaire_id' => $secretaire->id,
            'createur_id' => $secretaire->id,
            'user_id' => $secretaire->id,
            'nom_original' => 'lettre-officielle.pdf',
            'titre' => 'Lettre officielle',
            'chemin' => 'documents/test.pdf',
            'extension' => 'pdf',
            'taille_octets' => 1024,
            'statut' => 'brouillon',
            'en_corbeille' => false,
        ]);

        Document::create([
            'type_document_id' => TypeDocument::where('code', 'CV')->value('id'),
            'proprietaire_id' => $secretaire->id,
            'createur_id' => $secretaire->id,
            'user_id' => $secretaire->id,
            'nom_original' => 'demande-conge.pdf',
            'titre' => 'Demande congé',
            'chemin' => 'documents/conge.pdf',
            'extension' => 'pdf',
            'taille_octets' => 1024,
            'statut' => 'brouillon',
            'en_corbeille' => false,
        ]);

        $this->actingAs($secretaire)
            ->get(route('courriers.create', ['sens' => 'depart'], absolute: false))
            ->assertOk()
            ->assertSee('Lettre officielle', false)
            ->assertDontSee('Demande congé', false);
    }

    public function test_depot_inline_parapheur_lors_creation_depart(): void
    {
        Storage::fake('public');
        $secretaire = $this->creerSecretaire();
        $typeLettre = TypeDocument::where('code', 'LETTRE')->firstOrFail();

        $this->actingAs($secretaire)
            ->post(route('courriers.store', absolute: false), [
                'sens' => 'depart',
                'objet' => 'Courrier avec dépôt inline',
                'nouveau_type_document_id' => $typeLettre->id,
                'nouveaux_fichiers' => [
                    UploadedFile::fake()->create('note-depart.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertRedirect();

        $depart = Courrier::where('objet', 'Courrier avec dépôt inline')->firstOrFail();
        $this->assertCount(1, $depart->documents);

        $document = $depart->documents->first();
        $this->assertSame('LETTRE', $document->typeDocument->code);
        $this->assertSame('brouillon', $document->statut);
        $this->assertSame($secretaire->id, $document->createur_id);
        $this->assertNotNull($document->dossier_id);
        $this->assertSame('Courriers départ', $document->dossier->nom);
        $this->assertSame($secretaire->id, $document->dossier->parent->racine_utilisateur_id);
    }

    public function test_document_lie_a_depart_actif_exclu_du_parapheur(): void
    {
        $secretaire = $this->creerSecretaire();
        $typeLettre = TypeDocument::where('code', 'LETTRE')->firstOrFail();

        $document = Document::create([
            'type_document_id' => $typeLettre->id,
            'proprietaire_id' => $secretaire->id,
            'createur_id' => $secretaire->id,
            'user_id' => $secretaire->id,
            'nom_original' => 'deja-utilisee.pdf',
            'titre' => 'Déjà utilisée',
            'chemin' => 'documents/used.pdf',
            'extension' => 'pdf',
            'taille_octets' => 1024,
            'statut' => 'brouillon',
            'en_corbeille' => false,
        ]);

        $depart = Courrier::create([
            'sens_courrier_id' => SensCourrier::where('code', 'depart')->value('id'),
            'statut_courrier_id' => StatutCourrier::where('code', 'brouillon')->value('id'),
            'numero_registre' => 99,
            'numero_registre_annee' => (int) now()->format('Y'),
            'objet' => 'Départ existant',
            'createur_id' => $secretaire->id,
            'structure_id' => $secretaire->structure_id,
        ]);
        $depart->documents()->attach($document->id);

        $this->actingAs($secretaire)
            ->get(route('courriers.create', ['sens' => 'depart'], absolute: false))
            ->assertOk()
            ->assertDontSee('Déjà utilisée', false);
    }

    public function test_formulaire_creation_depart_n_affiche_pas_choix_destinataire(): void
    {
        $secretaire = $this->creerSecretaire();

        $this->actingAs($secretaire)
            ->get(route('courriers.create', ['sens' => 'depart'], absolute: false))
            ->assertOk()
            ->assertSee('après validation', false)
            ->assertDontSee('Secrétariat destinataire', false);
    }

    public function test_expedition_exige_choix_destinataire_apres_validation(): void
    {
        $secretaire = $this->creerSecretaire();

        $depart = Courrier::create([
            'sens_courrier_id' => SensCourrier::where('code', 'depart')->value('id'),
            'statut_courrier_id' => StatutCourrier::where('code', 'signe')->value('id'),
            'numero_registre' => 61,
            'numero_registre_annee' => (int) now()->format('Y'),
            'objet' => 'Courrier signé sans destinataire',
            'createur_id' => $secretaire->id,
            'structure_id' => $secretaire->structure_id,
        ]);

        $this->actingAs($secretaire)
            ->get(route('courriers.show', $depart, absolute: false))
            ->assertOk()
            ->assertSee('Secrétariat destinataire', false)
            ->assertSee('Expédier vers le secrétariat destinataire', false);

        $this->actingAs($secretaire)
            ->post(route('courriers.expedier-interne', $depart, absolute: false), [])
            ->assertSessionHasErrors('structure_destinataire_id');
    }

    public function test_expedition_exclut_le_secretariat_emetteur_des_destinataires(): void
    {
        $secDir = Structure::where('code', 'SEC-DIR')->firstOrFail();
        $secDdsait = Structure::where('code', 'SEC-DDSAIT')->firstOrFail();
        $secretaire = $this->creerSecretaire();

        $depart = Courrier::create([
            'sens_courrier_id' => SensCourrier::where('code', 'depart')->value('id'),
            'statut_courrier_id' => StatutCourrier::where('code', 'signe')->value('id'),
            'numero_registre' => 63,
            'numero_registre_annee' => (int) now()->format('Y'),
            'objet' => 'Expédition hors secrétariat émetteur',
            'createur_id' => $secretaire->id,
            'structure_id' => $secDir->id,
        ]);

        $html = $this->actingAs($secretaire)
            ->get(route('courriers.show', $depart, absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/name="structure_destinataire_id"[\s\S]*?<option[^>]*value="'.$secDir->id.'"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/name="structure_destinataire_id"[\s\S]*?<option[^>]*value="'.$secDdsait->id.'"/',
            $html
        );

        $this->actingAs($secretaire)
            ->post(route('courriers.expedier-interne', $depart, absolute: false), [
                'structure_destinataire_id' => $secDir->id,
            ])
            ->assertSessionHasErrors('structure_destinataire_id');
    }

    public function test_dg_ne_peut_pas_expedier_apres_sa_propre_validation(): void
    {
        $secDir = Structure::where('code', 'SEC-DIR')->firstOrFail();
        $secDdsait = Structure::where('code', 'SEC-DDSAIT')->firstOrFail();
        $dgStructure = Structure::where('code', 'DG')->firstOrFail();

        $particuliere = User::factory()->create(['structure_id' => $secDir->id]);
        $particuliere->assignRole('particulier_dg');

        $directeurGeneral = User::factory()->create([
            'structure_id' => $dgStructure->id,
            'name' => 'LORD MARHYNO GANDOU',
        ]);
        $directeurGeneral->assignRole('dg');

        $depart = Courrier::create([
            'sens_courrier_id' => SensCourrier::where('code', 'depart')->value('id'),
            'statut_courrier_id' => StatutCourrier::where('code', 'signe')->value('id'),
            'numero_registre' => 62,
            'numero_registre_annee' => (int) now()->format('Y'),
            'objet' => 'Signé — expédition secrétariat uniquement',
            'createur_id' => $particuliere->id,
            'structure_id' => $secDir->id,
            'signataire_id' => $directeurGeneral->id,
            'directeur_en_attente_id' => $directeurGeneral->id,
        ]);

        $this->actingAs($directeurGeneral)
            ->get(route('courriers.show', $depart, absolute: false))
            ->assertOk()
            ->assertDontSee('Expédier vers le secrétariat destinataire', false);

        $this->actingAs($directeurGeneral)
            ->post(route('courriers.expedier-interne', $depart, absolute: false), [
                'structure_destinataire_id' => $secDdsait->id,
            ])
            ->assertForbidden();

        $this->actingAs($particuliere)
            ->get(route('courriers.show', $depart, absolute: false))
            ->assertOk()
            ->assertSee('Expédier vers le secrétariat destinataire', false);
    }

    public function test_depart_brouillon_n_affiche_pas_transmission_ni_archiver(): void
    {
        $secretaire = $this->creerSecretaire();

        $this->actingAs($secretaire)
            ->post(route('courriers.store', absolute: false), [
                'sens' => 'depart',
                'objet' => 'Test actions brouillon',
            ])
            ->assertRedirect();

        $depart = Courrier::where('objet', 'Test actions brouillon')->firstOrFail();

        $this->actingAs($secretaire)
            ->get(route('courriers.show', $depart, absolute: false))
            ->assertOk()
            ->assertSee('Transmettre au directeur', false)
            ->assertSee('À définir après validation', false)
            ->assertSee('Modifier', false)
            ->assertDontSee('id="form-transmettre"', false)
            ->assertDontSee('>Transmission<', false)
            ->assertDontSee('>Archiver<', false);

        $this->actingAs($secretaire)
            ->post(route('courriers.transmettre', $depart, absolute: false), [
                'vers_structure_id' => Structure::where('code', 'SEC-DDSAIT')->value('id'),
                'commentaire' => 'Tentative',
            ])
            ->assertForbidden();

        $this->actingAs($secretaire)
            ->post(route('courriers.archiver', $depart, absolute: false))
            ->assertForbidden();
    }

    public function test_depart_expedie_refuse_transmission_meme_sans_accuse(): void
    {
        $secDir = Structure::where('code', 'SEC-DIR')->firstOrFail();
        $secDaf = Structure::where('code', 'SEC-DAF')->firstOrFail();
        $secretaire = $this->creerSecretaire();

        $depart = Courrier::create([
            'sens_courrier_id' => SensCourrier::where('code', 'depart')->value('id'),
            'statut_courrier_id' => StatutCourrier::where('code', 'expedie')->value('id'),
            'numero_registre' => 80,
            'numero_registre_annee' => (int) now()->format('Y'),
            'objet' => 'Départ expédié sans trace',
            'origine' => 'interne',
            'createur_id' => $secretaire->id,
            'structure_id' => $secDir->id,
            'structure_destinataire_id' => $secDaf->id,
            'date_expedition' => now(),
        ]);

        $this->actingAs($secretaire)
            ->get(route('courriers.show', $depart, absolute: false))
            ->assertOk()
            ->assertDontSee('>Transmission<', false)
            ->assertSee('Courrier expédié — aucune action supplémentaire', false);

        $this->actingAs($secretaire)
            ->post(route('courriers.transmettre', $depart, absolute: false), [
                'vers_structure_id' => $secDaf->id,
                'commentaire' => 'Tentative de trace',
                'accuse_reception' => '1',
            ])
            ->assertForbidden();
    }

    public function test_depart_brouillon_affiche_le_directeur_de_la_direction(): void
    {
        $ddsait = Structure::where('code', 'DDSAIT')->firstOrFail();
        $secDdsait = Structure::where('code', 'SEC-DDSAIT')->firstOrFail();

        $secretaire = User::factory()->create(['structure_id' => $secDdsait->id]);
        $secretaire->assignRole('secretaire_direction');

        $directeur = User::factory()->create([
            'structure_id' => $ddsait->id,
            'name' => 'Directeur DDSAIT Test',
        ]);
        $directeur->assignRole('directeur');

        $depart = Courrier::create([
            'sens_courrier_id' => SensCourrier::where('code', 'depart')->value('id'),
            'statut_courrier_id' => StatutCourrier::where('code', 'brouillon')->value('id'),
            'numero_registre' => 50,
            'numero_registre_annee' => (int) now()->format('Y'),
            'objet' => 'Courrier avec directeur affiché',
            'createur_id' => $secretaire->id,
            'structure_id' => $secDdsait->id,
        ]);

        $this->actingAs($secretaire)
            ->get(route('courriers.show', $depart, absolute: false))
            ->assertOk()
            ->assertSee('Directeur DDSAIT Test', false)
            ->assertSee('Transmettre au directeur', false)
            ->assertDontSee('Directeur Général', false);
    }

    public function test_transmettre_au_directeur_notifie_le_directeur(): void
    {
        Notification::fake();

        $secDir = Structure::where('code', 'SEC-DIR')->firstOrFail();
        $dg = Structure::where('code', 'DG')->firstOrFail();

        $secretaire = User::factory()->create(['structure_id' => $secDir->id]);
        $secretaire->assignRole('secretaire_direction');

        $directeur = User::factory()->create(['structure_id' => $dg->id]);
        $directeur->assignRole('directeur');

        $depart = Courrier::create([
            'sens_courrier_id' => SensCourrier::where('code', 'depart')->value('id'),
            'statut_courrier_id' => StatutCourrier::where('code', 'brouillon')->value('id'),
            'numero_registre' => 52,
            'numero_registre_annee' => (int) now()->format('Y'),
            'objet' => 'Courrier notif directeur',
            'createur_id' => $secretaire->id,
            'structure_id' => $secDir->id,
        ]);

        $this->actingAs($secretaire)
            ->post(route('courriers.transmettre-directeur', $depart, absolute: false))
            ->assertRedirect();

        Notification::assertSentTo(
            $directeur,
            CourrierWorkflowNotification::class,
            fn (CourrierWorkflowNotification $n) => $n->type === CourrierNotificationService::TRANSMISSION_DIRECTEUR
        );
    }

    public function test_dg_ne_peut_pas_se_transmettre_un_brouillon_depart(): void
    {
        $secDir = Structure::where('code', 'SEC-DIR')->firstOrFail();
        $dgStructure = Structure::where('code', 'DG')->firstOrFail();

        $particuliere = User::factory()->create(['structure_id' => $secDir->id]);
        $particuliere->assignRole('particulier_dg');

        $directeurGeneral = User::factory()->create([
            'structure_id' => $dgStructure->id,
            'name' => 'LORD MARHYNO GANDOU',
        ]);
        $directeurGeneral->assignRole('dg');

        $depart = Courrier::create([
            'sens_courrier_id' => SensCourrier::where('code', 'depart')->value('id'),
            'statut_courrier_id' => StatutCourrier::where('code', 'brouillon')->value('id'),
            'numero_registre' => 54,
            'numero_registre_annee' => (int) now()->format('Y'),
            'objet' => 'Brouillon visible DG sans auto-transmission',
            'createur_id' => $particuliere->id,
            'structure_id' => $secDir->id,
        ]);

        $this->actingAs($directeurGeneral)
            ->get(route('courriers.show', $depart, absolute: false))
            ->assertOk()
            ->assertDontSee('Transmettre au directeur', false);

        $this->actingAs($directeurGeneral)
            ->post(route('courriers.transmettre-directeur', $depart, absolute: false))
            ->assertForbidden();

        $this->actingAs($particuliere)
            ->get(route('courriers.show', $depart, absolute: false))
            ->assertOk()
            ->assertSee('Transmettre au directeur', false)
            ->assertSee('LORD MARHYNO GANDOU', false);
    }

    public function test_directeur_peut_annuler_courrier_en_validation(): void
    {
        $secDir = Structure::where('code', 'SEC-DIR')->firstOrFail();
        $dg = Structure::where('code', 'DG')->firstOrFail();

        $secretaire = User::factory()->create(['structure_id' => $secDir->id]);
        $secretaire->assignRole('secretaire_direction');

        $directeur = User::factory()->create(['structure_id' => $dg->id]);
        $directeur->assignRole('directeur');

        $depart = Courrier::create([
            'sens_courrier_id' => SensCourrier::where('code', 'depart')->value('id'),
            'statut_courrier_id' => StatutCourrier::where('code', 'transmis_directeur')->value('id'),
            'numero_registre' => 53,
            'numero_registre_annee' => (int) now()->format('Y'),
            'objet' => 'Courrier annulé',
            'createur_id' => $secretaire->id,
            'structure_id' => $secDir->id,
            'directeur_en_attente_id' => $directeur->id,
        ]);

        $this->actingAs($directeur)
            ->post(route('courriers.annuler', $depart, absolute: false), [
                'motif_annulation' => 'Doublon',
            ])
            ->assertRedirect(route('courriers.index', ['sens' => 'depart'], absolute: false));

        $depart->refresh();
        $this->assertSame('annule', $depart->statutCourrier->code);
    }

    public function test_depart_expedie_n_affiche_plus_transmission_ni_archiver(): void
    {
        $secretaire = $this->creerSecretaire();
        $secDdsait = Structure::where('code', 'SEC-DDSAIT')->firstOrFail();

        $depart = Courrier::create([
            'sens_courrier_id' => SensCourrier::where('code', 'depart')->value('id'),
            'statut_courrier_id' => StatutCourrier::where('code', 'expedie')->value('id'),
            'numero_registre' => 51,
            'numero_registre_annee' => (int) now()->format('Y'),
            'objet' => 'Courrier expédié',
            'structure_destinataire_id' => $secDdsait->id,
            'createur_id' => $secretaire->id,
            'structure_id' => $secretaire->structure_id,
            'date_expedition' => now(),
        ]);

        $this->actingAs($secretaire)
            ->get(route('courriers.show', $depart, absolute: false))
            ->assertOk()
            ->assertSee('Courrier expédié — aucune action supplémentaire', false)
            ->assertDontSee('>Transmission<', false)
            ->assertDontSee('>Archiver<', false)
            ->assertDontSee('Transmettre au directeur', false);

        $this->actingAs($secretaire)
            ->post(route('courriers.transmettre', $depart, absolute: false), [
                'vers_structure_id' => $secDdsait->id,
                'commentaire' => 'Tentative',
            ])
            ->assertForbidden();

        $this->actingAs($secretaire)
            ->post(route('courriers.archiver', $depart, absolute: false), [
                'numero_archives' => 'DG/DEP/2026/999',
            ])
            ->assertForbidden();
    }

    public function test_historique_fil_affiche_le_dernier_mouvement_en_premier(): void
    {
        $secretaire = $this->creerSecretaire();
        $sensDepart = SensCourrier::where('code', 'depart')->value('id');
        $statut = StatutCourrier::where('code', 'brouillon')->value('id');

        $ancien = Courrier::create([
            'sens_courrier_id' => $sensDepart,
            'statut_courrier_id' => $statut,
            'numero_registre' => 70,
            'numero_registre_annee' => (int) now()->format('Y'),
            'objet' => 'Ancien du fil',
            'origine' => 'interne',
            'date_courrier' => now()->subDays(3)->toDateString(),
            'createur_id' => $secretaire->id,
            'structure_id' => $secretaire->structure_id,
        ]);

        $recent = Courrier::create([
            'sens_courrier_id' => $sensDepart,
            'statut_courrier_id' => $statut,
            'numero_registre' => 71,
            'numero_registre_annee' => (int) now()->format('Y'),
            'objet' => 'Récent du fil',
            'origine' => 'interne',
            'date_courrier' => now()->toDateString(),
            'courrier_parent_id' => $ancien->id,
            'createur_id' => $secretaire->id,
            'structure_id' => $secretaire->structure_id,
        ]);

        $historique = app(CourrierFilService::class)->construireHistorique($recent);
        $libellesCourriers = $historique->where('type', 'courrier')->pluck('libelle')->values();

        $this->assertGreaterThanOrEqual(2, $libellesCourriers->count());
        $this->assertStringContainsString('71/', (string) $libellesCourriers->first());
        $this->assertTrue(
            $historique->first()['date']->greaterThanOrEqualTo($historique->last()['date']),
            'Le premier événement du fil doit être le plus récent.'
        );
    }

    public function test_fil_courrier_relie_arrivee_externe_et_reponse(): void
    {
        Storage::fake('public');
        $secretaire = $this->creerSecretaire();
        $secDdsait = Structure::where('code', 'SEC-DDSAIT')->firstOrFail();

        $this->actingAs($secretaire)
            ->post(route('courriers.store', absolute: false), [
                'sens' => 'arrivee',
                'objet' => 'Courrier ministère',
                'expediteur_libelle' => 'Ministère X',
                'fichier' => UploadedFile::fake()->create('entree.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $arrivee = Courrier::where('objet', 'Courrier ministère')->firstOrFail();

        $this->actingAs($secretaire)
            ->post(route('courriers.creer-reponse', $arrivee, absolute: false), [
                'structure_destinataire_id' => $secDdsait->id,
            ])
            ->assertRedirect(route('courriers.show', $arrivee, absolute: false));

        $reponse = Courrier::where('objet', 'Réponse — Courrier ministère')->firstOrFail();
        $this->assertSame($arrivee->id, $reponse->courrier_parent_id);

        $fil = app(CourrierFilService::class);
        $this->assertCount(2, $fil->courriersDuFil($arrivee));
        $this->assertSame($arrivee->id, $fil->racine($reponse)->id);

        $this->actingAs($secretaire)
            ->get(route('courriers.show', $arrivee, absolute: false))
            ->assertOk()
            ->assertSee('Fil (', false)
            ->assertSee('Réponse — Courrier ministère', false)
            ->assertSee('Document entrant', false)
            ->assertSee('Document sortant', false);
    }

    public function test_fil_courrier_relie_depart_interne_arrivee_et_reponse(): void
    {
        $secDir = Structure::where('code', 'SEC-DIR')->firstOrFail();
        $secDdsait = Structure::where('code', 'SEC-DDSAIT')->firstOrFail();
        $dg = Structure::where('code', 'DG')->firstOrFail();

        $secretaire = User::factory()->create(['structure_id' => $secDir->id]);
        $secretaire->assignRole('secretaire_direction');
        $directeur = User::factory()->create(['structure_id' => $dg->id]);
        $directeur->assignRole('directeur');
        $secretaireDest = User::factory()->create(['structure_id' => $secDdsait->id]);
        $secretaireDest->assignRole('secretaire_direction');

        $this->actingAs($secretaire)
            ->post(route('courriers.store', absolute: false), ['sens' => 'depart', 'objet' => 'Demande DDSAIT'])
            ->assertRedirect();

        $depart = Courrier::where('objet', 'Demande DDSAIT')->firstOrFail();

        $this->actingAs($secretaire)->post(route('courriers.transmettre-directeur', $depart, absolute: false));
        $this->actingAs($directeur)->post(route('courriers.signer', $depart, absolute: false));
        $this->actingAs($secretaire)->post(route('courriers.expedier-interne', $depart, absolute: false), [
            'structure_destinataire_id' => $secDdsait->id,
        ]);
        $this->actingAs($secretaireDest)->post(route('courriers.accepter-reception', $depart, absolute: false));

        $depart->refresh();
        $arrivee = Courrier::findOrFail($depart->courrier_arrivee_lie_id);

        $this->actingAs($secretaireDest)
            ->post(route('courriers.creer-reponse', $arrivee, absolute: false), [])
            ->assertRedirect(route('courriers.show', $arrivee, absolute: false));

        $reponse = Courrier::where('objet', 'Réponse — '.$arrivee->objet)->firstOrFail();
        $this->assertSame($arrivee->id, $reponse->courrier_parent_id);
        $this->assertSame('interne', $reponse->origine);
        $this->assertSame($secDir->id, $reponse->structure_destinataire_id);

        $fil = app(CourrierFilService::class);
        $this->assertCount(3, $fil->courriersDuFil($arrivee));
    }

    public function test_fiche_document_affiche_courriers_lies(): void
    {
        Storage::fake('public');
        $secretaire = $this->creerSecretaire();

        $this->actingAs($secretaire)
            ->post(route('courriers.store', absolute: false), [
                'sens' => 'arrivee',
                'objet' => 'Doc traçabilité',
                'fichier' => UploadedFile::fake()->create('trace.pdf', 100, 'application/pdf'),
            ]);

        $arrivee = Courrier::where('objet', 'Doc traçabilité')->firstOrFail();
        $document = $arrivee->documents()->first();
        $this->assertNotNull($document);

        $this->actingAs($secretaire)
            ->get(route('documents.fiche', $document, absolute: false))
            ->assertOk()
            ->assertSee('Courriers liés', false)
            ->assertSee('Courrier arrivée', false)
            ->assertSee('Document entrant', false);
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
}
