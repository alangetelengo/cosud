<?php

namespace Tests\Feature;

use App\Models\CosudSetting;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ParametresCosudAccesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Cache::forget('cosud_setting_bool:'.CosudSetting::LECTURE_DOSSIER_LORS_PARTAGE_DOCUMENT);
    }

    public function test_lecture_dossier_lors_partage_document_est_desactive_par_defaut_apres_migration(): void
    {
        $this->assertFalse(CosudSetting::lectureDossierLorsPartageDocument());
    }

    public function test_migration_create_cosud_ne_ecrase_pas_ged_settings_existantes(): void
    {
        Schema::dropIfExists('cosud_settings');

        Schema::create('ged_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('cle')->unique();
            $table->text('valeur');
            $table->timestamps();
        });

        DB::table('ged_settings')->insert([
            'cle' => CosudSetting::LECTURE_DOSSIER_LORS_PARTAGE_DOCUMENT,
            'valeur' => json_encode(true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $create = require database_path('migrations/2026_04_10_120000_create_cosud_settings_table.php');
        $create->up();

        $this->assertTrue(Schema::hasTable('ged_settings'));
        $this->assertFalse(Schema::hasTable('cosud_settings'));

        $rename = require database_path('migrations/2026_08_25_002700_rename_ged_settings_to_cosud_settings.php');
        $rename->up();

        $this->assertFalse(Schema::hasTable('ged_settings'));
        $this->assertTrue(Schema::hasTable('cosud_settings'));
        $this->assertTrue(CosudSetting::lectureDossierLorsPartageDocument());
    }

    public function test_admin_peut_consulter_et_modifier_la_page_cosud_acces(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('parametres.cosud-acces', absolute: false))
            ->assertOk()
            ->assertSee('Accès documents / dossiers', false)
            ->assertSee('Accorder la lecture du dossier parent', false)
            ->assertSee('Par défaut, l’option est', false)
            ->assertSee('désactivée', false);

        $this->actingAs($admin)
            ->put(route('parametres.cosud-acces.update', absolute: false), [
                'lecture_dossier_lors_partage_document' => '1',
            ])
            ->assertRedirect(route('parametres.cosud-acces', absolute: false));

        $this->assertTrue(CosudSetting::lectureDossierLorsPartageDocument());

        $this->actingAs($admin)
            ->put(route('parametres.cosud-acces.update', absolute: false), [
                'lecture_dossier_lors_partage_document' => '0',
            ])
            ->assertRedirect(route('parametres.cosud-acces', absolute: false));

        $this->assertFalse(CosudSetting::lectureDossierLorsPartageDocument());
    }

    public function test_utilisateur_sans_role_admin_recoit_403(): void
    {
        $user = User::factory()->create();
        $user->assignRole('utilisateur');

        $this->actingAs($user)
            ->get(route('parametres.cosud-acces', absolute: false))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('parametres.cosud-acces.update', absolute: false), [
                'lecture_dossier_lors_partage_document' => '1',
            ])
            ->assertForbidden();
    }
}
