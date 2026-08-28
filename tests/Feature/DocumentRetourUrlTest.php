<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Dossier;
use App\Models\StatutDocument;
use App\Models\TypeDocument;
use App\Models\User;
use App\Support\ReturnUrl;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StatutDocumentSeeder;
use Database\Seeders\TypeDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentRetourUrlTest extends TestCase
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
    }

    public function test_fiche_affiche_le_bouton_retour_vers_l_url_return(): void
    {
        $user = User::factory()->create();
        $user->assignRole('utilisateur');

        $dossier = Dossier::create([
            'nom' => 'Dossier retour',
            'code' => 'RET-'.uniqid(),
            'parent_id' => null,
            'createur_id' => $user->id,
            'proprietaire_id' => $user->id,
            'actif' => true,
            'ordre' => 0,
        ]);

        $type = TypeDocument::query()->firstOrFail();
        $statut = StatutDocument::query()->where('code', 'brouillon')->firstOrFail();

        $document = Document::create([
            'type_document_id' => $type->id,
            'user_id' => $user->id,
            'createur_id' => $user->id,
            'proprietaire_id' => $user->id,
            'dossier_id' => $dossier->id,
            'nom_original' => 'facture.pdf',
            'chemin' => 'docs/facture.pdf',
            'extension' => 'pdf',
            'taille_octets' => 1,
            'statut' => 'brouillon',
            'statut_document_id' => $statut->id,
            'en_corbeille' => false,
        ]);

        $returnUrl = route('dossiers.show', $dossier, absolute: false);

        $this->actingAs($user)
            ->get(route('documents.fiche', ['document' => $document, 'return' => url($returnUrl)], absolute: false))
            ->assertOk()
            ->assertSee('Retour à la liste', false)
            ->assertSee($returnUrl, false);
    }

    public function test_fiche_rejette_une_url_return_externe(): void
    {
        $user = User::factory()->create();
        $user->assignRole('utilisateur');

        $type = TypeDocument::query()->firstOrFail();
        $statut = StatutDocument::query()->where('code', 'brouillon')->firstOrFail();

        $document = Document::create([
            'type_document_id' => $type->id,
            'user_id' => $user->id,
            'createur_id' => $user->id,
            'proprietaire_id' => $user->id,
            'dossier_id' => null,
            'nom_original' => 'facture.pdf',
            'chemin' => 'docs/facture.pdf',
            'extension' => 'pdf',
            'taille_octets' => 1,
            'statut' => 'brouillon',
            'statut_document_id' => $statut->id,
            'en_corbeille' => false,
        ]);

        $fallback = route('documents.index', absolute: false);

        $this->actingAs($user)
            ->get(route('documents.fiche', ['document' => $document, 'return' => 'https://evil.example/phishing'], absolute: false))
            ->assertOk()
            ->assertSee($fallback, false)
            ->assertDontSee('evil.example', false);
    }

    public function test_return_url_validated_rejette_une_url_externe(): void
    {
        $this->assertNull(ReturnUrl::validated('https://evil.example/phishing'));
        $this->assertSame(url('/dossiers'), ReturnUrl::validated(url('/dossiers')));
    }

    public function test_return_url_validated_rejette_les_attaques_par_prefixe_hote(): void
    {
        $base = rtrim(url('/'), '/');
        $parts = parse_url($base);
        $this->assertIsArray($parts);
        $this->assertArrayHasKey('scheme', $parts);
        $this->assertArrayHasKey('host', $parts);

        $this->assertNull(ReturnUrl::validated($base.'.evil.example/x'));
        $this->assertNull(ReturnUrl::validated($parts['scheme'].'://'.$parts['host'].'.evil.example/x'));
        $this->assertNull(ReturnUrl::validated($parts['scheme'].'://evil@'.$parts['host'].'/x'));
        $this->assertNull(ReturnUrl::validated('//evil.example/x'));
        $this->assertSame(url('/dossiers?q=1'), ReturnUrl::validated('/dossiers?q=1'));
    }
}
