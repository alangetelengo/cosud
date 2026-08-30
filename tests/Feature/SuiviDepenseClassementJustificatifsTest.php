<?php

namespace Tests\Feature;

use App\Models\CategorieDepense;
use App\Models\Courrier;
use App\Models\Document;
use App\Models\Dossier;
use App\Models\PrioriteCourrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\Structure;
use App\Models\SuiviPaiement;
use App\Models\TypeCourrier;
use App\Models\User;
use App\Services\CourrierClassementDossierService;
use App\Services\SuiviDepenseClassementService;
use App\Services\SuiviPaiementService;
use Database\Seeders\CategorieDepenseSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Database\Seeders\TypeDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SuiviDepenseClassementJustificatifsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleAndPermissionSeeder::class,
            StructureSeeder::class,
            TypeDocumentSeeder::class,
            CourrierReferentielSeeder::class,
            CategorieDepenseSeeder::class,
        ]);
        Storage::fake('public');
    }

    public function test_saisie_dg_depose_justificatifs_chez_eleni(): void
    {
        $eleni = $this->creerEleni();
        $dg = User::factory()->create([
            'structure_id' => Structure::where('code', 'DG')->value('id'),
        ]);
        $dg->assignRole('dg');

        $catPaie = CategorieDepense::query()->where('code', CategorieDepense::CODE_PAIE)->firstOrFail();

        $ligne = app(SuiviPaiementService::class)->creerRemiseDg($dg, [
            'categorie_depense_id' => $catPaie->id,
            'date_suivi' => '2026-08-24',
            'intitule' => 'Remise DG pour Eleni',
            'montant' => 75000,
            'beneficiaire_libelle' => 'Bénéficiaire DG',
            'justificatifs' => [
                UploadedFile::fake()->create('scan-remise.pdf', 30, 'application/pdf'),
            ],
        ]);

        $dossier = Dossier::query()->findOrFail($ligne->dossier_id);
        $this->assertSame((int) $eleni->id, (int) $dossier->proprietaire_id);
        $this->assertTrue(app(SuiviDepenseClassementService::class)->estDossierAttente($dossier));
        $this->assertTrue($dossier->visiblePar($eleni));
        $this->assertTrue($eleni->can('classerDossier', $ligne));
        // Le DG reste créateur du dossier d’attente (traçabilité), Eleni en est propriétaire.

        $document = Document::query()->where('dossier_id', $dossier->id)->firstOrFail();
        $this->assertSame((int) $dg->id, (int) $document->createur_id);
        $this->assertSame((int) $eleni->id, (int) $document->proprietaire_id);
        $this->assertTrue($document->visiblePar($eleni));

        $this->actingAs($eleni)
            ->get(route('suivi-paiements.classer', $ligne, absolute: false))
            ->assertOk();

        $this->actingAs($eleni)
            ->post(route('suivi-paiements.classer.store', $ligne, absolute: false), [
                'mode' => 'nouveau',
                'nom_dossier' => 'Bénéficiaire DG',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($ligne->fresh()->estClasseMetier());
        $this->assertTrue($ligne->fresh()->dossier->visiblePar($eleni));
    }

    public function test_saisie_depense_depose_justificatifs_en_attente_a_classer(): void
    {
        $eleni = $this->creerEleni();
        $catPaie = CategorieDepense::query()->where('code', CategorieDepense::CODE_PAIE)->firstOrFail();

        $this->actingAs($eleni)
            ->post(route('suivi-paiements.remise-dg', absolute: false), [
                'categorie_depense_id' => $catPaie->id,
                'date_suivi' => '2026-08-24',
                'intitule' => 'Remboursement frais médicaux',
                'montant' => '185000',
                'beneficiaire_libelle' => 'ayant droit',
                'numero_piece' => 'PH-2026-12',
                'justificatifs' => [
                    UploadedFile::fake()->create('facture-medicale.pdf', 40, 'application/pdf'),
                    UploadedFile::fake()->image('scan-ordonnance.jpg'),
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $ligne = SuiviPaiement::query()->where('intitule', 'Remboursement frais médicaux')->firstOrFail();
        $this->assertNotNull($ligne->dossier_id);
        $this->assertFalse($ligne->estClasseMetier());

        $dossier = Dossier::query()->findOrFail($ligne->dossier_id);
        $this->assertTrue(app(SuiviDepenseClassementService::class)->estDossierAttente($dossier));
        $this->assertSame(2, Document::query()->where('dossier_id', $dossier->id)->count());

        $this->actingAs($eleni)
            ->get(route('suivi-paiements.index', ['annee' => 2026], absolute: false))
            ->assertOk()
            ->assertSee('À classer', false)
            ->assertSee(route('suivi-paiements.classer', $ligne, absolute: false), false);
    }

    public function test_eleni_classe_manuellement_comme_taty(): void
    {
        $eleni = $this->creerEleni();
        $cat = CategorieDepense::query()->where('code', CategorieDepense::CODE_COMMISSION)->firstOrFail();
        $ligne = app(SuiviPaiementService::class)->creerRemiseDg($eleni, [
            'categorie_depense_id' => $cat->id,
            'date_suivi' => '2026-08-12',
            'intitule' => 'Réquisition agents',
            'montant' => 200000,
            'beneficiaire_libelle' => 'ayant droit',
            'justificatifs' => [UploadedFile::fake()->create('piece.pdf', 20, 'application/pdf')],
        ]);

        $this->actingAs($eleni)
            ->post(route('suivi-paiements.classer.store', $ligne, absolute: false), [
                'mode' => 'nouveau',
                'nom_dossier' => 'ayant droit',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $ligne->refresh();
        $this->assertTrue($ligne->estClasseMetier());
        $this->assertSame('ayant droit', $ligne->dossier->nom);
        $this->assertSame(
            SuiviDepenseClassementService::NOM_PARENT_PRESTATAIRES,
            $ligne->dossier->parent?->nom
        );
    }

    public function test_filtre_periode_restreint_la_liste(): void
    {
        $eleni = $this->creerEleni();
        $cat = CategorieDepense::query()->where('code', CategorieDepense::CODE_TTF)->firstOrFail();
        $service = app(SuiviPaiementService::class);

        $service->creerRemiseDg($eleni, [
            'categorie_depense_id' => $cat->id,
            'date_suivi' => '2026-08-05',
            'intitule' => 'Hors periode',
            'montant' => 1000,
            'beneficiaire_libelle' => 'A',
        ]);
        $service->creerRemiseDg($eleni, [
            'categorie_depense_id' => $cat->id,
            'date_suivi' => '2026-08-20',
            'intitule' => 'Dans periode',
            'montant' => 2000,
            'beneficiaire_libelle' => 'B',
        ]);

        $this->actingAs($eleni)
            ->get(route('suivi-paiements.index', [
                'annee' => 2026,
                'date_debut' => '2026-08-15',
                'date_fin' => '2026-08-25',
            ], absolute: false))
            ->assertOk()
            ->assertSee('Dans periode', false)
            ->assertDontSee('Hors periode', false);
    }

    public function test_impression_etat_paiement_ok(): void
    {
        $eleni = $this->creerEleni();
        $cat = CategorieDepense::query()->where('code', CategorieDepense::CODE_PAIE)->firstOrFail();
        app(SuiviPaiementService::class)->creerRemiseDg($eleni, [
            'categorie_depense_id' => $cat->id,
            'date_suivi' => '2026-08-24',
            'intitule' => 'Ligne impression',
            'montant' => 50000,
            'beneficiaire_libelle' => 'X',
        ]);

        $this->actingAs($eleni)
            ->get(route('suivi-paiements.print', ['annee' => 2026], absolute: false))
            ->assertOk()
            ->assertSee('Aperçu PDF', false)
            ->assertSee('data:application/pdf;base64,', false)
            ->assertSee('Retour à la liste', false);
    }

    public function test_liste_affiche_derniere_ligne_ajoutee_en_premier(): void
    {
        $eleni = $this->creerEleni();
        $cat = CategorieDepense::query()->where('code', CategorieDepense::CODE_TTF)->firstOrFail();
        $service = app(SuiviPaiementService::class);

        $ancienne = $service->creerRemiseDg($eleni, [
            'categorie_depense_id' => $cat->id,
            'date_suivi' => '2026-08-20',
            'intitule' => 'Ancienne ligne',
            'montant' => 1000,
            'beneficiaire_libelle' => 'A',
        ]);
        $recente = $service->creerRemiseDg($eleni, [
            'categorie_depense_id' => $cat->id,
            'date_suivi' => '2026-08-01',
            'intitule' => 'Dernière ajoutée date ancienne',
            'montant' => 2000,
            'beneficiaire_libelle' => 'B',
        ]);

        $this->assertTrue($recente->id > $ancienne->id);

        $html = $this->actingAs($eleni)
            ->get(route('suivi-paiements.index', ['annee' => 2026], absolute: false))
            ->assertOk()
            ->getContent();

        $posRecente = strpos($html, 'Dernière ajoutée date ancienne');
        $posAncienne = strpos($html, 'Ancienne ligne');
        $this->assertNotFalse($posRecente);
        $this->assertNotFalse($posAncienne);
        $this->assertLessThan($posAncienne, $posRecente);
    }

    public function test_eleni_ne_peut_pas_classer_facture_prestataire_circuit(): void
    {
        $eleni = $this->creerEleni();
        $ac = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $ac->assignRole('agent_comptable');

        $courrier = $this->creerFacture($ac, 'Facture électricité');
        $ligne = app(SuiviPaiementService::class)->creerDepuisEntreeCheque($courrier, $ac, 500000, [
            'numero_piece' => '0000322',
            'banque' => 'BCH',
            'beneficiaire_libelle' => 'EEC',
        ]);

        $this->assertTrue($ligne->estClassementReserveFacturesPrestataires());
        $this->assertFalse($eleni->can('classerDossier', $ligne));

        $this->actingAs($eleni)
            ->get(route('suivi-paiements.classer', $ligne, absolute: false))
            ->assertForbidden();

        $this->actingAs($eleni)
            ->post(route('suivi-paiements.classer.store', $ligne, absolute: false), [
                'mode' => 'nouveau',
                'nom_dossier' => 'EEC',
            ])
            ->assertForbidden();

        $this->actingAs($eleni)
            ->get(route('suivi-paiements.index', ['annee' => 2026], absolute: false))
            ->assertOk()
            ->assertSee('Facture électricité', false)
            ->assertSee('À classer (Taty)', false)
            ->assertDontSee(route('suivi-paiements.classer', $ligne, absolute: false), false);
    }

    public function test_ligne_circuit_herite_dossier_courrier_et_sync_classement(): void
    {
        $ac = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $ac->assignRole('agent_comptable');

        $taty = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $taty->assignRole('responsable_dossiers_prestataires');

        $courrier = $this->creerFacture($ac, 'Gardiennage ACSI');
        $dossier = Dossier::query()->create([
            'parent_id' => null,
            'nom' => 'SOMAC',
            'actif' => true,
            'ordre' => 1,
            'structure_id' => $taty->structure_id,
            'createur_id' => $taty->id,
            'proprietaire_id' => $taty->id,
        ]);
        $courrier->update(['dossier_id' => $dossier->id]);

        $ligne = app(SuiviPaiementService::class)->creerDepuisEntreeCheque($courrier, $ac, 594500, [
            'numero_piece' => 'Cheque 0000322',
            'banque' => 'BCH',
            'beneficiaire_libelle' => 'SOMAC',
        ]);

        $this->assertSame((int) $dossier->id, (int) $ligne->dossier_id);
        $this->assertTrue($ligne->estClasseMetier());

        $nouveau = Dossier::query()->create([
            'parent_id' => null,
            'nom' => 'SOMAC Bis',
            'actif' => true,
            'ordre' => 2,
            'structure_id' => $taty->structure_id,
            'createur_id' => $taty->id,
            'proprietaire_id' => $taty->id,
        ]);

        app(CourrierClassementDossierService::class)->classer($courrier, $taty, [
            'mode' => 'existant',
            'dossier_id' => $nouveau->id,
        ]);

        $this->assertSame((int) $nouveau->id, (int) $ligne->fresh()->dossier_id);
    }

    private function creerEleni(): User
    {
        $user = User::factory()->create([
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
        ]);
        $user->assignRole('responsable_suivi_depenses');

        return $user;
    }

    private function creerFacture(User $createur, string $objet): Courrier
    {
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'recu')->firstOrFail();

        return Courrier::create([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => TypeCourrier::where('code', 'facture')->value('id'),
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => PrioriteCourrier::where('code', 'normale')->value('id'),
            'numero_registre' => random_int(100, 900),
            'numero_registre_annee' => 2026,
            'numero_fulgurant' => 'F-'.random_int(1000, 9999).'/2026',
            'origine' => Courrier::ORIGINE_EXTERNE,
            'date_reception' => now()->toDateString(),
            'expediteur_libelle' => 'SOMAC',
            'objet' => $objet,
            'createur_id' => $createur->id,
            'structure_id' => $createur->structure_id,
        ]);
    }
}
