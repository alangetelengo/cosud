<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UtilisateurTelephoneSmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_admin_peut_creer_un_utilisateur_avec_telephone_sms_normalise(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('utilisateurs.store', absolute: false), [
                'name' => 'Agent SMS',
                'email' => 'agent.sms@example.com',
                'telephone' => '+242 06 68 35 332',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
                'role' => 'utilisateur',
                'actif' => '1',
            ])
            ->assertRedirect(route('utilisateurs.index', absolute: false));

        $user = User::query()->where('email', 'agent.sms@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('242066835332', $user->telephone);
        $this->assertSame('242066835332', $user->routeNotificationForGedSms());
    }

    public function test_admin_peut_mettre_a_jour_et_effacer_le_telephone_sms(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create([
            'telephone' => '242066835332',
        ]);
        $user->assignRole('utilisateur');

        $this->actingAs($admin)
            ->put(route('utilisateurs.update', $user, absolute: false), [
                'name' => $user->name,
                'email' => $user->email,
                'telephone' => '06 12 34 56 7',
                'role' => 'utilisateur',
                'actif' => '1',
            ])
            ->assertRedirect();

        $this->assertSame('242061234567', $user->fresh()->telephone);

        $this->actingAs($admin)
            ->put(route('utilisateurs.update', $user, absolute: false), [
                'name' => $user->name,
                'email' => $user->email,
                'telephone' => '',
                'role' => 'utilisateur',
                'actif' => '1',
            ])
            ->assertRedirect();

        $this->assertNull($user->fresh()->telephone);
        $this->assertNull($user->fresh()->routeNotificationForGedSms());
    }

    public function test_telephone_sms_invalide_est_refuse(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->from(route('utilisateurs.create', absolute: false))
            ->post(route('utilisateurs.store', absolute: false), [
                'name' => 'Invalide',
                'email' => 'invalide.sms@example.com',
                'telephone' => '12',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
                'role' => 'utilisateur',
                'actif' => '1',
            ])
            ->assertRedirect(route('utilisateurs.create', absolute: false))
            ->assertSessionHasErrors('telephone');

        $this->assertDatabaseMissing('users', ['email' => 'invalide.sms@example.com']);
    }

    public function test_recherche_utilisateurs_par_telephone_sms(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        User::factory()->create([
            'name' => 'UtilisateurCibleTelSmsUnique',
            'telephone' => '242066811122',
        ]);
        User::factory()->create([
            'name' => 'UtilisateurHorsRechercheTelSms',
            'telephone' => '242066899999',
        ]);

        $this->actingAs($admin)
            ->get(route('utilisateurs.index', ['q' => '066811122'], absolute: false))
            ->assertOk()
            ->assertSee('UtilisateurCibleTelSmsUnique')
            ->assertDontSee('UtilisateurHorsRechercheTelSms');
    }
}
