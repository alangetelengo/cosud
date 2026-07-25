<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Dossier;
use App\Models\GedSetting;
use App\Models\StatutDocument;
use App\Models\TypeDocument;
use App\Models\User;
use App\Services\ValidationDossierLecturePartageService;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StatutDocumentSeeder;
use Database\Seeders\TypeDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ValidationDossierLecturePartageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleAndPermissionSeeder::class,
            StatutDocumentSeeder::class,
            TypeDocumentSeeder::class,
        ]);
        Cache::forget('ged_setting_bool:'.GedSetting::LECTURE_DOSSIER_LORS_PARTAGE_DOCUMENT);
    }

    /**
     * @return array{0: Dossier, 1: Document}
     */
    private function creerDossierEtDocument(User $owner): array
    {
        $dossier = Dossier::create([
            'nom' => 'Dossier test',
            'code' => 'TST-'.uniqid(),
            'parent_id' => null,
            'createur_id' => $owner->id,
            'proprietaire_id' => $owner->id,
            'actif' => true,
            'ordre' => 0,
        ]);
        $type = TypeDocument::query()->firstOrFail();
        $statut = StatutDocument::query()->where('code', 'brouillon')->firstOrFail();
        $doc = Document::create([
            'type_document_id' => $type->id,
            'user_id' => $owner->id,
            'createur_id' => $owner->id,
            'proprietaire_id' => $owner->id,
            'dossier_id' => $dossier->id,
            'nom_original' => 'x.pdf',
            'chemin' => 'docs/x.pdf',
            'extension' => 'pdf',
            'taille_octets' => 1,
            'statut' => 'brouillon',
            'statut_document_id' => $statut->id,
            'en_corbeille' => false,
        ]);

        return [$dossier, $doc];
    }

    public function test_ne_cree_pas_de_partage_si_parametre_desactive(): void
    {
        GedSetting::setBool(GedSetting::LECTURE_DOSSIER_LORS_PARTAGE_DOCUMENT, false);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [, $doc] = $this->creerDossierEtDocument($owner);

        app(ValidationDossierLecturePartageService::class)->syncPourUtilisateurs($doc->fresh(), [$other->id], $owner->id);

        $this->assertDatabaseMissing('dossier_partages', [
            'dossier_id' => $doc->dossier_id,
            'user_id' => $other->id,
        ]);
    }

    public function test_cree_partage_lecture_si_parametre_active(): void
    {
        GedSetting::setBool(GedSetting::LECTURE_DOSSIER_LORS_PARTAGE_DOCUMENT, true);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [, $doc] = $this->creerDossierEtDocument($owner);

        app(ValidationDossierLecturePartageService::class)->syncPourUtilisateurs($doc->fresh(), [$other->id], $owner->id);

        $this->assertDatabaseHas('dossier_partages', [
            'dossier_id' => $doc->dossier_id,
            'user_id' => $other->id,
            'droits_lecture' => true,
            'droits_ecriture' => false,
        ]);
    }

    public function test_revoquer_supprime_le_partage_auto(): void
    {
        GedSetting::setBool(GedSetting::LECTURE_DOSSIER_LORS_PARTAGE_DOCUMENT, true);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [, $doc] = $this->creerDossierEtDocument($owner);
        $service = app(ValidationDossierLecturePartageService::class);
        $service->syncPourUtilisateurs($doc->fresh(), [$other->id], $owner->id);

        $service->revoquerPourDocument($doc->fresh());

        $this->assertDatabaseMissing('dossier_partages', [
            'dossier_id' => $doc->dossier_id,
            'user_id' => $other->id,
        ]);
    }

    public function test_sync_vers_nouvel_utilisateur_revoque_le_precedent(): void
    {
        GedSetting::setBool(GedSetting::LECTURE_DOSSIER_LORS_PARTAGE_DOCUMENT, true);
        $owner = User::factory()->create();
        $premier = User::factory()->create();
        $suivant = User::factory()->create();
        [, $doc] = $this->creerDossierEtDocument($owner);
        $service = app(ValidationDossierLecturePartageService::class);

        $service->syncPourUtilisateurs($doc->fresh(), [$premier->id], $owner->id);
        $this->assertDatabaseHas('dossier_partages', [
            'dossier_id' => $doc->dossier_id,
            'user_id' => $premier->id,
            'droits_lecture' => true,
        ]);

        $service->syncPourUtilisateurs($doc->fresh(), [$suivant->id], $owner->id);

        $this->assertDatabaseMissing('dossier_partages', [
            'dossier_id' => $doc->dossier_id,
            'user_id' => $premier->id,
        ]);
        $this->assertDatabaseHas('dossier_partages', [
            'dossier_id' => $doc->dossier_id,
            'user_id' => $suivant->id,
            'droits_lecture' => true,
            'droits_ecriture' => false,
        ]);
    }
}
