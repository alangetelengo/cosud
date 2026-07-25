<?php

namespace Tests\Feature;

use App\Listeners\EnsureMesDossiersRacineExists;
use App\Models\Dossier;
use App\Models\Structure;
use App\Models\User;
use App\Services\MesDossiersRacineService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MesDossiersCreatePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_particulier_dg_voit_le_bouton_creer_un_dossier(): void
    {
        $user = $this->utilisateurParticulierDg();

        $this->actingAs($user)
            ->get(route('dossiers.index'))
            ->assertOk()
            ->assertSee('Créer un dossier', false);
    }

    public function test_connexion_cree_la_racine_mes_dossiers_avec_dossiers_create(): void
    {
        Event::forget(Login::class);
        Event::listen(Login::class, [EnsureMesDossiersRacineExists::class, 'handleLogin']);

        $user = $this->utilisateurParticulierDg();

        $this->assertNull(app(MesDossiersRacineService::class)->find($user));

        event(new Login('web', $user, false));

        $racine = app(MesDossiersRacineService::class)->find($user->fresh());
        $this->assertNotNull($racine);
        $this->assertSame('Mes dossiers', $racine->nom);
    }

    public function test_particulier_dg_peut_creer_un_sous_dossier_dans_mes_dossiers(): void
    {
        $user = $this->utilisateurParticulierDg();
        $racine = app(MesDossiersRacineService::class)->createDefaultRacinePourCommande($user);

        $this->actingAs($user)
            ->post(route('dossiers.store'), [
                'nom' => 'Projet Lucienne',
                'parent_id' => $racine->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('dossiers', [
            'parent_id' => $racine->id,
            'nom' => 'Projet Lucienne',
            'createur_id' => $user->id,
        ]);
    }

    public function test_utilisateur_avec_create_structure_seulement_peut_ecrire_dans_sa_racine_preexistante(): void
    {
        // Simule un compte dont la racine « Mes dossiers » a été créée avant l'introduction de la
        // permission dédiée dossiers.create (ex. sous l'ancien listener gated sur create-structure).
        $role = Role::firstOrCreate(['name' => 'utilisateur_sans_dossiers_create', 'guard_name' => 'web']);
        $role->syncPermissions(['dossiers.view', 'dossiers.create-structure']);

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->assertFalse($user->can('dossiers.create'));
        $this->assertTrue($user->can('dossiers.create-structure'));

        $racine = app(MesDossiersRacineService::class)->createDefaultRacinePourCommande($user);

        $this->actingAs($user)
            ->post(route('dossiers.store'), [
                'nom' => 'Sous-dossier malgré permission manquante',
                'parent_id' => $racine->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('dossiers', [
            'parent_id' => $racine->id,
            'nom' => 'Sous-dossier malgré permission manquante',
            'createur_id' => $user->id,
        ]);
    }

    public function test_particulier_dg_ne_peut_pas_creer_sous_racine_structure(): void
    {
        $user = $this->utilisateurParticulierDg();
        $structure = Structure::create([
            'nom' => 'Direction générale',
            'code' => 'DG-'.uniqid(),
            'type' => 'direction',
            'actif' => true,
        ]);
        $user->update(['structure_id' => $structure->id]);

        $racineOrg = Dossier::create([
            'parent_id' => null,
            'nom' => 'Racine DG',
            'code' => 'ORG-'.uniqid(),
            'est_racine_org' => true,
            'structure_id' => $structure->id,
            'createur_id' => $user->id,
            'proprietaire_id' => $user->id,
            'actif' => true,
            'ordre' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('dossiers.store'), [
                'nom' => 'Sous-dossier structure interdit',
                'parent_id' => $racineOrg->id,
            ])
            ->assertForbidden();
    }

    private function utilisateurParticulierDg(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('particulier_dg', 'web'));

        return $user->fresh();
    }
}
