<?php

namespace App\Policies;

use App\Models\Courrier;
use App\Models\User;
use App\Services\CourrierSecretariatService;

class CourrierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('courriers.view')
            && (
                $user->aAccesTotal()
                || $user->gereCourrierSecretariat()
                || $user->peutSignerCourrierDepart()
                || $user->hasRole('particulier_dg')
                || $user->hasRole('particulier_ac')
                || $user->hasRole('responsable_dossiers_prestataires')
                || $user->hasRole('responsable_suivi_depenses')
                || $user->hasRole('agent_comptable')
                || $user->hasRole('caissier')
            );
    }

    public function view(User $user, Courrier $courrier): bool
    {
        if (! $user->can('courriers.view')) {
            return false;
        }

        if ($courrier->visiblePar($user)) {
            return true;
        }

        // Consultation depuis le détail dette (Taty / Eleni / DG) : factures + régularisations.
        return $user->can('moratoires.view') && $courrier->estTypeFacture();
    }

    public function create(User $user): bool
    {
        return $user->can('courriers.create') && $user->gereCourrierSecretariat();
    }

    public function update(User $user, Courrier $courrier): bool
    {
        if (! $user->can('courriers.edit')) {
            return false;
        }

        if ($user->hasRole('particulier_dg')
            || $user->hasRole('particulier_ac')
            || $user->hasRole('responsable_dossiers_prestataires')
            || $user->hasRole('responsable_suivi_depenses')
            || $user->hasRole('agent_comptable')
            || $user->hasRole('caissier')
            || $user->aAccesTotal()) {
            return true;
        }

        return $user->gereCourrierSecretariat();
    }

    public function corriger(User $user, Courrier $courrier): bool
    {
        if (! $user->can('courriers.edit')) {
            return false;
        }

        return $courrier->peutCorrigerEnregistrement($user);
    }

    public function orienter(User $user, Courrier $courrier): bool
    {
        return $user->can('courriers.orienter')
            && $courrier->estArrivee()
            && $courrier->statutCourrier?->code === 'en_parapheur';
    }

    public function ventiler(User $user, Courrier $courrier): bool
    {
        return $user->can('courriers.ventiler') && $courrier->estArrivee();
    }

    public function signer(User $user, Courrier $courrier): bool
    {
        return $user->can('courriers.signer')
            && $courrier->estDepart()
            && (int) $courrier->directeur_en_attente_id === (int) $user->id
            && $courrier->statutCourrier?->code === 'transmis_directeur';
    }

    public function rejeter(User $user, Courrier $courrier): bool
    {
        return $user->can('courriers.rejeter')
            && $courrier->estDepart()
            && (int) $courrier->directeur_en_attente_id === (int) $user->id
            && $courrier->statutCourrier?->code === 'transmis_directeur';
    }

    public function transmettre(User $user, Courrier $courrier): bool
    {
        return $user->can('courriers.transmettre')
            && $user->gereCourrierSecretariat()
            && $courrier->peutEnregistrerTransmission();
    }

    /**
     * Soumettre un brouillon départ au directeur de la direction.
     * Réservé aux acteurs secrétariat (pas le DG / directeur destinataire via aAccesTotal).
     */
    public function transmettreAuDirecteur(User $user, Courrier $courrier): bool
    {
        if (! $courrier->estDepart()) {
            return false;
        }

        if (! in_array($courrier->statutCourrier?->code, ['brouillon', 'rejete_directeur'], true)) {
            return false;
        }

        if (! $user->can('courriers.edit') || ! $this->estActeurSecretariatEmetteur($user)) {
            return false;
        }

        $structure = $courrier->structure ?? $user->structurePourValidationHierarchique();
        $directeur = app(CourrierSecretariatService::class)->directeurPourSecretariat($structure);

        if ($directeur && (int) $directeur->id === (int) $user->id) {
            return false;
        }

        return true;
    }

    /**
     * Après validation directeur : choisir le secrétariat destinataire et expédier.
     * Réservé au secrétariat émetteur (pas au DG via aAccesTotal).
     */
    public function expedierVersSecretariat(User $user, Courrier $courrier): bool
    {
        if (! $courrier->estDepart()) {
            return false;
        }

        if ($courrier->statutCourrier?->code !== 'signe') {
            return false;
        }

        return $user->can('courriers.edit') && $this->estActeurSecretariatEmetteur($user);
    }

    /** Secrétariat / particulière — hors accès total DG (transmission / expédition). */
    private function estActeurSecretariatEmetteur(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('secretaire_direction')
            || $user->hasRole('particulier_dg')
            || $user->hasRole('responsable_dossiers_prestataires')) {
            return true;
        }

        if ($user->aAccesTotal()) {
            return false;
        }

        return $user->gereCourrierSecretariat();
    }

    public function recevoir(User $user, Courrier $courrier): bool
    {
        return $user->can('courriers.recevoir')
            && $user->gereCourrierSecretariat()
            && $courrier->enAttenteReceptionInterne()
            && (int) $courrier->structure_destinataire_id === (int) $user->structure_id;
    }

    public function archiver(User $user, Courrier $courrier): bool
    {
        return $user->can('courriers.archiver')
            && ($user->gereCourrierSecretariat() || $user->peutSignerCourrierDepart())
            && $courrier->peutEtreArchive();
    }

    /**
     * Classement d’une facture dans un dossier fournisseur.
     * Réservé à la responsable dossiers prestataires (Mme Taty) — hors MAD / secrétaires / Eleni.
     */
    public function classerDossier(User $user, Courrier $courrier): bool
    {
        if (! $user->can('courriers.view') || ! $courrier->visiblePar($user)) {
            return false;
        }

        if (! $user->hasRole('responsable_dossiers_prestataires') && ! $user->hasRole('admin')) {
            return false;
        }

        return $courrier->typeCourrier?->code === 'facture';
    }

    public function annuler(User $user, Courrier $courrier): bool
    {
        return $courrier->peutAnnulerEnregistrement($user);
    }

    public function delete(User $user, Courrier $courrier): bool
    {
        if (! $user->can('courriers.delete')) {
            return false;
        }

        return $courrier->peutSupprimerEnregistrementPar($user);
    }
}
