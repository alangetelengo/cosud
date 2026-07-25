<?php

namespace Tests\Feature;

use App\Models\Courrier;
use App\Models\PrioriteCourrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\Structure;
use App\Models\TypeCourrier;
use App\Models\User;
use App\Services\CourrierDoublonService;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourrierDoublonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleAndPermissionSeeder::class,
            StructureSeeder::class,
            CourrierReferentielSeeder::class,
        ]);
    }

    public function test_refuse_doublon_par_numero_fulgurant(): void
    {
        Storage::fake('public');
        $secretaire = $this->creerSecretaire();
        $existant = $this->creerArrivee($secretaire, [
            'numero_fulgurant' => '024/2026',
            'objet' => 'Facture A',
        ]);

        $this->actingAs($secretaire)
            ->post(route('courriers.store', absolute: false), [
                'sens' => 'arrivee',
                'objet' => 'Autre facture',
                'expediteur_libelle' => 'Autre',
                'numero_fulgurant' => '024/2026',
                'fichier' => UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('numero_fulgurant');

        $this->assertSame(1, Courrier::where('numero_fulgurant', '024/2026')->count());
        $this->assertNotNull($existant->fresh());
    }

    public function test_refuse_doublon_fulgurant_insensible_casse(): void
    {
        Storage::fake('public');
        $secretaire = $this->creerSecretaire();
        $this->creerArrivee($secretaire, ['numero_fulgurant' => 'ABC-1']);

        $this->actingAs($secretaire)
            ->post(route('courriers.store', absolute: false), [
                'sens' => 'arrivee',
                'objet' => 'Nouveau',
                'numero_fulgurant' => ' abc-1 ',
                'fichier' => UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('numero_fulgurant');
    }

    public function test_refuse_doublon_par_empreinte_expediteur_date_objet(): void
    {
        Storage::fake('public');
        $secretaire = $this->creerSecretaire();
        $this->creerArrivee($secretaire, [
            'expediteur_libelle' => 'EEC',
            'date_courrier' => '2026-07-20',
            'objet' => 'Facture électricité',
            'numero_fulgurant' => null,
        ]);

        $this->actingAs($secretaire)
            ->post(route('courriers.store', absolute: false), [
                'sens' => 'arrivee',
                'objet' => 'Facture  électricité',
                'expediteur_libelle' => 'eec',
                'date_courrier' => '2026-07-20',
                'fichier' => UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('objet');
    }

    public function test_correction_autorise_garder_son_propre_fulgurant(): void
    {
        $particuliere = User::factory()->create(['structure_id' => Structure::where('code', 'SEC-DIR')->value('id')]);
        $particuliere->assignRole('particulier_dg');

        $courrier = $this->creerArrivee($particuliere, [
            'numero_fulgurant' => '111/2026',
            'objet' => 'À corriger',
        ]);

        $this->actingAs($particuliere)
            ->put(route('courriers.update', $courrier, absolute: false), [
                'objet' => 'Objet corrigé',
                'numero_fulgurant' => '111/2026',
                'expediteur_libelle' => 'EEC',
            ])
            ->assertRedirect(route('courriers.show', $courrier, absolute: false));

        $this->assertSame('Objet corrigé', $courrier->fresh()->objet);
    }

    public function test_service_detecte_reference(): void
    {
        $user = $this->creerSecretaire();
        $this->creerArrivee($user, [
            'reference' => 'REF-XYZ',
            'numero_fulgurant' => null,
        ]);

        $doublon = app(CourrierDoublonService::class)->trouverDoublonArrivee([
            'reference' => 'ref-xyz',
            'objet' => 'Autre',
        ]);

        $this->assertNotNull($doublon);
        $this->assertSame('reference', $doublon['critere']);
    }

    private function creerSecretaire(): User
    {
        $user = User::factory()->create([
            'structure_id' => Structure::where('code', 'SEC-DIR')->value('id'),
        ]);
        $user->assignRole('secretaire_direction');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function creerArrivee(User $user, array $attrs = []): Courrier
    {
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'recu')->firstOrFail();

        return Courrier::create(array_merge([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => TypeCourrier::where('code', 'demande')->value('id'),
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => PrioriteCourrier::where('code', 'normale')->value('id'),
            'numero_registre' => random_int(100, 900),
            'numero_registre_annee' => (int) now()->year,
            'origine' => Courrier::ORIGINE_EXTERNE,
            'date_reception' => now()->toDateString(),
            'objet' => 'Courrier test',
            'createur_id' => $user->id,
            'structure_id' => $user->structure_id,
        ], $attrs));
    }
}
