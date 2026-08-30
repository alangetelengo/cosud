<?php

namespace Tests\Feature;

use App\Models\Structure;
use App\Models\TypeCourrier;
use App\Models\User;
use Database\Seeders\CircuitCourrierSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourrierFormulaireSelonTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleAndPermissionSeeder::class,
            StructureSeeder::class,
            CourrierReferentielSeeder::class,
            CircuitCourrierSeeder::class,
        ]);
    }

    public function test_formulaire_arrivee_expose_profils_par_type(): void
    {
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $user->assignRole('secretaire_direction');

        $factureId = TypeCourrier::where('code', 'facture')->value('id');
        $madId = TypeCourrier::where('code', 'mad')->value('id');
        $adminId = TypeCourrier::where('code', 'administratif')->value('id');

        $html = $this->actingAs($user)
            ->get(route('courriers.create', ['sens' => 'arrivee'], absolute: false))
            ->assertOk()
            ->assertDontSee('N° fulgurant', false)
            ->getContent();

        $this->assertStringContainsString('id="bloc-contacts-expediteur"', $html);
        $this->assertStringContainsString('id="bloc-fournisseur-prestataire"', $html);
        $this->assertStringContainsString('id="aide-arrivee-mad"', $html);
        $this->assertStringContainsString('id="aide-arrivee-facture"', $html);

        $this->assertMatchesRegularExpression(
            '/value="'.$factureId.'"[^>]*data-contacts="1"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/value="'.$madId.'"[^>]*data-contacts="0"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/value="'.$adminId.'"[^>]*data-contacts="1"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/value="'.$factureId.'"[^>]*data-telephone-requis="1"/',
            $html
        );
    }
}
