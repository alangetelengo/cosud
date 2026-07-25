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

    public function test_archivage_depart_enregistre_infos_registre(): void
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
            'createur_id' => $secretaire->id,
            'structure_id' => $secretaire->structure_id,
            'date_expedition' => now(),
        ]);

        $this->actingAs($secretaire)
            ->get(route('courriers.show', $depart, absolute: false))
            ->assertOk()
            ->assertSee('>Archiver<', false)
            ->assertSee('name="numero_archives"', false)
            ->assertSee('name="observations"', false);

        $this->actingAs($secretaire)
            ->post(route('courriers.archiver', $depart, absolute: false), [
                'nombre_pieces' => 2,
                'numero_archives' => 'DG/DEP/2026/001',
                'observations' => 'Archivé après AR destinataire',
            ])
            ->assertRedirect();

        $depart->refresh();
        $this->assertSame('archive', $depart->statutCourrier->code);
        $this->assertSame(2, $depart->nombre_pieces);
        $this->assertSame('DG/DEP/2026/001', $depart->numero_archives);
        $this->assertSame('Archivé après AR destinataire', $depart->observations);

        $this->actingAs($secretaire)
            ->get(route('courriers.registres.depart', ['annee' => $depart->numero_registre_annee], absolute: false))
            ->assertOk()
            ->assertSee('DG/DEP/2026/001', false)
            ->assertSee('Archivé après AR destinataire', false);
    }

    public function test_archivage_depart_preserve_infos_deja_saisies_a_lexpedition(): void
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

        $this->actingAs($secretaire)
            ->post(route('courriers.archiver', $depart, absolute: false), [
                'nombre_pieces' => 1,
                'numero_archives' => 'DG/DEP/2026/002',
                'observations' => 'Saisi à l’expédition',
            ])
            ->assertRedirect();

        $depart->refresh();
        $this->assertSame('DG/DEP/2026/002', $depart->numero_archives);
        $this->assertSame('Saisi à l’expédition', $depart->observations);
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
