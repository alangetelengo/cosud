<?php

namespace Database\Seeders;

use App\Models\Parapheur;
use App\Models\PrioriteCourrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\TypeCourrier;
use Illuminate\Database\Seeder;

class CourrierReferentielSeeder extends Seeder
{
    public function run(): void
    {
        $arrivee = SensCourrier::updateOrCreate(
            ['code' => SensCourrier::ARRIVEE],
            ['libelle' => 'Courrier arrivée', 'actif' => true]
        );
        $depart = SensCourrier::updateOrCreate(
            ['code' => SensCourrier::DEPART],
            ['libelle' => 'Courrier départ', 'actif' => true]
        );

        foreach ([
            ['code' => 'administratif', 'libelle' => 'Administratif'],
            ['code' => 'invitation', 'libelle' => 'Invitation'],
            ['code' => 'demande', 'libelle' => 'Demande'],
            ['code' => 'reponse', 'libelle' => 'Réponse'],
            ['code' => 'autre', 'libelle' => 'Autre'],
        ] as $t) {
            TypeCourrier::updateOrCreate(['code' => $t['code']], array_merge($t, ['actif' => true]));
        }

        foreach ([
            ['code' => 'normale', 'libelle' => 'Normale', 'ordre' => 1],
            ['code' => 'urgente', 'libelle' => 'Urgente', 'ordre' => 2],
            ['code' => 'tres_urgente', 'libelle' => 'Très urgente', 'ordre' => 3],
        ] as $p) {
            PrioriteCourrier::updateOrCreate(['code' => $p['code']], array_merge($p, ['actif' => true]));
        }

        $statutsArrivee = [
            ['code' => 'recu', 'libelle' => 'Reçu', 'ordre' => 1, 'est_initial' => true, 'est_final' => false],
            ['code' => 'en_parapheur', 'libelle' => 'En parapheur', 'ordre' => 2, 'est_initial' => false, 'est_final' => false],
            ['code' => 'attente_reponse_particuliere', 'libelle' => 'Attente réponse (particulière)', 'ordre' => 25, 'est_initial' => false, 'est_final' => false],
            ['code' => 'oriente', 'libelle' => 'Orienté (DG)', 'ordre' => 3, 'est_initial' => false, 'est_final' => false],
            ['code' => 'ventile', 'libelle' => 'Ventilé', 'ordre' => 4, 'est_initial' => false, 'est_final' => false],
            ['code' => 'cloture', 'libelle' => 'Clôturé', 'ordre' => 5, 'est_initial' => false, 'est_final' => true],
            ['code' => 'annule', 'libelle' => 'Annulé', 'ordre' => 6, 'est_initial' => false, 'est_final' => true],
        ];
        foreach ($statutsArrivee as $s) {
            StatutCourrier::updateOrCreate(
                ['sens_courrier_id' => $arrivee->id, 'code' => $s['code']],
                array_merge($s, ['actif' => true])
            );
        }

        $statutsDepart = [
            ['code' => 'brouillon', 'libelle' => 'Brouillon', 'ordre' => 1, 'est_initial' => true, 'est_final' => false],
            ['code' => 'transmis_directeur', 'libelle' => 'Transmis au directeur', 'ordre' => 2, 'est_initial' => false, 'est_final' => false],
            ['code' => 'rejete_directeur', 'libelle' => 'Rejeté par le directeur', 'ordre' => 3, 'est_initial' => false, 'est_final' => false],
            ['code' => 'signe', 'libelle' => 'Signé', 'ordre' => 4, 'est_initial' => false, 'est_final' => false],
            ['code' => 'expedie', 'libelle' => 'Expédié', 'ordre' => 5, 'est_initial' => false, 'est_final' => false],
            ['code' => 'reception_refusee', 'libelle' => 'Réception refusée', 'ordre' => 6, 'est_initial' => false, 'est_final' => false],
            ['code' => 'archive', 'libelle' => 'Archivé', 'ordre' => 7, 'est_initial' => false, 'est_final' => true],
            ['code' => 'annule', 'libelle' => 'Annulé', 'ordre' => 8, 'est_initial' => false, 'est_final' => true],
            ['code' => 'en_relecture', 'libelle' => 'En relecture (ancien)', 'ordre' => 90, 'est_initial' => false, 'est_final' => false],
            ['code' => 'a_signer', 'libelle' => 'À signer (ancien)', 'ordre' => 91, 'est_initial' => false, 'est_final' => false],
        ];
        foreach ($statutsDepart as $s) {
            StatutCourrier::updateOrCreate(
                ['sens_courrier_id' => $depart->id, 'code' => $s['code']],
                array_merge($s, ['actif' => true])
            );
        }

        Parapheur::updateOrCreate(
            ['sens_courrier_id' => $arrivee->id, 'code' => 'arrivee_dg'],
            ['libelle' => 'Parapheur arrivée — Direction', 'actif' => true]
        );
        Parapheur::updateOrCreate(
            ['sens_courrier_id' => $depart->id, 'code' => 'depart_dg'],
            ['libelle' => 'Parapheur départ — Direction', 'actif' => true]
        );
    }
}
