<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_utilisateur_doit_changer_le_mot_de_passe_avant_dacceder_a_lapp(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)
            ->get(route('home', absolute: false))
            ->assertRedirect(route('password.force-change', absolute: false));

        $this->actingAs($user)
            ->get(route('password.force-change', absolute: false))
            ->assertOk()
            ->assertSee('Changer votre mot de passe', false);
    }

    public function test_premiere_connexion_enregistre_le_nouveau_mot_de_passe(): void
    {
        $user = User::factory()->mustChangePassword()->create([
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user)
            ->put(route('password.force-change.update', absolute: false), [
                'password' => 'NouveauMotDePasse1!',
                'password_confirmation' => 'NouveauMotDePasse1!',
            ])
            ->assertRedirect(route('home', absolute: false));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('NouveauMotDePasse1!', $user->password));

        $this->actingAs($user)
            ->get(route('home', absolute: false))
            ->assertOk();
    }

    public function test_changement_depuis_profil_leve_le_flag(): void
    {
        $user = User::factory()->mustChangePassword()->create([
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'AutreMotDePasse1!',
                'password_confirmation' => 'AutreMotDePasse1!',
            ])
            ->assertRedirect('/profile');

        $this->assertFalse($user->refresh()->must_change_password);
    }
}
