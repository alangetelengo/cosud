<?php

namespace Tests\Feature;

use App\Models\Courrier;
use App\Models\PrioriteCourrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\Structure;
use App\Models\TypeCourrier;
use App\Models\User;
use Database\Seeders\CourrierReferentielSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourrierRegistreTest extends TestCase
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

    public function test_secretaire_peut_voir_registre_arrivee_avec_colonnes_papier(): void
    {
        $user = $this->creerSecretaire();
        $this->creerCourrierArrivee($user);

        $this->actingAs($user)
            ->get(route('courriers.registres.arrivee', absolute: false))
            ->assertOk()
            ->assertSee('COURRIER', false)
            ->assertSee('ARRIVÉE', false)
            ->assertSee('DATE D\'ARRIVÉE', false)
            ->assertSee('EXPÉDITEUR', false)
            ->assertSee('OBJET', false)
            ->assertSee('Entreprise NETPLUS SARL', false)
            ->assertSee('Imprimer / PDF', false)
            ->assertSee('Page de garde', false)
            ->assertSee('registre-closed-book', false)
            ->assertSee('is-closed', false)
            ->assertSee('arrêtons et clôturons', false)
            ->assertSee('Fin du livret', false)
            ->assertSee('feuilleter', false)
            ->assertSee('modernizr.custom.js', false)
            ->assertSee('registre-cloture-face', false)
            ->assertSee('registre-feuille-face', false)
            ->assertSee('registre-livret-shell', false)
            ->assertSee('Mois — tous', false)
            ->assertSee('Trimestre — tous', false)
            ->assertSee('Appliquer', false)
            ->assertDontSee('lignes / page', false);
    }

    public function test_secretaire_peut_voir_registre_depart_avec_colonnes_papier(): void
    {
        $user = $this->creerSecretaire();
        $this->creerCourrierDepart($user);

        $this->actingAs($user)
            ->get(route('courriers.registres.depart', absolute: false))
            ->assertOk()
            ->assertSee('DÉPART', false)
            ->assertSee('N° D\'ORDRE', false)
            ->assertSee('DESTINATAIRE', false)
            ->assertSee('Secrétariat DAF', false)
            ->assertSee('DG/DEP/2026/001', false)
            ->assertSee('COURRIER', false)
            ->assertSee('Page de garde', false)
            ->assertSee('arrêtons et clôturons', false)
            ->assertSee(Structure::where('code', 'SEC-DIR')->value('nom'), false)
            ->assertSee('Mois — tous', false)
            ->assertSee('Trimestre — tous', false);
    }

    public function test_page_impression_registre_arrivee(): void
    {
        $user = $this->creerSecretaire();
        $this->creerCourrierArrivee($user);

        $this->actingAs($user)
            ->get(route('courriers.registres.print-arrivee', absolute: false))
            ->assertOk()
            ->assertSee('ARRIVÉE', false)
            ->assertSee('window.print', false);
    }

    public function test_utilisateur_sans_droit_ne_voit_pas_les_registres(): void
    {
        $user = User::factory()->create();
        $user->assignRole('utilisateur');

        $this->actingAs($user)
            ->get(route('courriers.registres.arrivee', absolute: false))
            ->assertForbidden();
    }

    public function test_filtre_mois_registre_arrivee(): void
    {
        $user = $this->creerSecretaire();
        $enJanvier = $this->creerCourrierArrivee($user, now()->setMonth(1)->setDay(15));
        $enJuin = $this->creerCourrierArrivee($user, now()->setMonth(6)->setDay(10), 2);

        $this->actingAs($user)
            ->get(route('courriers.registres.arrivee', ['mois' => 1], absolute: false))
            ->assertOk()
            ->assertSee('Entreprise NETPLUS SARL', false)
            ->assertDontSee('Entreprise JUIN SARL', false);

        $this->actingAs($user)
            ->get(route('courriers.registres.arrivee', ['mois' => 6], absolute: false))
            ->assertOk()
            ->assertSee('Entreprise JUIN SARL', false)
            ->assertDontSee('Entreprise NETPLUS SARL', false);

        $this->assertSame(1, $enJanvier->numero_registre);
        $this->assertSame(2, $enJuin->numero_registre);
    }

    public function test_filtre_trimestre_registre_arrivee(): void
    {
        $user = $this->creerSecretaire();
        $this->creerCourrierArrivee($user, now()->setMonth(2)->setDay(5));
        $this->creerCourrierArrivee($user, now()->setMonth(8)->setDay(20), 2);

        $this->actingAs($user)
            ->get(route('courriers.registres.arrivee', ['trimestre' => 1], absolute: false))
            ->assertOk()
            ->assertSee('Entreprise NETPLUS SARL', false)
            ->assertDontSee('Entreprise JUIN SARL', false);

        $this->actingAs($user)
            ->get(route('courriers.registres.arrivee', ['trimestre' => 3], absolute: false))
            ->assertOk()
            ->assertSee('Entreprise JUIN SARL', false)
            ->assertDontSee('Entreprise NETPLUS SARL', false);
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

    private function creerCourrierArrivee(User $user, $dateReception = null, int $numero = 1): Courrier
    {
        $sens = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'recu')->firstOrFail();
        $dateReception = $dateReception ?? now();

        return Courrier::create([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => TypeCourrier::where('code', 'demande')->value('id'),
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => PrioriteCourrier::where('code', 'normale')->value('id'),
            'numero_registre' => $numero,
            'numero_registre_annee' => (int) now()->year,
            'reference' => 'FAC-2026-0'.$numero,
            'origine' => Courrier::ORIGINE_EXTERNE,
            'date_reception' => $dateReception->toDateString(),
            'date_courrier' => $dateReception->copy()->subDay()->toDateString(),
            'expediteur_libelle' => $numero === 2 ? 'Entreprise JUIN SARL' : 'Entreprise NETPLUS SARL',
            'objet' => 'Facture prestations maintenance réseau',
            'nombre_pieces' => 3,
            'createur_id' => $user->id,
            'structure_id' => $user->structure_id,
        ]);
    }

    private function creerCourrierDepart(User $user): Courrier
    {
        $sens = SensCourrier::where('code', SensCourrier::DEPART)->firstOrFail();
        $statut = StatutCourrier::where('sens_courrier_id', $sens->id)->where('code', 'expedie')->firstOrFail();

        return Courrier::create([
            'sens_courrier_id' => $sens->id,
            'type_courrier_id' => TypeCourrier::where('code', 'administratif')->value('id'),
            'statut_courrier_id' => $statut->id,
            'priorite_courrier_id' => PrioriteCourrier::where('code', 'normale')->value('id'),
            'numero_registre' => 1,
            'numero_registre_annee' => (int) now()->year,
            'reference' => 'DG/DEP/2026/0001',
            'origine' => Courrier::ORIGINE_INTERNE,
            'date_courrier' => now()->toDateString(),
            'date_expedition' => now(),
            'destinataire_libelle' => 'Secrétariat DAF',
            'objet' => 'Transmission facture pour élaboration du chèque',
            'nombre_pieces' => 2,
            'numero_archives' => 'DG/DEP/2026/001',
            'observations' => 'Transmission vers DAF',
            'createur_id' => $user->id,
            'structure_id' => $user->structure_id,
        ]);
    }
}
