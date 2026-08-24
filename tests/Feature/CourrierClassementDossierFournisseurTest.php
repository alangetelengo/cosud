<?php

namespace Tests\Feature;

use App\Models\CircuitCourrier;
use App\Models\Courrier;
use App\Models\Document;
use App\Models\Dossier;
use App\Models\PrioriteCourrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\StatutDocument;
use App\Models\Structure;
use App\Models\TypeCourrier;
use App\Models\TypeDocument;
use App\Models\User;
use App\Services\CircuitCourrierMoteurService;
use App\Services\CourrierClassementDossierService;
use App\Services\MesDossiersRacineService;
use Database\Seeders\CircuitCourrierSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Database\Seeders\TypeDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourrierClassementDossierFournisseurTest extends TestCase
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

    public function test_taty_peut_creer_et_classer_dans_dossier_fournisseur(): void
    {
        $taty = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $taty->assignRole('responsable_dossiers_prestataires');

        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->instruire($this->demarrerFacture($dg, 'Facture AF.COM à classer'), $dg, 'Bon pour accord.', $ac->id);

        $document = $this->attacherPiece($courrier, $taty);

        $this->actingAs($taty)
            ->from(route('courriers.show', $courrier, absolute: false))
            ->post(route('courriers.classer-dossier', $courrier, absolute: false), [
                'mode' => 'nouveau',
                'nom_dossier' => 'AF.COM',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $courrier->refresh();
        $this->assertNotNull($courrier->dossier_id);
        $this->assertSame('AF.COM', $courrier->dossier->nom);
        $this->assertSame((int) $courrier->dossier_id, (int) $document->fresh()->dossier_id);
    }

    public function test_taty_peut_classer_dans_dossier_existant(): void
    {
        $taty = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $taty->assignRole('responsable_dossiers_prestataires');

        $dossier = Dossier::create([
            'nom' => 'AF.COM',
            'actif' => true,
            'ordre' => 0,
            'structure_id' => $taty->structure_id,
            'createur_id' => $taty->id,
            'proprietaire_id' => $taty->id,
            'confidentiel' => false,
            'notify_sms' => false,
        ]);

        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        $moteur = app(CircuitCourrierMoteurService::class);
        $courrier = $moteur->instruire($this->demarrerFacture($dg, 'Facture classement existant'), $dg, 'BPA.', $ac->id);

        $this->actingAs($taty)
            ->post(route('courriers.classer-dossier', $courrier, absolute: false), [
                'mode' => 'existant',
                'dossier_id' => $dossier->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame((int) $dossier->id, (int) $courrier->fresh()->dossier_id);
    }

    public function test_agent_comptable_ne_peut_pas_classer(): void
    {
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        $dg = $this->creerDg();
        $courrier = app(CircuitCourrierMoteurService::class)
            ->instruire($this->demarrerFacture($dg, 'Facture refus classement'), $dg, 'BPA.', $ac->id);

        $this->actingAs($ac)
            ->post(route('courriers.classer-dossier', $courrier, absolute: false), [
                'mode' => 'nouveau',
                'nom_dossier' => 'Interdit',
            ])
            ->assertForbidden();
    }

    public function test_mad_ne_peut_pas_etre_classe_dans_dossier_fournisseur(): void
    {
        $taty = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $taty->assignRole('responsable_dossiers_prestataires');

        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');
        $courrier = app(CircuitCourrierMoteurService::class)
            ->instruire($this->demarrerMad($dg, 'MAD PNR sans classement'), $dg, 'BPA.', $ac->id);

        $this->assertFalse($taty->can('classerDossier', $courrier));

        // Taty (voir-factures uniquement) ne consulte pas les MAD.
        $this->actingAs($taty)
            ->get(route('courriers.show', $courrier, absolute: false))
            ->assertForbidden();

        $this->actingAs($taty)
            ->post(route('courriers.classer-dossier', $courrier, absolute: false), [
                'mode' => 'nouveau',
                'nom_dossier' => 'Interdit MAD',
            ])
            ->assertForbidden();
    }

    public function test_particulier_dg_secretaire_et_eleni_ne_peuvent_pas_classer(): void
    {
        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');
        $courrier = app(CircuitCourrierMoteurService::class)
            ->instruire($this->demarrerFacture($dg, 'Facture rôles classement'), $dg, 'BPA.', $ac->id);

        foreach (['particulier_dg', 'secretaire_direction', 'responsable_suivi_depenses'] as $role) {
            $user = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
            $user->assignRole($role);

            $this->assertFalse($user->can('classerDossier', $courrier), "Le rôle {$role} ne doit pas classer.");

            $this->actingAs($user)
                ->post(route('courriers.classer-dossier', $courrier, absolute: false), [
                    'mode' => 'nouveau',
                    'nom_dossier' => 'Interdit '.$role,
                ])
                ->assertForbidden();
        }
    }

    public function test_page_suivi_affiche_a_classer(): void
    {
        $taty = User::factory()->create();
        $taty->assignRole('responsable_dossiers_prestataires');

        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');

        app(CircuitCourrierMoteurService::class)
            ->instruire($this->demarrerFacture($dg, 'Facture non classée suivi'), $dg, 'BPA.', $ac->id);

        $this->actingAs($taty)
            ->get(route('suivi-factures-fournisseurs.index', absolute: false))
            ->assertOk()
            ->assertSee('À classer', false)
            ->assertSee('Classer', false);
    }

    public function test_mode_nouveau_refuse_dossier_homonyme_sans_droit_ecriture(): void
    {
        $taty = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $taty->assignRole('responsable_dossiers_prestataires');
        $autre = User::factory()->create(['structure_id' => $taty->structure_id]);

        $racine = app(MesDossiersRacineService::class)->createDefaultRacinePourCommande($taty);
        Dossier::create([
            'parent_id' => $racine->id,
            'nom' => 'AF.COM',
            'actif' => true,
            'ordre' => 0,
            'structure_id' => $taty->structure_id,
            'createur_id' => $autre->id,
            'proprietaire_id' => $autre->id,
            'confidentiel' => false,
            'notify_sms' => false,
        ]);

        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');
        $courrier = app(CircuitCourrierMoteurService::class)
            ->instruire($this->demarrerFacture($dg, 'Facture homonyme'), $dg, 'BPA.', $ac->id);

        $this->actingAs($taty)
            ->from(route('courriers.show', $courrier, absolute: false))
            ->post(route('courriers.classer-dossier', $courrier, absolute: false), [
                'mode' => 'nouveau',
                'nom_dossier' => 'AF.COM',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('nom_dossier');

        $this->assertNull($courrier->fresh()->dossier_id);
    }

    public function test_suggestion_trouve_dossier_proprietaire_par_nom_exact(): void
    {
        $taty = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $taty->assignRole('responsable_dossiers_prestataires');

        $dossier = Dossier::create([
            'nom' => 'AF.COM',
            'actif' => true,
            'ordre' => 0,
            'structure_id' => $taty->structure_id,
            'createur_id' => $taty->id,
            'proprietaire_id' => $taty->id,
            'confidentiel' => false,
            'notify_sms' => false,
        ]);

        $dg = $this->creerDg();
        $ac = User::factory()->create();
        $ac->assignRole('agent_comptable');
        $courrier = app(CircuitCourrierMoteurService::class)
            ->instruire($this->demarrerFacture($dg, 'Facture suggestion'), $dg, 'BPA.', $ac->id);

        $suggere = app(CourrierClassementDossierService::class)
            ->suggererDossier($taty, $courrier);

        $this->assertNotNull($suggere);
        $this->assertSame((int) $dossier->id, (int) $suggere->id);
    }

    private function creerDg(): User
    {
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'DG')->value('id')]);
        $user->assignRole('dg');

        return $user;
    }

    private function demarrerFacture(User $acteur, string $objet): Courrier
    {
        return $this->demarrerCourrierType($acteur, $objet, 'facture');
    }

    private function demarrerMad(User $acteur, string $objet): Courrier
    {
        return $this->demarrerCourrierType($acteur, $objet, 'mad');
    }

    private function demarrerCourrierType(User $acteur, string $objet, string $typeCode): Courrier
    {
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'recu')->firstOrFail();
        $type = TypeCourrier::where('code', $typeCode)->firstOrFail();

        $courrier = Courrier::create([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => $type->id,
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => PrioriteCourrier::where('code', 'normale')->value('id'),
            'numero_registre' => random_int(200, 900),
            'numero_registre_annee' => 2026,
            'origine' => 'externe',
            'date_reception' => now()->toDateString(),
            'objet' => $objet,
            'expediteur_libelle' => 'AF.COM',
            'createur_id' => $acteur->id,
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
            'service_demandeur_structure_id' => Structure::where('code', 'DAF')->value('id'),
        ]);

        $circuit = CircuitCourrier::where('code', 'facture_prestataire')->firstOrFail();

        return app(CircuitCourrierMoteurService::class)->demarrer($courrier, $circuit, $acteur);
    }

    private function attacherPiece(Courrier $courrier, User $user): Document
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
