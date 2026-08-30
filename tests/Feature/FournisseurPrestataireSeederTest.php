<?php

namespace Tests\Feature;

use App\Models\FournisseurPrestataire;
use Database\Seeders\FournisseurPrestataireSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FournisseurPrestataireSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_importe_le_referentiel_et_conserve_les_fiches_enrichies(): void
    {
        $this->seed(FournisseurPrestataireSeeder::class);
        $this->seed(FournisseurPrestataireSeeder::class);

        // 87 feuilles Excel + ACS + AIRTEL (BILLY déjà dans le fichier) + 7 checklist reprise = 95.
        $this->assertSame(95, FournisseurPrestataire::query()->count());

        $billy = FournisseurPrestataire::query()
            ->where('nom_normalise', FournisseurPrestataire::normaliserNom('BILLY SERVICES'))
            ->firstOrFail();

        $this->assertSame(FournisseurPrestataire::TYPE_PRESTATAIRE, $billy->type);
        $this->assertTrue($billy->a_contrat);
        $this->assertSame('Location véhicule', $billy->type_contrat);

        $airtel = FournisseurPrestataire::query()
            ->where('nom_normalise', FournisseurPrestataire::normaliserNom('AIRTEL'))
            ->firstOrFail();

        $this->assertSame(FournisseurPrestataire::TYPE_PARTENAIRE, $airtel->type);

        $afcom = FournisseurPrestataire::query()
            ->where('nom_normalise', FournisseurPrestataire::normaliserNom('AFCOM'))
            ->firstOrFail();

        $this->assertSame(FournisseurPrestataire::TYPE_FOURNISSEUR, $afcom->type);
        $this->assertFalse($afcom->a_contrat);

        $associe = FournisseurPrestataire::query()
            ->where('nom_normalise', FournisseurPrestataire::normaliserNom("L'ASSOCIE"))
            ->firstOrFail();

        $this->assertSame("L'ASSOCIE", $associe->nom);

        $banab = FournisseurPrestataire::query()
            ->where('nom_normalise', FournisseurPrestataire::normaliserNom('BANAB TECH & SCES'))
            ->firstOrFail();

        $this->assertSame('BANAB TECH & SCES', $banab->nom);
    }

    public function test_seeder_couvre_les_fournisseurs_checklist_reprise_aout(): void
    {
        $this->seed(FournisseurPrestataireSeeder::class);

        $nomsChecklist = [
            'EDITION LES SOZO',
            'BILLY SERVICES',
            'BUROTEC',
            'SOFT RENOVATIONS',
            'AF,COM',
            'METRE DE LUXE',
            'ETS DB',
            'PHARMACIE MAVRE',
            'GROUPE DREAMS LINK TECHNOLOGIES',
            'ETS DALPIVOT AIR FROID',
            'DS POWER',
            'ETS ND SERVICES',
            "AUTO MOBILE TOYOTA D'ORIGINE PIECES",
            'E²C',
            'SILICON CONNECT',
        ];

        foreach ($nomsChecklist as $nom) {
            $this->assertTrue(
                FournisseurPrestataire::query()
                    ->where('nom_normalise', FournisseurPrestataire::normaliserNom($nom))
                    ->exists(),
                "Fournisseur checklist introuvable après seed : {$nom}"
            );
        }
    }

    public function test_normaliser_nom_rattache_les_variantes_checklist(): void
    {
        $this->assertSame(
            FournisseurPrestataire::normaliserNom('ED. LES SOZO'),
            FournisseurPrestataire::normaliserNom('EDITION LES SOZO')
        );
        $this->assertSame(
            FournisseurPrestataire::normaliserNom('SOFT-RENOVATION'),
            FournisseurPrestataire::normaliserNom('SOFT RENOVATIONS')
        );
        $this->assertSame(
            FournisseurPrestataire::normaliserNom('AFCOM'),
            FournisseurPrestataire::normaliserNom('AF,COM')
        );
        $this->assertSame(
            FournisseurPrestataire::normaliserNom('E2C'),
            FournisseurPrestataire::normaliserNom('E²C')
        );
    }
}
