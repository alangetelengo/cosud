<?php

namespace Tests\Feature;

use App\Models\Courrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourrierArchivageRegistreTest extends TestCase
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

    public function test_depart_expedie_n_autorise_plus_l_archivage_manuel(): void
    {
        $secretaire = $this->creerSecretaire();
        $secDaf = Structure::where('code', 'SEC-DAF')->firstOrFail();

        $depart = Courrier::create([
            'sens_courrier_id' => SensCourrier::where('code', 'depart')->value('id'),
            'statut_courrier_id' => StatutCourrier::where('code', 'expedie')->value('id'),
            'numero_registre' => 1,
            'numero_registre_annee' => (int) now()->format('Y'),
            'objet' => 'Lettre au Prefet',
            'structure_destinataire_id' => $secDaf->id,
            'destinataire_libelle' => $secDaf->nom,
            'numero_archives' => 'DG/DEP/2026/001',
            'observations' => 'Saisi à l’expédition',
            'createur_id' => $secretaire->id,
            'structure_id' => $secretaire->structure_id,
            'date_expedition' => now(),
        ]);

        $this->actingAs($secretaire)
            ->get(route('courriers.show', $depart, absolute: false))
            ->assertOk()
            ->assertDontSee('>Archiver<', false)
            ->assertSee('Courrier expédié — aucune action supplémentaire', false);

        $this->actingAs($secretaire)
            ->post(route('courriers.archiver', $depart, absolute: false), [
                'nombre_pieces' => 2,
                'numero_archives' => 'DG/DEP/2026/001',
                'observations' => 'Tentative archivage',
            ])
            ->assertForbidden();

        $depart->refresh();
        $this->assertSame('expedie', $depart->statutCourrier->code);

        // Les infos registre saisies à l’expédition restent visibles au registre départ.
        $this->actingAs($secretaire)
            ->get(route('courriers.registres.depart', ['annee' => $depart->numero_registre_annee], absolute: false))
            ->assertOk()
            ->assertSee('DG/DEP/2026/001', false)
            ->assertSee('Saisi à l’expédition', false);
    }

    public function test_infos_registre_saisies_a_lexpedition_restent_conservees(): void
    {
        $secretaire = $this->creerSecretaire();
        $secDaf = Structure::where('code', 'SEC-DAF')->firstOrFail();

        $depart = Courrier::create([
            'sens_courrier_id' => SensCourrier::where('code', 'depart')->value('id'),
            'statut_courrier_id' => StatutCourrier::where('code', 'expedie')->value('id'),
            'numero_registre' => 2,
            'numero_registre_annee' => (int) now()->format('Y'),
            'objet' => 'Note interne',
            'structure_destinataire_id' => $secDaf->id,
            'destinataire_libelle' => $secDaf->nom,
            'nombre_pieces' => 1,
            'numero_archives' => 'DG/DEP/2026/002',
            'observations' => 'Saisi à l’expédition',
            'createur_id' => $secretaire->id,
            'structure_id' => $secretaire->structure_id,
            'date_expedition' => now(),
        ]);

        $depart->refresh();
        $this->assertSame('DG/DEP/2026/002', $depart->numero_archives);
        $this->assertSame('Saisi à l’expédition', $depart->observations);
        $this->assertFalse($depart->peutEtreArchive());
    }

    private function creerSecretaire(): User
    {
        $secDir = Structure::where('code', 'SEC-DIR')->firstOrFail();
        $dg = Structure::where('code', 'DG')->firstOrFail();

        $directeur = User::factory()->create(['structure_id' => $dg->id]);
        $directeur->assignRole('directeur');

        $user = User::factory()->create(['structure_id' => $secDir->id]);
        $user->assignRole('secretaire_direction');

        return $user;
    }
}
