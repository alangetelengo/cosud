<?php

namespace Tests\Feature;

use App\Models\CosudSetting;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ParametresCosudNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Cache::forget('cosud_setting_bool:'.CosudSetting::NOTIF_FACTURE_ENREGISTREE_DG);
    }

    public function test_notif_facture_enregistree_dg_est_activee_par_defaut(): void
    {
        $this->assertTrue(CosudSetting::notifFactureEnregistreeDg());
    }

    public function test_admin_peut_consulter_et_modifier_les_notifications(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('parametres.notifications', absolute: false))
            ->assertOk()
            ->assertSee('Notifications courriers', false)
            ->assertSee('Notifier le DG à l’enregistrement', false);

        $this->actingAs($admin)
            ->put(route('parametres.notifications.update', absolute: false), [
                'notif_facture_enregistree_dg' => '0',
            ])
            ->assertRedirect(route('parametres.notifications', absolute: false));

        $this->assertFalse(CosudSetting::notifFactureEnregistreeDg());

        $this->actingAs($admin)
            ->put(route('parametres.notifications.update', absolute: false), [
                'notif_facture_enregistree_dg' => '1',
            ])
            ->assertRedirect(route('parametres.notifications', absolute: false));

        $this->assertTrue(CosudSetting::notifFactureEnregistreeDg());
    }

    public function test_utilisateur_sans_role_admin_recoit_403(): void
    {
        $user = User::factory()->create();
        $user->assignRole('utilisateur');

        $this->actingAs($user)
            ->get(route('parametres.notifications', absolute: false))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('parametres.notifications.update', absolute: false), [
                'notif_facture_enregistree_dg' => '0',
            ])
            ->assertForbidden();
    }
}
