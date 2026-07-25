<?php

namespace Tests\Feature;

use App\Models\Structure;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAffectationRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_index_affectations_redirige_vers_edition_avec_fragment_structures(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('utilisateurs.affectations.index', $user, absolute: false))
            ->assertRedirect(route('utilisateurs.edit', $user, absolute: false).'#structures');
    }

    public function test_store_affectation_redirige_avec_fragment_structures(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create();
        $structure = Structure::create([
            'code' => 'DG',
            'nom' => 'DIRECTION GENERALE',
            'type' => 'direction',
            'parent_id' => null,
            'actif' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('utilisateurs.affectations.store', $user, absolute: false), [
                'structure_id' => $structure->id,
            ])
            ->assertRedirect(route('utilisateurs.edit', $user, absolute: false).'#structures');

        $this->assertTrue($user->fresh()->structures->contains('id', $structure->id));
    }
}
