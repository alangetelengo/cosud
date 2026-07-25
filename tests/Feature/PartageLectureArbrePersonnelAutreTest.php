<?php

namespace Tests\Feature;

use App\Models\Dossier;
use App\Models\DossierPartage;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PartageLectureArbrePersonnelAutreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    /** @return array{0: User, 1: User, 2: Dossier, 3: Structure} */
    private function scenarioArbrePersoCollegue(bool $ecriturePartage): array
    {
        $structure = Structure::create([
            'nom' => 'Service test partage',
            'code' => 'SVC-PART-'.uniqid(),
            'type' => 'service',
            'actif' => true,
        ]);

        $owner = User::factory()->create(['structure_id' => $structure->id]);
        $guest = User::factory()->create(['structure_id' => $structure->id]);
        $owner->assignRole(Role::findByName('utilisateur', 'web'));
        $guest->assignRole(Role::findByName('utilisateur', 'web'));

        $racine = Dossier::create([
            'parent_id' => null,
            'nom' => 'Mes dossiers',
            'code' => 'MES-'.$owner->id,
            'createur_id' => $owner->id,
            'proprietaire_id' => $owner->id,
            'structure_id' => $structure->id,
            'racine_utilisateur_id' => $owner->id,
            'actif' => true,
            'ordre' => 0,
        ]);

        $enfant = Dossier::create([
            'parent_id' => $racine->id,
            'nom' => 'Dossier partagé lecture',
            'code' => 'CHILD-'.uniqid(),
            'createur_id' => $owner->id,
            'proprietaire_id' => $owner->id,
            'structure_id' => null,
            'actif' => true,
            'ordre' => 0,
        ]);

        DossierPartage::create([
            'dossier_id' => $enfant->id,
            'user_id' => $guest->id,
            'partage_par_id' => $owner->id,
            'droits_lecture' => true,
            'droits_ecriture' => $ecriturePartage,
            'droits_suppression' => false,
            'propager_aux_sous_dossiers' => false,
        ]);

        return [$owner, $guest, $enfant, $structure];
    }

    public function test_creation_sous_dossier_interdite_si_lecture_seule_sur_espace_perso_dun_collegue(): void
    {
        [, $guest, $enfant] = $this->scenarioArbrePersoCollegue(false);

        $this->actingAs($guest)
            ->post(route('dossiers.store'), [
                'nom' => 'Sous-dossier non autorisé',
                'parent_id' => $enfant->id,
            ])
            ->assertForbidden();
    }

    public function test_creation_sous_dossier_autorisee_si_partage_ecriture_sur_espace_perso_dun_collegue(): void
    {
        [, $guest, $enfant] = $this->scenarioArbrePersoCollegue(true);

        $this->actingAs($guest)
            ->post(route('dossiers.store'), [
                'nom' => 'Sous-dossier autorisé',
                'parent_id' => $enfant->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('dossiers', [
            'parent_id' => $enfant->id,
            'nom' => 'Sous-dossier autorisé',
        ]);
    }

    public function test_peux_deposer_faux_sur_dossier_arbre_perso_autre_avec_lecture_seule(): void
    {
        [, $guest, $enfant] = $this->scenarioArbrePersoCollegue(false);

        $this->assertFalse($enfant->fresh()->peuxDeposer($guest));
    }

    public function test_peux_deposer_vrai_sur_dossier_arbre_perso_autre_avec_ecriture(): void
    {
        [, $guest, $enfant] = $this->scenarioArbrePersoCollegue(true);

        $this->assertTrue($enfant->fresh()->peuxDeposer($guest));
    }
}
