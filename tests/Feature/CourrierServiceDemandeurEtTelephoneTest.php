<?php

namespace Tests\Feature;

use App\Models\FournisseurPrestataire;
use App\Models\Structure;
use App\Models\TypeCourrier;
use App\Models\User;
use Database\Seeders\ACSIFonctionsSeeder;
use Database\Seeders\CircuitCourrierSeeder;
use Database\Seeders\CourrierActeursDgSeeder;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Database\Seeders\TypeDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourrierServiceDemandeurEtTelephoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleAndPermissionSeeder::class,
            StructureSeeder::class,
            ACSIFonctionsSeeder::class,
            CourrierReferentielSeeder::class,
            CircuitCourrierSeeder::class,
            TypeDocumentSeeder::class,
            CourrierActeursDgSeeder::class,
        ]);
        Storage::fake('public');
    }

    public function test_pointe_noire_apparait_comme_service_demandeur_et_directeur_existe(): void
    {
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $user->assignRole('secretaire_direction');

        $ant = Structure::where('code', 'ANT')->firstOrFail();
        $this->assertSame('antenne', $ant->type);

        $this->actingAs($user)
            ->get(route('courriers.create', ['sens' => 'arrivee'], absolute: false))
            ->assertOk()
            ->assertSee('DIRECTION DEPARTEMENTALE DE POINTE-NOIRE', false)
            ->assertSee((string) $ant->id, false);

        $directeur = User::where('email', '003020h@acsi.cg')->firstOrFail();
        $this->assertSame('MARTISC MONDZILA', $directeur->name);
        $this->assertTrue($directeur->hasRole('directeur'));
        $this->assertSame((int) $ant->id, (int) $directeur->structure_id);
    }

    public function test_telephone_obligatoire_pour_facture_et_demande(): void
    {
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $user->assignRole('secretaire_direction');

        $factureId = TypeCourrier::where('code', 'facture')->value('id');
        $demandeId = TypeCourrier::where('code', 'demande')->value('id');
        $madId = TypeCourrier::where('code', 'mad')->value('id');
        $daf = Structure::where('code', 'DAF')->value('id');
        $ant = Structure::where('code', 'ANT')->value('id');
        $fiche = FournisseurPrestataire::factory()->create([
            'nom' => 'AF.COM',
            'telephone' => null,
            'email' => null,
        ]);

        $this->actingAs($user)
            ->post(route('courriers.store', absolute: false), [
                'sens' => 'arrivee',
                'numero_fulgurant' => 'REG-f572c02f/2026',
                'type_courrier_id' => $factureId,
                'objet' => 'Facture sans téléphone',
                'fournisseur_prestataire_id' => $fiche->id,
                'montant_facture' => '100000',
                'service_demandeur_structure_id' => $daf,
                'date_reception' => now()->toDateString(),
                'fichier' => UploadedFile::fake()->create('f.pdf', 20, 'application/pdf'),
            ])
            ->assertSessionHasErrors('expediteur_telephone');

        $this->actingAs($user)
            ->post(route('courriers.store', absolute: false), [
                'sens' => 'arrivee',
                'numero_fulgurant' => 'REG-7ae71f66/2026',
                'type_courrier_id' => $demandeId,
                'objet' => 'Demande de stage sans téléphone',
                'expediteur_libelle' => 'Étudiant X',
                'date_reception' => now()->toDateString(),
                'fichier' => UploadedFile::fake()->create('f.pdf', 20, 'application/pdf'),
            ])
            ->assertSessionHasErrors('expediteur_telephone');

        $this->actingAs($user)
            ->post(route('courriers.store', absolute: false), [
                'sens' => 'arrivee',
                'numero_fulgurant' => 'REG-1fa52f19/2026',
                'type_courrier_id' => $madId,
                'objet' => 'MAD PNR sans téléphone OK',
                'expediteur_libelle' => 'DAF / SAGP',
                'service_demandeur_structure_id' => $ant,
                'date_reception' => now()->toDateString(),
                'fichier' => UploadedFile::fake()->create('f.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors('expediteur_telephone');
    }

    public function test_formulaire_marque_telephone_requis_pour_facture_et_demande(): void
    {
        $user = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $user->assignRole('secretaire_direction');

        $factureId = TypeCourrier::where('code', 'facture')->value('id');
        $demandeId = TypeCourrier::where('code', 'demande')->value('id');
        $madId = TypeCourrier::where('code', 'mad')->value('id');

        $html = $this->actingAs($user)
            ->get(route('courriers.create', ['sens' => 'arrivee'], absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/value="'.$factureId.'"[^>]*data-telephone-requis="1"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/value="'.$demandeId.'"[^>]*data-telephone-requis="1"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/value="'.$madId.'"[^>]*data-telephone-requis="0"/',
            $html
        );
    }
}
