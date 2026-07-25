<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Dossier;
use App\Models\GedSetting;
use App\Models\StatutDocument;
use App\Models\TypeDocument;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StatutDocumentSeeder;
use Database\Seeders\TypeDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ValidationPieceSeuleAccesTest extends TestCase
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

    public function test_validateur_voit_seulement_le_document_en_attente_sans_ouvrir_le_dossier(): void
    {
        GedSetting::setBool(GedSetting::LECTURE_DOSSIER_LORS_PARTAGE_DOCUMENT, false);

        $owner = User::factory()->create();
        $owner->assignRole('utilisateur');

        $validateur = User::factory()->create();
        $validateur->assignRole('utilisateur');

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
        $statutEnAttente = StatutDocument::query()->where('code', 'en_attente')->firstOrFail();
        $statutBrouillon = StatutDocument::query()->where('code', 'brouillon')->firstOrFail();

        $docAValider = Document::create([
            'type_document_id' => $type->id,
            'user_id' => $owner->id,
            'createur_id' => $owner->id,
            'proprietaire_id' => $owner->id,
            'dossier_id' => $dossier->id,
            'nom_original' => 'a.pdf',
            'chemin' => 'docs/a.pdf',
            'extension' => 'pdf',
            'taille_octets' => 1,
            'statut' => 'en_attente',
            'statut_document_id' => $statutEnAttente->id,
            'workflow_validateur_id' => $validateur->id,
            'en_corbeille' => false,
        ]);

        $autreDocDossier = Document::create([
            'type_document_id' => $type->id,
            'user_id' => $owner->id,
            'createur_id' => $owner->id,
            'proprietaire_id' => $owner->id,
            'dossier_id' => $dossier->id,
            'nom_original' => 'b.pdf',
            'chemin' => 'docs/b.pdf',
            'extension' => 'pdf',
            'taille_octets' => 1,
            'statut' => 'brouillon',
            'statut_document_id' => $statutBrouillon->id,
            'en_corbeille' => false,
        ]);

        $this->actingAs($validateur)
            ->get(route('documents.index', absolute: false))
            ->assertOk()
            ->assertSee($docAValider->nom_original, false)
            ->assertDontSee($autreDocDossier->nom_original, false);

        $this->actingAs($validateur)
            ->get(route('documents.fiche', $docAValider, absolute: false))
            ->assertOk()
            ->assertSee($docAValider->nom_original, false);

        $this->actingAs($validateur)
            ->get(route('documents.fiche', $autreDocDossier, absolute: false))
            ->assertForbidden();
    }

    public function test_destinataire_enregistre_peut_voir_le_document_meme_si_workflow_validateur_different(): void
    {
        GedSetting::setBool(GedSetting::LECTURE_DOSSIER_LORS_PARTAGE_DOCUMENT, false);

        $owner = User::factory()->create();
        $owner->assignRole('utilisateur');

        $destinataire = User::factory()->create();
        $destinataire->assignRole('utilisateur');

        $validateur = User::factory()->create();
        $validateur->assignRole('utilisateur');

        $dossier = Dossier::create([
            'nom' => 'Dossier test 2',
            'code' => 'TST-'.uniqid(),
            'parent_id' => null,
            'createur_id' => $owner->id,
            'proprietaire_id' => $owner->id,
            'actif' => true,
            'ordre' => 0,
        ]);

        $type = TypeDocument::query()->firstOrFail();
        $statutEnAttente = StatutDocument::query()->where('code', 'en_attente')->firstOrFail();

        $doc = Document::create([
            'type_document_id' => $type->id,
            'user_id' => $owner->id,
            'createur_id' => $owner->id,
            'proprietaire_id' => $owner->id,
            'dossier_id' => $dossier->id,
            'nom_original' => 'c.pdf',
            'chemin' => 'docs/c.pdf',
            'extension' => 'pdf',
            'taille_octets' => 1,
            'statut' => 'en_attente',
            'statut_document_id' => $statutEnAttente->id,
            'workflow_validateur_id' => $validateur->id,
            'workflow_destinataire_id' => $destinataire->id,
            'en_corbeille' => false,
        ]);

        $this->actingAs($destinataire)
            ->get(route('documents.fiche', $doc, absolute: false))
            ->assertOk()
            ->assertSee($doc->nom_original, false);
    }

    public function test_un_utilisateur_dans_la_liste_des_destinataires_workflow_peut_voir_le_document(): void
    {
        GedSetting::setBool(GedSetting::LECTURE_DOSSIER_LORS_PARTAGE_DOCUMENT, false);

        $owner = User::factory()->create();
        $owner->assignRole('utilisateur');

        $dest1 = User::factory()->create();
        $dest1->assignRole('utilisateur');

        $dest2 = User::factory()->create();
        $dest2->assignRole('utilisateur');

        $dossier = Dossier::create([
            'nom' => 'Dossier test 3',
            'code' => 'TST-'.uniqid(),
            'parent_id' => null,
            'createur_id' => $owner->id,
            'proprietaire_id' => $owner->id,
            'actif' => true,
            'ordre' => 0,
        ]);

        $type = TypeDocument::query()->firstOrFail();
        $statutEnAttente = StatutDocument::query()->where('code', 'en_attente')->firstOrFail();

        $doc = Document::create([
            'type_document_id' => $type->id,
            'user_id' => $owner->id,
            'createur_id' => $owner->id,
            'proprietaire_id' => $owner->id,
            'dossier_id' => $dossier->id,
            'nom_original' => 'd.pdf',
            'chemin' => 'docs/d.pdf',
            'extension' => 'pdf',
            'taille_octets' => 1,
            'statut' => 'en_attente',
            'statut_document_id' => $statutEnAttente->id,
            'workflow_validateur_id' => null,
            'workflow_destinataire_id' => null,
            'en_corbeille' => false,
        ]);
        $doc->workflowDestinataires()->sync([(int) $dest1->id, (int) $dest2->id]);

        $this->actingAs($dest2)
            ->get(route('documents.fiche', $doc, absolute: false))
            ->assertOk()
            ->assertSee($doc->nom_original, false);
    }
}
