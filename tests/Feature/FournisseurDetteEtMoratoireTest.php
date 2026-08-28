<?php

namespace Tests\Feature;

use App\Models\CategorieDepense;
use App\Models\CircuitCourrier;
use App\Models\Courrier;
use App\Models\Document;
use App\Models\Moratoire;
use App\Models\PrioriteCourrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\StatutDocument;
use App\Models\Structure;
use App\Models\SuiviPaiement;
use App\Models\TypeCourrier;
use App\Models\TypeDocument;
use App\Models\User;
use App\Services\CircuitCourrierMoteurService;
use App\Services\FournisseurDetteService;
use App\Services\MoratoireService;
use Database\Seeders\CategorieDepenseSeeder;
use Database\Seeders\CircuitCourrierSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StatutDocumentSeeder;
use Database\Seeders\StructureSeeder;
use Database\Seeders\TypeDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FournisseurDetteEtMoratoireTest extends TestCase
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
            CategorieDepenseSeeder::class,
            TypeDocumentSeeder::class,
            StatutDocumentSeeder::class,
        ]);
    }

    public function test_cumul_dette_par_fournisseur_facture_moins_paiements(): void
    {
        $dg = $this->creerDg();
        $this->creerFacture($dg, 'ETABLISSEMENT JAY', 10_000_000);
        $this->creerFacture($dg, 'etablissement jay', 7_989_516);

        $ac = User::factory()->create();
        $catFacture = CategorieDepense::query()->where('code', CategorieDepense::CODE_FACTURE)->firstOrFail();
        SuiviPaiement::query()->create([
            'type' => SuiviPaiement::TYPE_FSP_FACTURE,
            'categorie_depense_id' => $catFacture->id,
            'origine' => SuiviPaiement::ORIGINE_CIRCUIT_CHEQUE,
            'numero_ligne' => 1,
            'numero_annee' => 2026,
            'date_suivi' => now()->toDateString(),
            'date_decharge' => now()->toDateString(),
            'intitule' => 'Paiement partiel JAY',
            'montant' => 1_500_000,
            'fournisseur_libelle' => 'ETABLISSEMENT JAY',
            'etabli_par_id' => $ac->id,
        ]);

        $dette = app(FournisseurDetteService::class)->dettePourFournisseur('ETABLISSEMENT JAY');

        $this->assertNotNull($dette);
        $this->assertSame(2, $dette['nb_factures']);
        $this->assertEquals(17_989_516.0, $dette['montant_facture']);
        $this->assertEquals(1_500_000.0, $dette['montant_paye']);
        $this->assertEquals(16_489_516.0, $dette['dette']);
    }

    public function test_generation_echeancier_type_document_jay(): void
    {
        $lignes = app(MoratoireService::class)->genererLignesEcheancier(17_989_516, 1_500_000);

        $this->assertCount(12, $lignes);
        $this->assertEquals(17_989_516.0, $lignes[0]['montant_dette']);
        $this->assertEquals(1_500_000.0, $lignes[0]['montant_echeance']);
        $this->assertEquals(16_489_516.0, $lignes[0]['solde']);
        $this->assertEquals(1_489_516.0, $lignes[11]['montant_echeance']);
        $this->assertEquals(0.0, $lignes[11]['solde']);
        $this->assertEquals($lignes[10]['solde'], $lignes[11]['montant_dette']);
    }

    public function test_generation_echeancier_refuse_plus_de_500_lignes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('500 échéances');

        app(MoratoireService::class)->genererLignesEcheancier(1_000_000, 1);
    }

    public function test_creation_moratoire_exige_instruction_dg(): void
    {
        Storage::fake('public');

        $this->preparerDetteFournisseur('ETABLISSEMENT JAY', 17_989_516);

        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $this->actingAs($eleni)
            ->from(route('moratoires.create', absolute: false))
            ->post(route('moratoires.store', absolute: false), [
                'fournisseur_libelle' => 'ETABLISSEMENT JAY',
                'montant_echeance_defaut' => '1 500 000',
            ])
            ->assertRedirect(route('moratoires.create', absolute: false))
            ->assertSessionHasErrors('fichiers');

        $this->assertSame(0, Moratoire::query()->count());
    }

    public function test_creation_moratoire_refuse_fournisseur_hors_dettes(): void
    {
        Storage::fake('public');

        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $this->actingAs($eleni)
            ->post(route('moratoires.store', absolute: false), [
                'fournisseur_libelle' => 'FOURNISSEUR INVENTÉ',
                'montant_echeance_defaut' => '1500000',
                'fichiers' => [UploadedFile::fake()->create('instruction-dg.pdf', 20, 'application/pdf')],
            ])
            ->assertSessionHasErrors('fournisseur_libelle');

        $this->assertSame(0, Moratoire::query()->count());
    }

    public function test_detail_dettes_affiche_chaque_facture(): void
    {
        $dg = $this->creerDg();
        $this->creerFacture($dg, 'SOMAC', 280_000);
        $this->creerFacture($dg, 'SOMAC', 2_570_000);

        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $this->actingAs($eleni)
            ->get(route('moratoires.dettes.detail', ['fournisseur' => 'SOMAC'], absolute: false))
            ->assertOk()
            ->assertSee('SOMAC', false)
            ->assertSee('2 850 000', false)
            ->assertSee('280 000', false)
            ->assertSee('2 570 000', false)
            ->assertSee('Factures détaillées', false);
    }

    public function test_responsable_peut_creer_moratoire_et_saisir_cheque(): void
    {
        Storage::fake('public');

        $this->preparerDetteFournisseur('ETABLISSEMENT JAY', 17_989_516);

        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $this->actingAs($eleni)
            ->post(route('moratoires.store', absolute: false), [
                'fournisseur_libelle' => 'ETABLISSEMENT JAY',
                'montant_echeance_defaut' => '1 500 000',
                'lieu' => 'Brazzaville',
                'signataire_libelle' => $eleni->name,
                'fichiers' => [
                    UploadedFile::fake()->create('instruction-dg.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertRedirect();

        $moratoire = Moratoire::query()->first();
        $this->assertNotNull($moratoire);
        $this->assertSame(12, $moratoire->echeances()->count());
        $this->assertCount(1, $moratoire->documents);
        $this->assertSame('instruction-dg.pdf', $moratoire->documents->first()->nom_original);

        $echeance = $moratoire->echeances()->where('numero', 1)->first();
        $this->assertNotNull($echeance);

        $this->actingAs($eleni)
            ->get(route('moratoires.show', $moratoire, absolute: false))
            ->assertOk()
            ->assertSee('Saisir', false)
            ->assertSee('Échéance n° 1', false)
            ->assertSee('Date paiement', false)
            ->assertSee('Enregistrer', false);

        $this->actingAs($eleni)
            ->patch(route('moratoires.echeances.update', [$moratoire, $echeance], absolute: false), [
                'mode_paiement' => 'cheque',
                'numero_cheque' => 'CHQ-001',
                'banque' => 'BCH',
                'date_paiement' => now()->toDateString(),
                'periode_mois' => 'Janvier',
                'periode_annee' => 2026,
                'observation' => '1er versement',
                'fichiers' => [UploadedFile::fake()->create('cheque.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect();

        $echeance->refresh();
        $this->assertSame('CHQ-001', $echeance->numero_cheque);
        $this->assertSame('cheque', $echeance->mode_paiement);
        $this->assertSame('Janvier', $echeance->periode_mois);
        $this->assertSame(2026, $echeance->periode_annee);
        $this->assertTrue($echeance->estPayee());
        $this->assertNotNull($echeance->suivi_paiement_id);

        $suivi = SuiviPaiement::query()->find($echeance->suivi_paiement_id);
        $this->assertNotNull($suivi);
        $this->assertSame(SuiviPaiement::ORIGINE_MORATOIRE, $suivi->origine);
        $this->assertEquals(1_500_000.0, (float) $suivi->montant);
        $this->assertNotNull($suivi->date_decharge);
        $this->assertNotNull($suivi->dossier_id);

        $this->actingAs($eleni)
            ->get(route('suivi-paiements.index', absolute: false))
            ->assertOk()
            ->assertSee('Moratoire', false)
            ->assertSee('ETABLISSEMENT JAY', false);

        $this->actingAs($eleni)
            ->get(route('moratoires.print', $moratoire, absolute: false))
            ->assertOk()
            ->assertSee('Aperçu PDF', false)
            ->assertSee('data:application/pdf;base64,', false);
    }

    public function test_eleni_peut_saisir_paiement_espece_avec_periode(): void
    {
        Storage::fake('public');

        $this->preparerDetteFournisseur('Chauffeur DG', 500_000);

        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $this->actingAs($eleni)
            ->post(route('moratoires.store', absolute: false), [
                'fournisseur_libelle' => 'Chauffeur DG',
                'montant_echeance_defaut' => '250000',
                'fichiers' => [UploadedFile::fake()->create('instruction-dg.pdf', 20, 'application/pdf')],
            ])
            ->assertRedirect();

        $moratoire = Moratoire::query()->firstOrFail();
        $echeance = $moratoire->echeances()->where('numero', 1)->firstOrFail();

        $this->actingAs($eleni)
            ->patch(route('moratoires.echeances.update', [$moratoire, $echeance], absolute: false), [
                'mode_paiement' => 'espece',
                'date_paiement' => '2026-02-11',
                'periode_mois' => 'Janvier',
                'periode_annee' => 2026,
                'observation' => 'Paiement espèces',
                'fichiers' => [UploadedFile::fake()->create('recu.pdf', 50, 'application/pdf')],
            ])
            ->assertRedirect();

        $echeance->refresh();
        $this->assertSame('espece', $echeance->mode_paiement);
        $this->assertTrue($echeance->estPayee());
        $this->assertSame('Janvier', $echeance->periode_mois);
        $this->assertSame(2026, $echeance->periode_annee);
        $this->assertNull($echeance->numero_cheque);

        $suivi = SuiviPaiement::query()->find($echeance->suivi_paiement_id);
        $this->assertNotNull($suivi);
        $this->assertSame('Espèces', $suivi->numero_piece);
        $this->assertStringContainsString('Janvier 2026', $suivi->intitule);
    }

    public function test_suivi_factures_renvoie_vers_moratoires_sans_doublon_dettes(): void
    {
        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $this->creerFacture($dg, 'AF.COM', 2_500_000);
        $moteur->instruire($courrier, $dg, 'Bon pour accord.', $ac->id);

        $this->actingAs($taty)
            ->get(route('suivi-factures-fournisseurs.index', absolute: false))
            ->assertOk()
            ->assertDontSee('Dettes par fournisseur', false)
            ->assertSee('AF.COM', false)
            ->assertSee('2 500 000', false)
            ->assertSee('/moratoires', false)
            ->assertSee('Dettes &amp; moratoires', false);

        $this->actingAs($taty)
            ->get(route('moratoires.index', absolute: false))
            ->assertOk()
            ->assertSee('Dettes fournisseurs', false)
            ->assertSee('AF.COM', false)
            ->assertDontSee('Plans de paiement progressif', false)
            ->assertDontSee('Nouveau moratoire', false);

        $this->actingAs($taty)
            ->get(route('moratoires.print-dettes', absolute: false))
            ->assertOk()
            ->assertSee('Aperçu PDF', false);
    }

    public function test_taty_voit_dettes_mais_pas_les_plans_de_paiement(): void
    {
        Storage::fake('public');

        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');
        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $this->preparerDetteFournisseur('SCAB', 2_550_000);

        $this->actingAs($eleni)
            ->post(route('moratoires.store', absolute: false), [
                'fournisseur_libelle' => 'SCAB',
                'montant_echeance_defaut' => '1500000',
                'fichiers' => [UploadedFile::fake()->create('instruction-dg.pdf', 20, 'application/pdf')],
            ])
            ->assertRedirect();

        $moratoire = Moratoire::query()->firstOrFail();
        $echeance = $moratoire->echeances()->where('numero', 1)->firstOrFail();

        $this->assertFalse($taty->can('view', $moratoire));
        $this->assertFalse($taty->can('create', Moratoire::class));
        $this->assertFalse($taty->can('update', $moratoire));

        $this->actingAs($taty)
            ->get(route('moratoires.index', absolute: false))
            ->assertOk()
            ->assertSee('Dettes fournisseurs', false)
            ->assertSee('SCAB', false)
            ->assertDontSee('Plans de paiement progressif', false)
            ->assertDontSee('Voir plan', false);

        $this->actingAs($taty)
            ->get(route('moratoires.show', $moratoire, absolute: false))
            ->assertForbidden();

        $this->actingAs($taty)
            ->get(route('moratoires.print-plans', absolute: false))
            ->assertForbidden();

        $this->actingAs($taty)
            ->get(route('moratoires.dettes.detail', ['fournisseur' => 'SCAB'], absolute: false))
            ->assertOk()
            ->assertSee('SCAB', false)
            ->assertDontSee('Voir le plan actif', false);

        $this->actingAs($taty)
            ->get(route('moratoires.create', absolute: false))
            ->assertForbidden();

        $this->actingAs($taty)
            ->patch(route('moratoires.echeances.update', [$moratoire, $echeance], absolute: false), [
                'numero_cheque' => 'CHQ-999',
                'date_paiement' => now()->toDateString(),
                'fichiers' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')],
            ])
            ->assertForbidden();
    }

    public function test_eleni_peut_ouvrir_piece_jointe_facture_depuis_dettes(): void
    {
        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $dg = $this->creerDg();
        $courrier = $this->creerFacture($dg, 'AF.COM', 1_200_000);
        $document = $this->attacherPieceFacture($courrier, $dg);

        $this->assertTrue($eleni->can('view', $document));
        $this->assertTrue($eleni->can('view', $courrier));

        $this->actingAs($eleni)
            ->get(route('documents.fiche', $document, absolute: false))
            ->assertOk();

        $this->actingAs($eleni)
            ->get(route('courriers.show', $courrier, absolute: false))
            ->assertOk()
            ->assertSee('AF.COM', false);

        $this->actingAs($eleni)
            ->get(route('moratoires.dettes.detail', ['fournisseur' => 'AF.COM'], absolute: false))
            ->assertOk()
            ->assertSee(route('documents.fiche', $document, absolute: false), false)
            ->assertSee(route('courriers.show', $courrier, absolute: false), false);
    }

    public function test_filtres_et_impression_listes_moratoires(): void
    {
        Storage::fake('public');

        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');

        $this->preparerDetteFournisseur('DS', 3_500_000);
        $this->preparerDetteFournisseur('SCAB', 2_550_000);

        $this->actingAs($eleni)
            ->post(route('moratoires.store', absolute: false), [
                'fournisseur_libelle' => 'DS',
                'montant_echeance_defaut' => '500000',
                'fichiers' => [UploadedFile::fake()->create('instruction-ds.pdf', 20, 'application/pdf')],
            ])
            ->assertRedirect();

        $this->actingAs($eleni)
            ->post(route('moratoires.store', absolute: false), [
                'fournisseur_libelle' => 'SCAB',
                'montant_echeance_defaut' => '1500000',
                'fichiers' => [UploadedFile::fake()->create('instruction-scab.pdf', 20, 'application/pdf')],
            ])
            ->assertRedirect();

        $this->actingAs($eleni)
            ->get(route('moratoires.index', ['plan_q' => 'SCAB', 'plan_statut' => 'actif'], absolute: false))
            ->assertOk()
            ->assertSee('SCAB', false)
            ->assertSee('1 plan(s)', false)
            ->assertSee('Imprimer', false);

        $this->actingAs($eleni)
            ->get(route('moratoires.print-plans', ['plan_q' => 'SCAB'], absolute: false))
            ->assertOk()
            ->assertSee('Aperçu PDF', false)
            ->assertSee('data:application/pdf;base64,', false);

        $this->actingAs($eleni)
            ->get(route('moratoires.print-dettes', ['dette_solde' => 'tous'], absolute: false))
            ->assertOk()
            ->assertSee('Aperçu PDF', false)
            ->assertSee('data:application/pdf;base64,', false);
    }

    public function test_titre_signature_pdf_suit_le_role_utilisateur(): void
    {
        $eleni = User::factory()->create();
        $eleni->assignRole('responsable_suivi_depenses');
        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $this->assertSame('Responsable suivi des dépenses', $eleni->titreSignatureDocument());
        $this->assertSame('Responsable dossiers fournisseurs et prestataires', $taty->titreSignatureDocument());
    }

    private function preparerDetteFournisseur(string $fournisseur, float $montant): Courrier
    {
        return $this->creerFacture($this->creerDg(), $fournisseur, $montant);
    }

    private function creerDg(): User
    {
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'DG')->value('id')]);
        $user->assignRole('dg');

        return $user;
    }

    private function creerFacture(User $acteur, string $fournisseur, float $montant): Courrier
    {
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'recu')->firstOrFail();
        $type = TypeCourrier::where('code', 'facture')->firstOrFail();

        $courrier = Courrier::create([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => $type->id,
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => PrioriteCourrier::where('code', 'normale')->value('id'),
            'numero_registre' => random_int(200, 900),
            'numero_registre_annee' => 2026,
            'origine' => 'externe',
            'date_reception' => now()->toDateString(),
            'objet' => 'Facture '.$fournisseur,
            'montant_facture' => $montant,
            'expediteur_libelle' => $fournisseur,
            'createur_id' => $acteur->id,
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
            'service_demandeur_structure_id' => Structure::where('code', 'DAF')->value('id'),
        ]);

        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();

        return app(CircuitCourrierMoteurService::class)->demarrer($courrier, $circuit, $acteur);
    }

    private function attacherPieceFacture(Courrier $courrier, User $user): Document
    {
        $typeDoc = TypeDocument::query()->where('code', 'COURRIER_IN')->first()
            ?? TypeDocument::query()->where('code', 'COURRIER')->firstOrFail();
        $statut = StatutDocument::query()->where('code', 'brouillon')->first();

        $document = Document::create([
            'type_document_id' => $typeDoc->id,
            'user_id' => $user->id,
            'createur_id' => $user->id,
            'proprietaire_id' => $user->id,
            'dossier_id' => null,
            'nom_original' => 'facture.pdf',
            'chemin' => 'documents/courriers/facture-test.pdf',
            'extension' => 'pdf',
            'taille_octets' => 1024,
            'statut' => 'brouillon',
            'statut_document_id' => $statut?->id,
            'en_corbeille' => false,
        ]);

        $courrier->documents()->attach($document->id, ['est_principal' => true]);

        return $document;
    }
}
