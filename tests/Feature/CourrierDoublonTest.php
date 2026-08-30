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

    public function test_service_detecte_doublon_legacy_par_numero_fulgurant(): void
    {
        $secretaire = $this->creerSecretaire();
        $this->creerArrivee($secretaire, [
            'numero_fulgurant' => '024/2026',
            'objet' => 'Facture A',
        ]);

        $doublon = app(CourrierDoublonService::class)->trouverDoublonArrivee([
            'numero_fulgurant' => '024/2026',
            'objet' => 'Autre',
        ]);

        $this->assertNotNull($doublon);
        $this->assertSame('numero_fulgurant', $doublon['critere']);
    }

    public function test_service_detecte_fulgurant_insensible_casse(): void
    {
        $secretaire = $this->creerSecretaire();
        $this->creerArrivee($secretaire, ['numero_fulgurant' => 'ABC-1']);

        $doublon = app(CourrierDoublonService::class)->trouverDoublonArrivee([
            'numero_fulgurant' => ' abc-1 ',
            'objet' => 'Nouveau',
        ]);

        $this->assertNotNull($doublon);
        $this->assertSame('numero_fulgurant', $doublon['critere']);
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
                'numero_fulgurant' => 'REG-dba86221/2026',
                'objet' => 'Facture  électricité',
                'expediteur_libelle' => 'eec',
                'date_courrier' => '2026-07-20',
                'fichier' => UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('objet');
    }

    public function test_correction_conserve_numero_fulgurant_legacy(): void
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
                'expediteur_libelle' => 'EEC',
                'expediteur_telephone' => '+242060000011',
                'numero_fulgurant' => '111/2026',
            ])
            ->assertRedirect(route('courriers.show', $courrier, absolute: false));

        $fresh = $courrier->fresh();
        $this->assertSame('Objet corrigé', $fresh->objet);
        $this->assertSame('111/2026', $fresh->numero_fulgurant);
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
