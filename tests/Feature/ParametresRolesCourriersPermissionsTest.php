<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ParametresRolesCourriersPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_page_edition_role_affiche_le_groupe_courriers(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $role = Role::where('name', 'directeur')->where('guard_name', 'web')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('parametres.roles.edit', $role, absolute: false))
            ->assertOk()
            ->assertSee('Courriers', false)
            ->assertSee('Consulter', false)
            ->assertSee('Orienter', false)
            ->assertSee('Ventiler', false)
            ->assertSee('Signer', false)
            ->assertSee('Recevoir', false)
            ->assertSee('Consulter les courriers arrivée et départ accessibles', false);
    }

    public function test_admin_peut_attribuer_les_permissions_courriers_a_un_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $role = Role::where('name', 'utilisateur')->where('guard_name', 'web')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('parametres.roles.update', $role, absolute: false), [
                'name' => 'utilisateur',
                'permissions' => [
                    'documents.view',
                    'courriers.view',
                    'courriers.orienter',
                ],
            ])
            ->assertRedirect(route('parametres.roles.index', absolute: false));

        $role->refresh();

        $this->assertTrue($role->hasPermissionTo('courriers.view'));
        $this->assertTrue($role->hasPermissionTo('courriers.orienter'));
        $this->assertFalse($role->hasPermissionTo('courriers.create'));
    }
}
