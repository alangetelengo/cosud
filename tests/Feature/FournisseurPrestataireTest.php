<?php

namespace Tests\Feature;

use App\Models\Courrier;
use App\Models\Dossier;
use App\Models\FournisseurPrestataire;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\Structure;
use App\Models\TypeCourrier;
use App\Models\User;
use App\Services\MesDossiersRacineService;
use Database\Seeders\CircuitCourrierSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FournisseurPrestataireTest extends TestCase
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

    public function test_dg_peut_lister_et_creer_fournisseur_prestataire(): void
    {
        Storage::fake('local');

        $dg = Structure::where('code', 'DG')->firstOrFail();
        $user = User::factory()->create(['structure_id' => $dg->id]);
        $user->assignRole('dg');

        $this->actingAs($user)
            ->get(route('fournisseurs-prestataires.index', absolute: false))
            ->assertOk()
            ->assertSee('Fournisseurs ou prestataires', false);

        $this->actingAs($user)
            ->post(route('fournisseurs-prestataires.store', absolute: false), [
                'nom' => 'ACS - Approvisionnement Congo Services',
                'type' => 'prestataire',
                'type_contrat' => 'Entretien des groupes électrogènes',
                'a_contrat' => '1',
                'a_dossier_fiscal' => '0',
                'scan_contrat' => [UploadedFile::fake()->create('contrat-acs.pdf', 40, 'application/pdf')],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fournisseur_prestataires', [
            'nom' => 'ACS - Approvisionnement Congo Services',
            'a_contrat' => 1,
            'a_dossier_fiscal' => 0,
        ]);

        $fiche = FournisseurPrestataire::query()
            ->where('nom_normalise', FournisseurPrestataire::normaliserNom('ACS - Approvisionnement Congo Services'))
            ->firstOrFail();
        $this->assertTrue($fiche->aScanContrat());
        Storage::disk('local')->assertExists($fiche->piecesContrat()[0]['chemin']);
    }

    public function test_scan_contrat_prive_exige_autorisation(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->assignRole('dg');

        $this->actingAs($user)
            ->post(route('fournisseurs-prestataires.store', absolute: false), [
                'nom' => 'Scan Prive SARL',
                'type' => 'fournisseur',
                'a_contrat' => '1',
                'scan_contrat' => [UploadedFile::fake()->create('contrat.pdf', 40, 'application/pdf')],
            ])
            ->assertRedirect();

        $fiche = FournisseurPrestataire::query()
            ->where('nom_normalise', FournisseurPrestataire::normaliserNom('Scan Prive SARL'))
            ->firstOrFail();

        $url = route('fournisseurs-prestataires.pieces.show', [$fiche, 'contrat', 0], absolute: false);

        auth()->logout();
        $this->get($url)->assertRedirect();

        $invite = User::factory()->create();
        $invite->assignRole('utilisateur');

        $this->actingAs($invite)
            ->get($url)
            ->assertForbidden();

        $this->actingAs($user)
            ->get($url)
            ->assertOk();
    }

    public function test_scan_ignore_si_contrat_non_coche(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->assignRole('dg');

        $this->actingAs($user)
            ->post(route('fournisseurs-prestataires.store', absolute: false), [
                'nom' => 'Sans Contrat Mais Fichier',
                'type' => 'fournisseur',
                'scan_contrat' => [UploadedFile::fake()->create('contrat.pdf', 40, 'application/pdf')],
                'scan_fiscal' => [UploadedFile::fake()->create('fiscal.pdf', 40, 'application/pdf')],
            ])
            ->assertRedirect();

        $fiche = FournisseurPrestataire::query()
            ->where('nom_normalise', FournisseurPrestataire::normaliserNom('Sans Contrat Mais Fichier'))
            ->firstOrFail();

        $this->assertFalse($fiche->a_contrat);
        $this->assertFalse($fiche->a_dossier_fiscal);
        $this->assertFalse($fiche->aScanContrat());
        $this->assertFalse($fiche->aScanFiscal());
        $this->assertSame([], Storage::disk('local')->allFiles('fournisseurs-prestataires/'.$fiche->id));
    }

    public function test_mise_a_jour_sans_contrat_ni_fiscal_supprime_les_scans(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->assignRole('dg');

        $this->actingAs($user)
            ->post(route('fournisseurs-prestataires.store', absolute: false), [
                'nom' => 'Fiche Avec Scans',
                'type' => 'prestataire',
                'type_contrat' => 'Maintenance',
                'a_contrat' => '1',
                'a_dossier_fiscal' => '1',
                'scan_contrat' => [UploadedFile::fake()->create('contrat.pdf', 40, 'application/pdf')],
                'scan_fiscal' => [UploadedFile::fake()->create('fiscal.pdf', 40, 'application/pdf')],
            ])
            ->assertRedirect();

        $fiche = FournisseurPrestataire::query()
            ->where('nom_normalise', FournisseurPrestataire::normaliserNom('Fiche Avec Scans'))
            ->firstOrFail();

        $cheminContrat = $fiche->piecesContrat()[0]['chemin'];
        $cheminFiscal = $fiche->piecesFiscal()[0]['chemin'];
        Storage::disk('local')->assertExists($cheminContrat);
        Storage::disk('local')->assertExists($cheminFiscal);

        $this->actingAs($user)
            ->put(route('fournisseurs-prestataires.update', $fiche, absolute: false), [
                'nom' => 'Fiche Avec Scans',
                'type' => 'prestataire',
                'type_contrat' => 'Maintenance',
                'actif' => '1',
            ])
            ->assertRedirect();

        $fiche->refresh();
        $this->assertFalse($fiche->a_contrat);
        $this->assertFalse($fiche->a_dossier_fiscal);
        $this->assertFalse($fiche->aScanContrat());
        $this->assertFalse($fiche->aScanFiscal());
        $this->assertSame([], $fiche->scan_contrat_pieces);
        $this->assertSame([], $fiche->scan_fiscal_pieces);
        Storage::disk('local')->assertMissing($cheminContrat);
        Storage::disk('local')->assertMissing($cheminFiscal);
    }

    public function test_contrat_formalise_exige_un_scan(): void
    {
        $user = User::factory()->create();
        $user->assignRole('dg');

        $this->actingAs($user)
            ->from(route('fournisseurs-prestataires.create', absolute: false))
            ->post(route('fournisseurs-prestataires.store', absolute: false), [
                'nom' => 'Sans Scan SARL',
                'type' => 'fournisseur',
                'a_contrat' => '1',
            ])
            ->assertSessionHasErrors('scan_contrat');
    }

    public function test_dossier_fiscal_exige_un_scan(): void
    {
        $user = User::factory()->create();
        $user->assignRole('dg');

        $this->actingAs($user)
            ->from(route('fournisseurs-prestataires.create', absolute: false))
            ->post(route('fournisseurs-prestataires.store', absolute: false), [
                'nom' => 'Fiscal Sans Piece',
                'type' => 'fournisseur',
                'a_dossier_fiscal' => '1',
            ])
            ->assertSessionHasErrors('scan_fiscal');
    }

    public function test_utilisateur_sans_permission_ne_peut_pas_acceder(): void
    {
        $user = User::factory()->create();
        $user->assignRole('utilisateur');

        $this->actingAs($user)
            ->get(route('fournisseurs-prestataires.index', absolute: false))
            ->assertForbidden();
    }

    public function test_refus_doublon_nom_normalise(): void
    {
        $user = User::factory()->create();
        $user->assignRole('responsable_dossiers_prestataires');

        FournisseurPrestataire::factory()->create([
            'nom' => 'BILLY SERVICES',
            'nom_normalise' => 'billy services',
        ]);

        $this->actingAs($user)
            ->post(route('fournisseurs-prestataires.store', absolute: false), [
                'nom' => 'Billy   Services',
                'type' => 'prestataire',
            ])
            ->assertSessionHasErrors('nom');
    }

    public function test_export_pdf_recapitulatif(): void
    {
        $user = User::factory()->create(['name' => 'Anne Lethicia Taty']);
        $user->assignRole('dg');

        FournisseurPrestataire::factory()->create([
            'nom' => 'AIRTEL',
            'type_contrat' => 'API mobile',
            'a_contrat' => true,
            'a_dossier_fiscal' => false,
        ]);

        $this->actingAs($user)
            ->get(route('fournisseurs-prestataires.index', absolute: false))
            ->assertOk()
            ->assertSee('Imprimer le tableau', false)
            ->assertSee('border-emerald-600', false)
            ->assertSee('Nouvelle fiche', false);

        $this->actingAs($user)
            ->get(route('fournisseurs-prestataires.print', absolute: false))
            ->assertOk()
            ->assertSee('Document PDF', false)
            ->assertSee('Retour à la liste', false)
            ->assertSee('Télécharger', false)
            ->assertSee('data:application/pdf;base64,', false);
    }

    public function test_fiche_360_affiche_factures_et_synthese(): void
    {
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'DG')->value('id')]);
        $user->assignRole('dg');

        $fiche = FournisseurPrestataire::factory()->create([
            'nom' => 'ACS Services',
            'nom_normalise' => 'acs services',
        ]);

        $typeFacture = TypeCourrier::where('code', 'facture')->firstOrFail();
        $sens = SensCourrier::where('code', 'arrivee')->firstOrFail();
        $statut = StatutCourrier::where('code', 'recu')->firstOrFail();

        Courrier::create([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => $typeFacture->id,
            'statut_courrier_id' => $statut->id,
            'numero_registre' => 1,
            'numero_registre_annee' => (int) now()->format('Y'),
            'objet' => 'Facture entretien groupes',
            'expediteur_libelle' => 'ACS Services',
            'fournisseur_prestataire_id' => $fiche->id,
            'montant_facture' => 1_500_000,
            'createur_id' => $user->id,
            'structure_id' => $user->structure_id,
            'origine' => 'externe',
        ]);

        $this->actingAs($user)
            ->get(route('fournisseurs-prestataires.show', $fiche, absolute: false))
            ->assertOk()
            ->assertSee('Facture entretien groupes', false)
            ->assertSee('Identité', false)
            ->assertSee('Factures', false)
            ->assertSee('Moratoires', false);
    }

    public function test_creation_facture_exige_fournisseur_prestataire(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $user->assignRole('secretaire_direction');

        $fiche = FournisseurPrestataire::factory()->create([
            'nom' => 'AF.COM',
            'telephone' => '+242060000099',
        ]);

        $payload = [
            'sens' => 'arrivee',
            'numero_fulgurant' => 'REG-fp-req/2026',
            'type_courrier_id' => TypeCourrier::where('code', 'facture')->value('id'),
            'objet' => 'Facture sans référentiel',
            'expediteur_telephone' => '+242060000001',
            'service_demandeur_structure_id' => Structure::where('code', 'DAF')->value('id'),
            'montant_facture' => '250000',
            'date_reception' => now()->toDateString(),
            'fichier' => UploadedFile::fake()->create('f.pdf', 20, 'application/pdf'),
        ];

        $this->actingAs($user)
            ->post(route('courriers.store', absolute: false), $payload)
            ->assertSessionHasErrors('fournisseur_prestataire_id');

        $this->actingAs($user)
            ->post(route('courriers.store', absolute: false), array_merge($payload, [
                'numero_fulgurant' => 'REG-fp-ok/2026',
                'fournisseur_prestataire_id' => $fiche->id,
            ]))
            ->assertRedirect();

        $courrier = Courrier::query()->where('objet', 'Facture sans référentiel')->firstOrFail();
        $this->assertSame($fiche->id, (int) $courrier->fournisseur_prestataire_id);
        $this->assertSame('AF.COM', $courrier->expediteur_libelle);
    }

    public function test_formulaire_ne_propose_que_les_dossiers_sous_mes_dossiers(): void
    {
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $user->assignRole('responsable_dossiers_prestataires');

        $autre = User::factory()->create();
        $racineAutre = app(MesDossiersRacineService::class)->createDefaultRacinePourCommande($autre);
        Dossier::query()->create([
            'parent_id' => $racineAutre->id,
            'nom' => 'Dossier autre utilisateur',
            'actif' => true,
            'ordre' => 0,
            'createur_id' => $autre->id,
            'proprietaire_id' => $autre->id,
        ]);

        $institutionnel = Dossier::query()->create([
            'parent_id' => null,
            'nom' => 'Circulaires institutionnelles',
            'actif' => true,
            'ordre' => 0,
            'createur_id' => $user->id,
            'est_racine_org' => true,
        ]);

        $racine = app(MesDossiersRacineService::class)->createDefaultRacinePourCommande($user);
        $billy = Dossier::query()->create([
            'parent_id' => $racine->id,
            'nom' => 'Billy Services',
            'description' => 'Dossier fournisseur / prestataire (classement factures).',
            'actif' => true,
            'ordre' => 0,
            'createur_id' => $user->id,
            'proprietaire_id' => $user->id,
        ]);
        $sozo = Dossier::query()->create([
            'parent_id' => $racine->id,
            'nom' => 'Edition les SOZO',
            'description' => 'Dossier fournisseur / prestataire (classement factures).',
            'actif' => true,
            'ordre' => 1,
            'createur_id' => $user->id,
            'proprietaire_id' => $user->id,
        ]);

        $html = $this->actingAs($user)
            ->get(route('fournisseurs-prestataires.create', absolute: false))
            ->assertOk()
            ->assertSee('Billy Services', false)
            ->assertSee('Edition les SOZO', false)
            ->assertDontSee('Circulaires institutionnelles', false)
            ->assertDontSee('Dossier autre utilisateur', false)
            ->getContent();

        $this->assertSame(0, substr_count($html, '>Mes dossiers</option>'));
        $this->assertStringContainsString('value="'.$billy->id.'"', $html);
        $this->assertStringContainsString('value="'.$sozo->id.'"', $html);
        $this->assertStringNotContainsString('value="'.$racine->id.'"', $html);
        $this->assertStringNotContainsString('value="'.$institutionnel->id.'"', $html);

        $this->actingAs($user)
            ->post(route('fournisseurs-prestataires.store', absolute: false), [
                'nom' => 'Billy lié',
                'type' => 'prestataire',
                'dossier_id' => $billy->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fournisseur_prestataires', [
            'nom' => 'Billy lié',
            'dossier_id' => $billy->id,
        ]);

        $this->actingAs($user)
            ->post(route('fournisseurs-prestataires.store', absolute: false), [
                'nom' => 'Mauvais dossier',
                'type' => 'prestataire',
                'dossier_id' => $institutionnel->id,
            ])
            ->assertSessionHasErrors('dossier_id');
    }
}
