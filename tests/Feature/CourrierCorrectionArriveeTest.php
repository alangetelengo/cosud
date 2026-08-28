<?php

namespace Tests\Feature;

use App\Models\Courrier;
use App\Models\FournisseurPrestataire;
use App\Models\PrioriteCourrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\Structure;
use App\Models\TypeCourrier;
use App\Models\User;
use Database\Seeders\CircuitCourrierSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourrierCorrectionArriveeTest extends TestCase
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

    public function test_particuliere_peut_corriger_enregistrement_arrivee(): void
    {
        $particuliere = $this->creerUtilisateurAvecRole('particulier_dg');
        $courrier = $this->creerCourrierArrivee($particuliere);
        $dafId = Structure::where('code', 'DAF')->value('id');
        $fiche = FournisseurPrestataire::factory()->create([
            'nom' => 'EEC corrigé',
            'telephone' => '+242060000099',
        ]);

        $this->assertTrue($particuliere->can('corriger', $courrier));

        $this->actingAs($particuliere)
            ->get(route('courriers.edit', $courrier, absolute: false))
            ->assertOk()
            ->assertSee('Corrigez une erreur de saisie', false)
            ->assertSee('id="bloc-service-demandeur"', false)
            ->assertSee('id="bloc-fournisseur-prestataire"', false)
            ->assertSee('data-telephone-requis="1"', false)
            ->assertSee('EEC corrigé', false);

        $this->actingAs($particuliere)
            ->put(route('courriers.update', $courrier, absolute: false), [
                'objet' => 'Facture corrigée',
                'fournisseur_prestataire_id' => $fiche->id,
                'expediteur_telephone' => '+242060000099',
                'numero_fulgurant' => $courrier->numero_fulgurant,
                'date_reception' => now()->toDateString(),
                'type_courrier_id' => $courrier->type_courrier_id,
                'service_demandeur_structure_id' => $dafId,
                'montant_facture' => '1250000',
            ])
            ->assertRedirect(route('courriers.show', $courrier, absolute: false));

        $this->assertDatabaseHas('courriers', [
            'id' => $courrier->id,
            'objet' => 'Facture corrigée',
            'expediteur_libelle' => 'EEC corrigé',
            'expediteur_telephone' => '+242060000099',
            'fournisseur_prestataire_id' => $fiche->id,
            'service_demandeur_structure_id' => $dafId,
        ]);
    }

    public function test_responsable_dossiers_peut_corriger_enregistrement_arrivee(): void
    {
        $responsable = $this->creerUtilisateurAvecRole('responsable_dossiers_prestataires');
        $courrier = $this->creerCourrierArrivee($responsable);
        $dafId = Structure::where('code', 'DAF')->value('id');
        $fiche = FournisseurPrestataire::factory()->create(['nom' => 'Fournisseur']);

        $this->actingAs($responsable)
            ->put(route('courriers.update', $courrier, absolute: false), [
                'objet' => 'Objet corrigé par responsable',
                'fournisseur_prestataire_id' => $fiche->id,
                'expediteur_telephone' => '+242060000088',
                'numero_fulgurant' => $courrier->numero_fulgurant,
                'type_courrier_id' => $courrier->type_courrier_id,
                'service_demandeur_structure_id' => $dafId,
                'montant_facture' => '1250000',
            ])
            ->assertRedirect(route('courriers.show', $courrier, absolute: false));

        $this->assertSame('Objet corrigé par responsable', $courrier->fresh()->objet);
        $this->assertSame($fiche->id, (int) $courrier->fresh()->fournisseur_prestataire_id);
    }

    public function test_correction_facture_exige_fournisseur_prestataire(): void
    {
        $particuliere = $this->creerUtilisateurAvecRole('particulier_dg');
        $courrier = $this->creerCourrierArrivee($particuliere);
        $dafId = Structure::where('code', 'DAF')->value('id');

        $this->actingAs($particuliere)
            ->from(route('courriers.edit', $courrier, absolute: false))
            ->put(route('courriers.update', $courrier, absolute: false), [
                'objet' => 'Facture sans référentiel',
                'expediteur_telephone' => '+242060000099',
                'numero_fulgurant' => $courrier->numero_fulgurant,
                'type_courrier_id' => $courrier->type_courrier_id,
                'service_demandeur_structure_id' => $dafId,
                'montant_facture' => '1250000',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('fournisseur_prestataire_id');
    }

    public function test_correction_facture_exige_telephone(): void
    {
        $particuliere = $this->creerUtilisateurAvecRole('particulier_dg');
        $courrier = $this->creerCourrierArrivee($particuliere);
        $dafId = Structure::where('code', 'DAF')->value('id');
        $fiche = FournisseurPrestataire::factory()->create([
            'nom' => 'EEC',
            'telephone' => null,
            'email' => null,
        ]);

        $this->actingAs($particuliere)
            ->from(route('courriers.edit', $courrier, absolute: false))
            ->put(route('courriers.update', $courrier, absolute: false), [
                'objet' => 'Facture sans téléphone',
                'fournisseur_prestataire_id' => $fiche->id,
                'expediteur_telephone' => '',
                'numero_fulgurant' => $courrier->numero_fulgurant,
                'type_courrier_id' => $courrier->type_courrier_id,
                'service_demandeur_structure_id' => $dafId,
                'montant_facture' => '1250000',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('expediteur_telephone');
    }

    public function test_correction_facture_exige_service_demandeur(): void
    {
        $particuliere = $this->creerUtilisateurAvecRole('particulier_dg');
        $courrier = $this->creerCourrierArrivee($particuliere);
        $fiche = FournisseurPrestataire::factory()->create(['nom' => 'EEC']);

        $this->actingAs($particuliere)
            ->from(route('courriers.edit', $courrier, absolute: false))
            ->put(route('courriers.update', $courrier, absolute: false), [
                'objet' => 'Facture sans service',
                'fournisseur_prestataire_id' => $fiche->id,
                'expediteur_telephone' => '+242060000099',
                'numero_fulgurant' => $courrier->numero_fulgurant,
                'type_courrier_id' => $courrier->type_courrier_id,
                'montant_facture' => '1250000',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('service_demandeur_structure_id');
    }

    public function test_correction_passage_demande_exige_telephone_dynamique(): void
    {
        $particuliere = $this->creerUtilisateurAvecRole('particulier_dg');
        $courrier = $this->creerCourrierArrivee($particuliere);
        $demandeId = TypeCourrier::where('code', 'demande')->value('id');

        $html = $this->actingAs($particuliere)
            ->get(route('courriers.edit', $courrier, absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/value="'.$demandeId.'"[^>]*data-telephone-requis="1"/',
            $html
        );
        $this->assertStringContainsString('synchroniserSelonType', $html);

        $this->actingAs($particuliere)
            ->from(route('courriers.edit', $courrier, absolute: false))
            ->put(route('courriers.update', $courrier, absolute: false), [
                'objet' => 'Demande sans téléphone',
                'expediteur_libelle' => 'Demandeur',
                'expediteur_telephone' => '',
                'numero_fulgurant' => $courrier->numero_fulgurant,
                'type_courrier_id' => $demandeId,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('expediteur_telephone');
    }

    public function test_orientation_n_inclut_pas_antenne_pointe_noire_dans_directions(): void
    {
        $this->assertTrue(
            Structure::servicesDemandeurs()->where('code', 'ANT')->exists(),
            'Pointe-Noire doit rester sélectionnable comme service demandeur.'
        );
        $this->assertFalse(
            Structure::directionsOrientation()->where('code', 'ANT')->exists(),
            'Pointe-Noire ne doit pas être une direction d’orientation.'
        );

        $dg = User::factory()->create(['structure_id' => Structure::where('code', 'DG')->value('id')]);
        $dg->assignRole('dg');

        $courrier = $this->creerCourrierArrivee($dg);
        $statutParapheur = StatutCourrier::query()
            ->where('sens_courrier_id', $courrier->sens_courrier_id)
            ->where('code', 'en_parapheur')
            ->firstOrFail();
        $courrier->update(['statut_courrier_id' => $statutParapheur->id]);

        $html = $this->actingAs($dg)
            ->get(route('courriers.show', $courrier, absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            'DIRECTION DEPARTEMENTALE DE POINTE-NOIRE',
            $html,
            'L’antenne Pointe-Noire ne doit pas apparaître dans le formulaire d’orientation.'
        );
    }

    public function test_secretaire_ne_peut_pas_corriger_arrivee(): void
    {
        $secretaire = $this->creerUtilisateurAvecRole('secretaire_direction');
        $courrier = $this->creerCourrierArrivee($secretaire);

        $this->assertFalse($secretaire->can('corriger', $courrier));

        $this->actingAs($secretaire)
            ->get(route('courriers.edit', $courrier, absolute: false))
            ->assertForbidden();

        $this->actingAs($secretaire)
            ->get(route('courriers.show', $courrier, absolute: false))
            ->assertOk()
            ->assertDontSee('>Modifier</a>', false);
    }

    public function test_arrivee_cloturee_ne_peut_plus_etre_corrigee(): void
    {
        $particuliere = $this->creerUtilisateurAvecRole('particulier_dg');
        $courrier = $this->creerCourrierArrivee($particuliere);

        $statutCloture = StatutCourrier::query()
            ->where('sens_courrier_id', $courrier->sens_courrier_id)
            ->where('code', 'cloture')
            ->first();

        if (! $statutCloture) {
            $this->markTestSkipped('Statut cloture arrivée absent du référentiel.');
        }

        $courrier->update(['statut_courrier_id' => $statutCloture->id]);

        $this->assertFalse($particuliere->can('corriger', $courrier->fresh()));

        $this->actingAs($particuliere)
            ->get(route('courriers.edit', $courrier, absolute: false))
            ->assertForbidden();
    }

    private function creerUtilisateurAvecRole(string $role): User
    {
        $user = User::factory()->create([
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function creerCourrierArrivee(User $user): Courrier
    {
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'recu')->firstOrFail();

        return Courrier::create([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => TypeCourrier::where('code', 'facture')->value('id'),
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => PrioriteCourrier::where('code', 'normale')->value('id'),
            'numero_registre' => random_int(100, 900),
            'numero_registre_annee' => (int) now()->year,
            'numero_fulgurant' => '192/2026/DAF/SAGP',
            'origine' => Courrier::ORIGINE_EXTERNE,
            'date_reception' => now()->toDateString(),
            'expediteur_libelle' => 'EEC',
            'objet' => 'Facture électricité',
            'montant_facture' => 1_250_000,
            'createur_id' => $user->id,
            'structure_id' => $user->structure_id,
        ]);
    }
}
