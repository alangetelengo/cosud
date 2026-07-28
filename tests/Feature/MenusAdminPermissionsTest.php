<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MenusAdminPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_seul_admin_a_les_permissions_menus_par_defaut(): void
    {
        $admin = Role::findByName('admin', 'web');
        $utilisateur = Role::findByName('utilisateur', 'web');
        $dg = Role::findByName('dg', 'web');

        foreach (['types-documents.view', 'recherche.view', 'corbeille.view'] as $permission) {
            $this->assertTrue($admin->hasPermissionTo($permission));
            $this->assertFalse($utilisateur->hasPermissionTo($permission));
            $this->assertFalse($dg->hasPermissionTo($permission));
        }
    }

    public function test_admin_voit_les_menus_et_accede_aux_pages(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('home', absolute: false))
            ->assertOk()
            ->assertSee('Types de documents', false)
            ->assertSee('Recherche', false)
            ->assertSee('Corbeille', false);

        $this->actingAs($admin)->get(route('recherche.index', absolute: false))->assertOk();
        $this->actingAs($admin)->get(route('corbeille.index', absolute: false))->assertOk();
        $this->actingAs($admin)->get(route('types-documents.index', absolute: false))->assertOk();
    }

    public function test_utilisateur_ne_voit_pas_les_menus_et_recoit_403(): void
    {
        $user = User::factory()->create();
        $user->assignRole('utilisateur');

        $this->actingAs($user)
            ->get(route('home', absolute: false))
            ->assertOk()
            ->assertDontSee('Types de documents', false)
            ->assertDontSee('>Recherche<', false)
            ->assertDontSee('>Corbeille<', false);

        $this->actingAs($user)->get(route('recherche.index', absolute: false))->assertForbidden();
        $this->actingAs($user)->get(route('corbeille.index', absolute: false))->assertForbidden();
        $this->actingAs($user)->get(route('types-documents.index', absolute: false))->assertForbidden();
    }

    public function test_admin_peut_reattribuer_recherche_a_un_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $role = Role::findByName('utilisateur', 'web');

        $this->actingAs($admin)
            ->put(route('parametres.roles.update', $role, absolute: false), [
                'name' => 'utilisateur',
                'permissions' => [
                    'documents.view',
                    'dossiers.view',
                    'recherche.view',
                ],
            ])
            ->assertRedirect(route('parametres.roles.index', absolute: false));

        $this->assertTrue($role->fresh()->hasPermissionTo('recherche.view'));
    }
}
