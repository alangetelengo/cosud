<?php

namespace Database\Seeders;

use App\Models\Courrier;
use App\Models\PrioriteCourrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\TypeCourrier;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Exemples réalistes pour valider les registres papier Arrivée / Départ (secrétariat DG).
 */
class CourrierRegistreDemoSeeder extends Seeder
{
    public function run(): void
    {
        $createur = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'secretaire_direction', 'directeur']))
            ->orderBy('id')
            ->first()
            ?? User::query()->orderBy('id')->first();

        if (! $createur) {
            $this->command?->warn('CourrierRegistreDemoSeeder : aucun utilisateur — seed annulé.');

            return;
        }

        $arrivee = SensCourrier::where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $depart = SensCourrier::where('code', SensCourrier::DEPART)->firstOrFail();

        $statutRecu = StatutCourrier::where('sens_courrier_id', $arrivee->id)->where('code', 'recu')->firstOrFail();
        $statutOriente = StatutCourrier::where('sens_courrier_id', $arrivee->id)->where('code', 'oriente')->firstOrFail();
        $statutCloture = StatutCourrier::where('sens_courrier_id', $arrivee->id)->where('code', 'cloture')->firstOrFail();
        $statutSigne = StatutCourrier::where('sens_courrier_id', $depart->id)->where('code', 'signe')->firstOrFail();
        $statutExpedie = StatutCourrier::where('sens_courrier_id', $depart->id)->where('code', 'expedie')->firstOrFail();

        $typeDemande = TypeCourrier::where('code', 'demande')->first();
        $typeAdmin = TypeCourrier::where('code', 'administratif')->first();
        $typeReponse = TypeCourrier::where('code', 'reponse')->first();
        $priorite = PrioriteCourrier::where('code', 'normale')->first();

        $annee = (int) now()->year;

        // Évite les doublons si le seeder est relancé
        Courrier::query()
            ->where('numero_registre_annee', $annee)
            ->where('objet', 'like', '[DÉMO]%')
            ->delete();

        $nextArrivee = (int) Courrier::query()
            ->where('sens_courrier_id', $arrivee->id)
            ->where('numero_registre_annee', $annee)
            ->max('numero_registre') + 1;
        $nextDepart = (int) Courrier::query()
            ->where('sens_courrier_id', $depart->id)
            ->where('numero_registre_annee', $annee)
            ->max('numero_registre') + 1;

        $nA = $nextArrivee;
        $nD = $nextDepart;

        $facture = Courrier::create([
            'sens_courrier_id' => $arrivee->id,
            'type_courrier_id' => $typeDemande?->id,
            'statut_courrier_id' => $statutOriente->id,
            'priorite_courrier_id' => $priorite?->id,
            'numero_registre' => $nA++,
            'numero_registre_annee' => $annee,
            'reference' => 'FAC-2026-0142',
            'origine' => Courrier::ORIGINE_EXTERNE,
            'date_reception' => now()->subDays(18)->toDateString(),
            'date_courrier' => now()->subDays(20)->toDateString(),
            'expediteur_libelle' => 'Entreprise NETPLUS SARL',
            'est_expediteur_externe' => true,
            'objet' => '[DÉMO] Facture prestations maintenance réseau — juin 2026',
            'nombre_pieces' => 3,
            'numero_archives' => 'DG/ARCH/2026/001',
            'observations' => 'Dossier prestataire — circuit paiement',
            'instructions_dg' => 'À traiter pour paiement après contrôle DAF.',
            'date_orientation' => now()->subDays(17),
            'createur_id' => $createur->id,
            'structure_id' => $createur->structure_id,
        ]);

        $departDaf = Courrier::create([
            'sens_courrier_id' => $depart->id,
            'type_courrier_id' => $typeAdmin?->id,
            'statut_courrier_id' => $statutExpedie->id,
            'priorite_courrier_id' => $priorite?->id,
            'numero_registre' => $nD++,
            'numero_registre_annee' => $annee,
            'reference' => 'DG/DEP/2026/0001',
            'origine' => Courrier::ORIGINE_INTERNE,
            'date_courrier' => now()->subDays(14)->toDateString(),
            'date_expedition' => now()->subDays(14),
            'destinataire_libelle' => 'Secrétariat DAF',
            'expediteur_libelle' => 'Secrétariat DG',
            'est_expediteur_externe' => false,
            'objet' => '[DÉMO] Transmission facture NETPLUS pour élaboration du chèque',
            'nombre_pieces' => 2,
            'numero_archives' => 'DG/DEP/2026/001',
            'observations' => 'Transmission vers DAF — paiement',
            'createur_id' => $createur->id,
            'structure_id' => $createur->structure_id,
            'courrier_parent_id' => $facture->id,
        ]);

        $cheque = Courrier::create([
            'sens_courrier_id' => $arrivee->id,
            'type_courrier_id' => $typeAdmin?->id,
            'statut_courrier_id' => $statutOriente->id,
            'priorite_courrier_id' => $priorite?->id,
            'numero_registre' => $nA++,
            'numero_registre_annee' => $annee,
            'reference' => 'CHQ-DAF-4587',
            'origine' => Courrier::ORIGINE_INTERNE,
            'date_reception' => now()->subDays(10)->toDateString(),
            'date_courrier' => now()->subDays(10)->toDateString(),
            'expediteur_libelle' => 'Direction Administrative et Financière (DAF)',
            'est_expediteur_externe' => false,
            'objet' => '[DÉMO] Chèque n° 4587 — paiement facture NETPLUS SARL',
            'nombre_pieces' => 2,
            'numero_archives' => 'DG/ARCH/2026/002',
            'observations' => 'Suivi dépenses — signature DG puis AC',
            'instructions_dg' => 'Signer et transmettre à l’Agent comptable.',
            'date_orientation' => now()->subDays(9),
            'createur_id' => $createur->id,
            'structure_id' => $createur->structure_id,
            'courrier_depart_source_id' => $departDaf->id,
        ]);

        Courrier::create([
            'sens_courrier_id' => $depart->id,
            'type_courrier_id' => $typeAdmin?->id,
            'statut_courrier_id' => $statutSigne->id,
            'priorite_courrier_id' => $priorite?->id,
            'numero_registre' => $nD++,
            'numero_registre_annee' => $annee,
            'reference' => 'DG/DEP/2026/0002',
            'origine' => Courrier::ORIGINE_INTERNE,
            'date_courrier' => now()->subDays(8)->toDateString(),
            'date_expedition' => now()->subDays(8),
            'destinataire_libelle' => 'Agent Comptable (AC)',
            'expediteur_libelle' => 'Secrétariat DG',
            'est_expediteur_externe' => false,
            'objet' => '[DÉMO] Transmission chèque n° 4587 signé pour paiement',
            'nombre_pieces' => 1,
            'numero_archives' => 'DG/DEP/2026/002',
            'observations' => 'Chèque signé DG → AC',
            'createur_id' => $createur->id,
            'structure_id' => $createur->structure_id,
            'courrier_parent_id' => $cheque->id,
            'signataire_id' => $createur->id,
        ]);

        Courrier::create([
            'sens_courrier_id' => $arrivee->id,
            'type_courrier_id' => $typeDemande?->id,
            'statut_courrier_id' => $statutCloture->id,
            'priorite_courrier_id' => $priorite?->id,
            'numero_registre' => $nA++,
            'numero_registre_annee' => $annee,
            'reference' => 'INV-ACSI-09',
            'origine' => Courrier::ORIGINE_EXTERNE,
            'date_reception' => now()->subDays(25)->toDateString(),
            'date_courrier' => now()->subDays(27)->toDateString(),
            'expediteur_libelle' => 'Ministère de tutelle',
            'est_expediteur_externe' => true,
            'objet' => '[DÉMO] Invitation réunion de coordination sectorielle',
            'nombre_pieces' => 1,
            'numero_archives' => 'DG/ARCH/2026/003',
            'observations' => 'Clôturé après réponse',
            'createur_id' => $createur->id,
            'structure_id' => $createur->structure_id,
        ]);

        $noteArrivee = Courrier::create([
            'sens_courrier_id' => $arrivee->id,
            'type_courrier_id' => $typeAdmin?->id,
            'statut_courrier_id' => $statutRecu->id,
            'priorite_courrier_id' => $priorite?->id,
            'numero_registre' => $nA++,
            'numero_registre_annee' => $annee,
            'reference' => 'FOURN-2026-88',
            'origine' => Courrier::ORIGINE_EXTERNE,
            'date_reception' => now()->subDays(3)->toDateString(),
            'date_courrier' => now()->subDays(5)->toDateString(),
            'expediteur_libelle' => 'Société CONGO-BUREAU',
            'est_expediteur_externe' => true,
            'objet' => '[DÉMO] Facture fournitures de bureau — lot 2026',
            'nombre_pieces' => 2,
            'numero_archives' => null,
            'observations' => 'En attente d’instructions DG',
            'createur_id' => $createur->id,
            'structure_id' => $createur->structure_id,
        ]);

        Courrier::create([
            'sens_courrier_id' => $depart->id,
            'type_courrier_id' => $typeReponse?->id ?? $typeAdmin?->id,
            'statut_courrier_id' => $statutExpedie->id,
            'priorite_courrier_id' => $priorite?->id,
            'numero_registre' => $nD++,
            'numero_registre_annee' => $annee,
            'reference' => 'DG/DEP/2026/0003',
            'origine' => Courrier::ORIGINE_INTERNE,
            'date_courrier' => now()->subDays(20)->toDateString(),
            'date_expedition' => now()->subDays(20),
            'destinataire_libelle' => 'Ministère de tutelle',
            'expediteur_libelle' => 'Direction Générale — ACSI',
            'est_expediteur_externe' => false,
            'objet' => '[DÉMO] Accusé de réception — invitation réunion de coordination',
            'nombre_pieces' => 1,
            'numero_archives' => 'DG/DEP/2026/003',
            'observations' => 'Note / lettre signée DG',
            'createur_id' => $createur->id,
            'structure_id' => $createur->structure_id,
            'courrier_parent_id' => Courrier::query()
                ->where('objet', '[DÉMO] Invitation réunion de coordination sectorielle')
                ->value('id'),
            'signataire_id' => $createur->id,
        ]);

        Courrier::create([
            'sens_courrier_id' => $depart->id,
            'type_courrier_id' => $typeAdmin?->id,
            'statut_courrier_id' => $statutSigne->id,
            'priorite_courrier_id' => $priorite?->id,
            'numero_registre' => $nD++,
            'numero_registre_annee' => $annee,
            'reference' => 'DG/NS/2026/0012',
            'origine' => Courrier::ORIGINE_INTERNE,
            'date_courrier' => now()->subDays(2)->toDateString(),
            'date_expedition' => now()->subDays(1),
            'destinataire_libelle' => 'Tous les chefs de service',
            'expediteur_libelle' => 'Direction Générale — ACSI',
            'est_expediteur_externe' => false,
            'objet' => '[DÉMO] Note de service — organisation des horaires de permanence',
            'nombre_pieces' => 1,
            'numero_archives' => 'DG/NS/2026/012',
            'observations' => 'Instructions DG — diffusion interne',
            'createur_id' => $createur->id,
            'structure_id' => $createur->structure_id,
            'signataire_id' => $createur->id,
        ]);

        unset($noteArrivee);

        $this->command?->info('Registres démo : 4 arrivées + 4 départs (préfixe [DÉMO]).');
    }
}
