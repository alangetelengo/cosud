<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulesRetourUrlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleAndPermissionSeeder::class]);
    }

    public function test_fiche_utilisateur_retourne_vers_l_url_return(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $cible = User::factory()->create();
        $cible->assignRole('utilisateur');

        $returnUrl = route('utilisateurs.index', ['q' => 'filtre-test'], absolute: false);

        $this->actingAs($admin)
            ->get(route('utilisateurs.show', ['user' => $cible, 'return' => url($returnUrl)], absolute: false))
            ->assertOk()
            ->assertSee('Retour à la liste', false)
            ->assertSee($returnUrl, false);
    }

    public function test_edition_utilisateur_rejette_une_url_return_externe(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $cible = User::factory()->create();
        $cible->assignRole('utilisateur');

        $fallback = route('utilisateurs.index', absolute: false);

        $this->actingAs($admin)
            ->get(route('utilisateurs.edit', ['user' => $cible, 'return' => 'https://evil.example/x'], absolute: false))
            ->assertOk()
            ->assertSee($fallback, false)
            ->assertDontSee('evil.example', false);
    }

    public function test_liste_utilisateurs_ajoute_return_aux_liens_fiche(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $cible = User::factory()->create(['name' => 'Utilisateur Retour Url']);
        $cible->assignRole('utilisateur');

        $this->actingAs($admin)
            ->get(route('utilisateurs.index', absolute: false))
            ->assertOk()
            ->assertSee('return=', false);
    }
}
