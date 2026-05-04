<?php

namespace Tests\Feature;

use App\Models\GedSetting;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ParametresGedAccesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Cache::forget('ged_setting_bool:'.GedSetting::LECTURE_DOSSIER_LORS_PARTAGE_DOCUMENT);
    }

    public function test_lecture_dossier_lors_partage_document_est_active_par_defaut_apres_migration(): void
    {
        $this->assertTrue(GedSetting::lectureDossierLorsPartageDocument());
    }

    public function test_admin_peut_consulter_et_modifier_la_page_ged_acces(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('parametres.ged-acces', absolute: false))
            ->assertOk()
            ->assertSee('Accès documents / dossiers', false)
            ->assertSee('Accorder la lecture du dossier parent', false);

        $this->actingAs($admin)
            ->put(route('parametres.ged-acces.update', absolute: false), [
                'lecture_dossier_lors_partage_document' => '1',
            ])
            ->assertRedirect(route('parametres.ged-acces', absolute: false));

        $this->assertTrue(GedSetting::lectureDossierLorsPartageDocument());

        $this->actingAs($admin)
            ->put(route('parametres.ged-acces.update', absolute: false), [
                'lecture_dossier_lors_partage_document' => '0',
            ])
            ->assertRedirect(route('parametres.ged-acces', absolute: false));

        $this->assertFalse(GedSetting::lectureDossierLorsPartageDocument());
    }

    public function test_utilisateur_sans_role_admin_recoit_403(): void
    {
        $user = User::factory()->create();
        $user->assignRole('utilisateur');

        $this->actingAs($user)
            ->get(route('parametres.ged-acces', absolute: false))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('parametres.ged-acces.update', absolute: false), [
                'lecture_dossier_lors_partage_document' => '1',
            ])
            ->assertForbidden();
    }
}
