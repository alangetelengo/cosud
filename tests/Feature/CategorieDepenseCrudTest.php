<?php

namespace Tests\Feature;

use App\Models\CategorieDepense;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorieDepenseCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_admin_peut_creer_une_categorie(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('parametres.categories-depense.store', absolute: false), [
                'code' => 'frais_mission',
                'libelle' => 'Frais de mission',
                'ordre' => 80,
                'actif' => '1',
            ])
            ->assertRedirect(route('parametres.categories-depense.index', absolute: false));

        $this->assertDatabaseHas('categorie_depenses', [
            'code' => 'frais_mission',
            'libelle' => 'Frais de mission',
            'est_systeme' => false,
        ]);
    }

    public function test_admin_ne_peut_pas_supprimer_categorie_systeme(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $facture = CategorieDepense::query()->where('code', CategorieDepense::CODE_FACTURE)->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('parametres.categories-depense.destroy', $facture, absolute: false))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('categorie_depenses', ['id' => $facture->id]);
    }

    public function test_utilisateur_sans_droit_ne_voit_pas_le_crud(): void
    {
        $user = User::factory()->create();
        $user->assignRole('utilisateur');

        $this->actingAs($user)
            ->get(route('parametres.categories-depense.index', absolute: false))
            ->assertForbidden();
    }
}
