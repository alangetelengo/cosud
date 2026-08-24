<?php

namespace Database\Seeders;

use App\Models\CircuitCourrier;
use App\Models\CircuitCourrierEtape;
use App\Models\Courrier;
use App\Models\TypeCourrier;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Circuits métier DG (version corrigée particulière) + rôles acteurs dédiés.
 */
class CircuitCourrierSeeder extends Seeder
{
    public function run(): void
    {
        $this->assurerRolesActeurs();

        $facture = CircuitCourrier::updateOrCreate(
            ['code' => 'facture_prestataire'],
            [
                'libelle' => 'Factures prestataires / fournisseurs',
                'description' => 'Circuit A : BPA DG → AC établit chèque → DG signe (sans scan) → AC enregistre décharge (bordereau) → contrôle Eleni / clôture. Taty suit en parallèle.',
                'sens_initial' => CircuitCourrier::SENS_ARRIVEE,
                'actif' => true,
            ]
        );

        $this->syncEtapes($facture, [
            [
                'ordre' => 1,
                'code' => 'enregistrement',
                'nom' => 'Enregistrement courrier arrivée',
                'acteur_type' => CircuitCourrierEtape::ACTEUR_SECRETARIAT,
                'acteur_valeur' => null,
                'action' => CircuitCourrierEtape::ACTION_ENREGISTRER,
                'mouvement' => CircuitCourrierEtape::MOUVEMENT_AUCUN,
                'notifie_roles' => ['dg', 'particulier_dg'],
                'instructions_aide' => 'Enregistrer la pièce dans le registre Arrivée.',
            ],
            [
                'ordre' => 2,
                'code' => 'instructions_dg',
                'nom' => 'Bon pour accord / instructions DG',
                'acteur_type' => CircuitCourrierEtape::ACTEUR_DG,
                'acteur_valeur' => null,
                'action' => CircuitCourrierEtape::ACTION_INSTRUIRE,
                'mouvement' => CircuitCourrierEtape::MOUVEMENT_AUCUN,
                'notifie_roles' => ['particulier_dg', 'particulier_ac', 'agent_comptable', 'responsable_dossiers_prestataires'],
                'instructions_aide' => 'Le DG donne son Bon pour accord. L’AC est notifié pour établir le chèque ; la responsable dossiers suit en parallèle.',
            ],
            [
                'ordre' => 3,
                'code' => 'ac_etablit_cheque',
                'nom' => 'AC établit le chèque → envoi DG',
                'acteur_type' => CircuitCourrierEtape::ACTEUR_ROLE,
                'acteur_valeur' => 'agent_comptable',
                'action' => CircuitCourrierEtape::ACTION_TRAITER,
                'mouvement' => CircuitCourrierEtape::MOUVEMENT_ATTENDRE_ARRIVEE,
                'notifie_roles' => ['agent_comptable', 'particulier_dg', 'particulier_ac'],
                'instructions_aide' => 'L’AC établit le chèque et l’envoie au DG pour signature (sans scan dans le GED).',
            ],
            [
                'ordre' => 4,
                'code' => 'dg_signe_cheque',
                'nom' => 'DG signe le chèque → renvoi AC',
                'acteur_type' => CircuitCourrierEtape::ACTEUR_DG,
                'acteur_valeur' => null,
                'action' => CircuitCourrierEtape::ACTION_SIGNER,
                'mouvement' => CircuitCourrierEtape::MOUVEMENT_AUCUN,
                'notifie_roles' => ['agent_comptable', 'particulier_dg', 'particulier_ac', 'responsable_dossiers_prestataires'],
                'instructions_aide' => 'Le DG confirme que le chèque est signé (sans scan) et renvoie le dossier à l’AC pour la décharge bénéficiaire.',
            ],
            [
                'ordre' => 5,
                'code' => 'preuve_paiement',
                'nom' => 'AC — enregistrement décharge / paiement (clôture)',
                'acteur_type' => CircuitCourrierEtape::ACTEUR_ROLE,
                'acteur_valeur' => 'agent_comptable',
                'action' => CircuitCourrierEtape::ACTION_CLOTURER,
                'mouvement' => CircuitCourrierEtape::MOUVEMENT_AUCUN,
                'notifie_roles' => ['responsable_suivi_depenses', 'particulier_dg', 'particulier_ac', 'dg', 'responsable_dossiers_prestataires'],
                'instructions_aide' => 'À la décharge du bénéficiaire : saisir la date, joindre les pièces (chèque déchargé, identité…). Cette action clôture le circuit. Mme Eleni contrôlera ensuite les pièces hors circuit.',
                'est_finale' => true,
            ],
        ]);

        $this->reassignerCourriersEtapeObsoleteDossiersVersAc($facture);
        $this->reassignerCourriersEtapesCaissiersVersDecharge($facture);
        $this->cloturerCourriersBloquesSurControleEleni($facture);

        $general = CircuitCourrier::updateOrCreate(
            ['code' => 'courrier_general'],
            [
                'libelle' => 'Courriers généraux / notes / instructions',
                'description' => 'Circuit B : arrivée → instruction → préparation départ → signature DG → expédition',
                'sens_initial' => CircuitCourrier::SENS_ARRIVEE,
                'actif' => true,
            ]
        );

        $this->syncEtapes($general, [
            [
                'ordre' => 1,
                'code' => 'enregistrement',
                'nom' => 'Enregistrement courrier arrivée',
                'acteur_type' => CircuitCourrierEtape::ACTEUR_SECRETARIAT,
                'acteur_valeur' => null,
                'action' => CircuitCourrierEtape::ACTION_ENREGISTRER,
                'mouvement' => CircuitCourrierEtape::MOUVEMENT_AUCUN,
                'notifie_roles' => ['dg', 'particulier_dg'],
                'instructions_aide' => 'Enregistrer dans le registre Arrivée.',
            ],
            [
                'ordre' => 2,
                'code' => 'notification_dg_particuliere',
                'nom' => 'Notification DG et particulière',
                'acteur_type' => CircuitCourrierEtape::ACTEUR_SECRETARIAT,
                'acteur_valeur' => null,
                'action' => CircuitCourrierEtape::ACTION_NOTIFIER,
                'mouvement' => CircuitCourrierEtape::MOUVEMENT_AUCUN,
                'notifie_roles' => ['dg', 'particulier_dg'],
                'instructions_aide' => 'Notifier le DG et la particulière.',
            ],
            [
                'ordre' => 3,
                'code' => 'instruction_dg',
                'nom' => 'Instruction hiérarchique',
                'acteur_type' => CircuitCourrierEtape::ACTEUR_DIRECTEUR_DESTINATAIRE,
                'acteur_valeur' => null,
                'action' => CircuitCourrierEtape::ACTION_INSTRUIRE,
                'mouvement' => CircuitCourrierEtape::MOUVEMENT_AUCUN,
                'notifie_roles' => ['particulier_dg'],
                'instructions_aide' => 'Le DG (ou le directeur de la structure destinataire, si le courrier lui est adressé) donne ses instructions.',
            ],
            [
                'ordre' => 4,
                'code' => 'traitement_particuliere',
                'nom' => 'Préparation de la réponse',
                'acteur_type' => CircuitCourrierEtape::ACTEUR_ROLE,
                'acteur_valeur' => 'particulier_dg',
                'action' => CircuitCourrierEtape::ACTION_TRAITER,
                'mouvement' => CircuitCourrierEtape::MOUVEMENT_CREER_DEPART,
                'notifie_roles' => [],
                'instructions_aide' => 'La particulière prépare le courrier de réponse (document) et le transmet au DG pour signature.',
            ],
            [
                'ordre' => 5,
                'code' => 'validation_reponse_dg',
                'nom' => 'Signature de la réponse par le DG',
                'acteur_type' => CircuitCourrierEtape::ACTEUR_DIRECTEUR_DESTINATAIRE,
                'acteur_valeur' => null,
                'action' => CircuitCourrierEtape::ACTION_VALIDER,
                'mouvement' => CircuitCourrierEtape::MOUVEMENT_AUCUN,
                'notifie_roles' => ['particulier_dg'],
                'instructions_aide' => 'Le DG signe le courrier de réponse (ou le rejette avec un motif). La particulière pourra ensuite l’expédier.',
            ],
            [
                'ordre' => 6,
                'code' => 'expedition_reponse',
                'nom' => 'Expédition de la réponse',
                'acteur_type' => CircuitCourrierEtape::ACTEUR_ROLE,
                'acteur_valeur' => 'particulier_dg',
                'action' => CircuitCourrierEtape::ACTION_TRAITER,
                'mouvement' => CircuitCourrierEtape::MOUVEMENT_AUCUN,
                'notifie_roles' => [],
                'instructions_aide' => 'La particulière expédie le courrier départ signé vers le secrétariat destinataire — le dossier arrivée sera clôturé.',
                'est_finale' => true,
            ],
        ]);

        TypeCourrier::updateOrCreate(
            ['code' => 'facture'],
            ['libelle' => 'Facture prestataire / fournisseur', 'actif' => true, 'circuit_courrier_id' => $facture->id]
        );

        TypeCourrier::updateOrCreate(
            ['code' => 'mad'],
            ['libelle' => 'Mise à disposition (MAD)', 'actif' => true, 'circuit_courrier_id' => $facture->id]
        );

        TypeCourrier::whereIn('code', ['administratif', 'invitation', 'reponse', 'autre', 'demande'])
            ->update(['circuit_courrier_id' => $general->id]);

        // Les factures utilisent le type dédié ; "demande" reste sur le circuit général par défaut.

        $this->command?->info('Circuits courrier A (facture) et B (général) configurés.');
    }

    /**
     * @param  list<array<string, mixed>>  $etapes
     */
    protected function syncEtapes(CircuitCourrier $circuit, array $etapes): void
    {
        $codes = [];
        foreach ($etapes as $data) {
            $codes[] = $data['code'];
            CircuitCourrierEtape::updateOrCreate(
                [
                    'circuit_courrier_id' => $circuit->id,
                    'code' => $data['code'],
                ],
                [
                    'ordre' => $data['ordre'],
                    'nom' => $data['nom'],
                    'acteur_type' => $data['acteur_type'],
                    'acteur_valeur' => $data['acteur_valeur'] ?? null,
                    'action' => $data['action'],
                    'mouvement' => $data['mouvement'] ?? CircuitCourrierEtape::MOUVEMENT_AUCUN,
                    'notifie_roles' => $data['notifie_roles'] ?? [],
                    'instructions_aide' => $data['instructions_aide'] ?? null,
                    'est_finale' => (bool) ($data['est_finale'] ?? false),
                    'actif' => true,
                ]
            );
        }

        CircuitCourrierEtape::query()
            ->where('circuit_courrier_id', $circuit->id)
            ->whereNotIn('code', $codes)
            ->update(['actif' => false]);
    }

    /**
     * Option A : la responsable dossiers ne transmet plus à l’AC.
     * Les dossiers encore bloqués sur l’ancienne étape passent à « AC établit le chèque ».
     */
    protected function reassignerCourriersEtapeObsoleteDossiersVersAc(CircuitCourrier $facture): void
    {
        $obsolete = CircuitCourrierEtape::query()
            ->where('circuit_courrier_id', $facture->id)
            ->where('code', 'traitement_dossiers_vers_ac')
            ->first();

        $ac = CircuitCourrierEtape::query()
            ->where('circuit_courrier_id', $facture->id)
            ->where('code', 'ac_etablit_cheque')
            ->where('actif', true)
            ->first();

        if (! $obsolete || ! $ac) {
            return;
        }

        $updated = Courrier::query()
            ->where('circuit_etape_actuelle_id', $obsolete->id)
            ->update([
                'circuit_etape_actuelle_id' => $ac->id,
                'circuit_etape_depuis' => now(),
            ]);

        if ($updated > 0) {
            $this->command?->info("{$updated} courrier(s) basculé(s) de « dossiers → AC » vers « AC établit le chèque ».");
        }
    }

    /**
     * Les étapes caissiers / retour caisse sont retirées : dossiers concernés → décharge AC.
     */
    protected function reassignerCourriersEtapesCaissiersVersDecharge(CircuitCourrier $facture): void
    {
        $cible = CircuitCourrierEtape::query()
            ->where('circuit_courrier_id', $facture->id)
            ->where('code', 'preuve_paiement')
            ->where('actif', true)
            ->first();

        if (! $cible) {
            return;
        }

        $obsoletes = CircuitCourrierEtape::query()
            ->where('circuit_courrier_id', $facture->id)
            ->whereIn('code', ['ac_vers_caissiers', 'retour_caisse_depenses'])
            ->pluck('id');

        if ($obsoletes->isEmpty()) {
            return;
        }

        $updated = Courrier::query()
            ->whereIn('circuit_etape_actuelle_id', $obsoletes->all())
            ->update([
                'circuit_etape_actuelle_id' => $cible->id,
                'circuit_etape_depuis' => now(),
            ]);

        if ($updated > 0) {
            $this->command?->info("{$updated} courrier(s) basculé(s) des étapes caissiers vers « enregistrement décharge AC ».");
        }
    }

    /**
     * Option A : le contrôle Eleni n’est plus une étape de circuit.
     * Les dossiers encore sur « cloture_depenses » sont considérés clôturés côté circuit
     * (la décharge AC a déjà été faite) — le contrôle reste possible hors circuit.
     */
    protected function cloturerCourriersBloquesSurControleEleni(CircuitCourrier $facture): void
    {
        $obsolete = CircuitCourrierEtape::query()
            ->where('circuit_courrier_id', $facture->id)
            ->where('code', 'cloture_depenses')
            ->first();

        if (! $obsolete) {
            return;
        }

        $updated = Courrier::query()
            ->where('circuit_etape_actuelle_id', $obsolete->id)
            ->update([
                'circuit_etape_actuelle_id' => null,
                'circuit_etape_depuis' => null,
            ]);

        if ($updated > 0) {
            $this->command?->info("{$updated} courrier(s) sorti(s) de l’étape « contrôle Eleni » (circuit clôturé — contrôle hors circuit).");
        }
    }

    protected function assurerRolesActeurs(): void
    {
        // Ne pas syncPermissions ici : cela écrasait documents.view / dossiers.view
        // déjà attribués par RoleAndPermissionSeeder.
        $permsBase = Permission::whereIn('name', [
            'documents.view', 'documents.create', 'documents.edit',
            'dossiers.view', 'dossiers.create', 'dossiers.edit',
            'courriers.view', 'courriers.create', 'courriers.edit', 'courriers.transmettre',
            'courriers.archiver', 'courriers.recevoir',
        ])->pluck('name');

        foreach ([
            'particulier_dg',
            'particulier_ac',
            'responsable_dossiers_prestataires',
            'responsable_suivi_depenses',
            'agent_comptable',
            'caissier',
        ] as $name) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            if ($permsBase->isNotEmpty()) {
                $role->givePermissionTo($permsBase);
            }
        }

        // Menus métier : Suivi paiements = Eleni (+ DG via RoleAndPermissionSeeder) ;
        // Bordereau = AC, Eleni, particulières circuit.
        Role::findByName('responsable_suivi_depenses', 'web')
            ?->givePermissionTo(['suivi-paiements.view', 'suivi-paiements.create', 'bordereau-transmission.view']);
        Role::findByName('agent_comptable', 'web')
            ?->givePermissionTo(['bordereau-transmission.view']);
        Role::findByName('caissier', 'web')
            ?->givePermissionTo(['bordereau-transmission.view']);
        Role::findByName('particulier_ac', 'web')
            ?->givePermissionTo(['bordereau-transmission.view']);
        Role::findByName('particulier_dg', 'web')
            ?->givePermissionTo(['suivi-paiements.view', 'suivi-factures.view', 'bordereau-transmission.view']);
        Role::findByName('responsable_dossiers_prestataires', 'web')
            ?->givePermissionTo(['suivi-factures.view']);
    }
}
