<?php

namespace Tests\Feature;

use App\Models\Structure;
use App\Models\User;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StructureDafServicesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_daf_contient_les_cinq_services_de_l_article_38(): void
    {
        $this->seed(StructureSeeder::class);

        $daf = Structure::where('code', 'DAF')->firstOrFail();

        $services = Structure::query()
            ->where('parent_id', $daf->id)
            ->where('type', 'service')
            ->orderBy('code')
            ->get()
            ->keyBy('code');

        $this->assertTrue($services->has('SVC-DAF-RH'));
        $this->assertTrue($services->has('SVC-DAF-APPRO'));
        $this->assertTrue($services->has('SVC-DAF-BUDGET'));
        $this->assertTrue($services->has('SVC-DAF-FIN'));
        $this->assertTrue($services->has('SVC-DAF-DOC'));

        $this->assertSame('SERVICE DES RESSOURCES HUMAINES', $services['SVC-DAF-RH']->nom);
        $this->assertSame('SERVICE DES APPROVISIONNEMENTS ET DU PATRIMOINE', $services['SVC-DAF-APPRO']->nom);
        $this->assertSame('SERVICE DU BUDGET', $services['SVC-DAF-BUDGET']->nom);
        $this->assertSame('SERVICE DES FINANCES', $services['SVC-DAF-FIN']->nom);
        $this->assertSame('SERVICE DE LA DOCUMENTATION ET DE L\'ARCHIVAGE', $services['SVC-DAF-DOC']->nom);

        $this->assertDatabaseMissing('structures', ['code' => 'SVC-FIN']);
    }

    public function test_dac_et_secretariat_sont_crees_sous_la_dg(): void
    {
        $this->seed(StructureSeeder::class);

        $dg = Structure::where('code', 'DG')->firstOrFail();
        $dac = Structure::where('code', 'DAC')->firstOrFail();
        $secDac = Structure::where('code', 'SEC-DAC')->firstOrFail();

        $this->assertSame('DIRECTION DE L\'AGENCE COMPTABLE', $dac->nom);
        $this->assertSame('direction', $dac->type);
        $this->assertSame($dg->id, (int) $dac->parent_id);

        $this->assertSame('SECRÉTARIAT DIR. DAC', $secDac->nom);
        $this->assertSame('secretariat', $secDac->type);
        $this->assertSame($dac->id, (int) $secDac->parent_id);
    }

    public function test_reseed_migre_les_utilisateurs_de_svc_fin_vers_svc_daf_fin_sans_perte(): void
    {
        // Simule une base déjà peuplée avec l'ancien code, avant le renommage en SVC-DAF-FIN.
        $daf = Structure::create(['code' => 'DAF', 'nom' => 'DIRECTION FINANCIERE ET COMPTABLE', 'type' => 'direction', 'actif' => true]);
        $ancienne = Structure::create(['code' => 'SVC-FIN', 'nom' => 'SCE COMPTABILITES', 'type' => 'service', 'parent_id' => $daf->id, 'actif' => true]);
        $agent = User::factory()->create(['structure_id' => $ancienne->id]);

        $this->seed(StructureSeeder::class);

        $nouvelle = Structure::where('code', 'SVC-DAF-FIN')->firstOrFail();

        $this->assertSame($nouvelle->id, $agent->fresh()->structure_id);
        $this->assertDatabaseMissing('structures', ['code' => 'SVC-FIN']);
    }

    public function test_reseed_ne_supprime_pas_les_structures_hors_liste(): void
    {
        // Une structure créée hors du seed (autre seeder, UI admin…) ne doit jamais être
        // supprimée par un re-seed : seuls les codes explicitement renommés sont migrés.
        $structureExterne = Structure::create(['code' => 'SVC-CUSTOM', 'nom' => 'Service ajouté manuellement', 'type' => 'service', 'actif' => true]);

        $this->seed(StructureSeeder::class);

        $this->assertDatabaseHas('structures', ['id' => $structureExterne->id, 'code' => 'SVC-CUSTOM']);
    }
}
