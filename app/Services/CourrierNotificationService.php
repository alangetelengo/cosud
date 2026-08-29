<?php

namespace App\Services;

use App\Models\CosudSetting;
use App\Models\Courrier;
use App\Models\Structure;
use App\Models\User;
use App\Notifications\CourrierExpediteurTraiteNotification;
use App\Notifications\CourrierExpediteurValideNotification;
use App\Notifications\CourrierFournisseurRecouvrementNotification;
use App\Notifications\CourrierWorkflowNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

class CourrierNotificationService
{
    public const TRANSMISSION_DIRECTEUR = 'transmission_directeur';

    public const VALIDE_POUR_ENVOI = 'valide_pour_envoi';

    public const RENVOI_CORRECTION = 'renvoi_correction';

    public const ANNULATION = 'annulation';

    public const EXPEDITION = 'expedition';

    public const RECEPTION_REFUSEE = 'reception_refusee';

    public const ENREGISTREMENT_ARRIVEE = 'enregistrement_arrivee';

    public const MISE_EN_PARAPHEUR = 'mise_en_parapheur';

    public const ORIENTATION = 'orientation';

    public const DOSSIER_CONFIE = 'dossier_confie';

    public const INSTRUCTION_PARTICULIERE = 'instruction_particuliere';

    public const ETAPE_CIRCUIT = 'etape_circuit';

    public const REPONSE_A_VALIDER = 'reponse_a_valider';

    public const REPONSE_REJETEE = 'reponse_rejetee';

    public const REPONSE_VALIDEE_A_CREER = 'reponse_validee_a_creer';

    public const RETARD_TRAITEMENT = 'retard_traitement';

    public const RELANCE = 'relance';

    public const EXPEDITEUR_TRAITE = 'expediteur_traite';

    public const FOURNISSEUR_RECOUVREMENT = 'fournisseur_recouvrement';

    public const ENTREE_CHEQUE_SUIVI_DEPENSE = 'entree_cheque_suivi_depense';

    /** SMS + cloche : facture/MAD enregistrée — le DG doit donner le Bon pour accord. */
    public const FACTURE_ENREGISTREE_DG = 'facture_enregistree_dg';

    /** SMS + cloche : Bon pour accord DG — l’AC doit établir le chèque. */
    public const BON_POUR_ACCORD_AC = 'bon_pour_accord_ac';

    public function notifier(User $destinataire, Courrier $courrier, User $acteur, string $type, ?string $detail = null): void
    {
        if ((int) $destinataire->id === (int) $acteur->id) {
            return;
        }

        try {
            $destinataire->notify(new CourrierWorkflowNotification($courrier, $acteur, $type, $detail));
        } catch (\Throwable $e) {
            Log::channel('cosud')->error('Notification courrier échouée', [
                'destinataire_id' => $destinataire->id,
                'courrier_id' => $courrier->id,
                'type' => $type,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function notifierCreateur(Courrier $courrier, User $acteur, string $type, ?string $detail = null): void
    {
        $createur = $courrier->createur;
        if ($createur) {
            $this->notifier($createur, $courrier, $acteur, $type, $detail);
        }
    }

    public function notifierSecretariatStructure(?Structure $structure, Courrier $courrier, User $acteur, string $type, ?string $detail = null): void
    {
        $this->notifierCollection(
            app(CourrierSecretariatService::class)->secretairesPourStructure($structure),
            $courrier,
            $acteur,
            $type,
            $detail
        );
    }

    /**
     * @param  Collection<int, User>  $destinataires
     */
    public function notifierCollection(Collection $destinataires, Courrier $courrier, User $acteur, string $type, ?string $detail = null): void
    {
        foreach ($destinataires->unique('id') as $destinataire) {
            $this->notifier($destinataire, $courrier, $acteur, $type, $detail);
        }
    }

    /**
     * @param  list<string>  $roles
     */
    public function notifierRoles(array $roles, Courrier $courrier, User $acteur, string $type, ?string $detail = null): void
    {
        $roles = array_values(array_unique(array_filter($roles)));
        if ($roles === []) {
            return;
        }

        $rolesExistants = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $roles)
            ->pluck('name')
            ->all();

        if ($rolesExistants === []) {
            return;
        }

        $destinataires = User::query()
            ->role($rolesExistants)
            ->where('actif', true)
            ->get();

        $this->notifierCollection($destinataires, $courrier, $acteur, $type, $detail);
    }

    public function notifierEnregistrementArrivee(Courrier $courrier, User $acteur): void
    {
        if (! $courrier->estArrivee()) {
            return;
        }

        $this->notifierRoles(
            ['dg', 'particulier_dg'],
            $courrier,
            $acteur,
            self::ENREGISTREMENT_ARRIVEE,
            'Nouveau courrier arrivée enregistré au secrétariat DG.'
        );
    }

    /**
     * Informe le directeur (ou le DG) de la direction du secrétariat qui met le courrier en parapheur.
     */
    public function notifierMiseEnParapheur(Courrier $courrier, User $acteur): void
    {
        if (! $courrier->estArrivee()) {
            return;
        }

        $courrier->loadMissing('structure');
        $directeur = app(CourrierSecretariatService::class)
            ->directeurPourSecretariat($courrier->structure ?? $acteur->structure);

        if (! $directeur) {
            return;
        }

        $direction = ($courrier->structure ?? $acteur->structure)?->directionGestionCourrier();
        $detail = $direction
            ? 'Courrier placé en parapheur — en attente d’instructions ('.$direction->nom.').'
            : 'Courrier placé en parapheur — en attente d’instructions de la direction.';

        $this->notifier($directeur, $courrier, $acteur, self::MISE_EN_PARAPHEUR, $detail);
    }

    /**
     * @param  Collection<int, User>|iterable<int, User>  $destinataires
     */
    public function notifierOrientation(Courrier $courrier, User $acteur, iterable $destinataires, string $type = self::ORIENTATION, ?string $detail = null): void
    {
        $this->notifierCollection(
            collect($destinataires),
            $courrier,
            $acteur,
            $type,
            $detail ?? ($courrier->instructions_dg ?: null)
        );
    }

    /**
     * Informe l’expéditeur externe (e-mail / SMS) que son dossier a été validé
     * par la Direction (réponse signée ; expédition éventuellement encore en cours).
     */
    public function notifierExpediteurExterneValide(Courrier $courrier): void
    {
        $this->notifierExpediteurExterne($courrier, new CourrierExpediteurValideNotification($courrier));
    }

    /**
     * Informe l’expéditeur externe (e-mail / SMS) que son courrier arrivée a été clôturé.
     */
    public function notifierExpediteurExterneTraite(Courrier $courrier): void
    {
        $this->notifierExpediteurExterne($courrier, new CourrierExpediteurTraiteNotification($courrier));
    }

    /**
     * Informe le fournisseur / prestataire que le chèque est signé et que le recouvrement est possible.
     */
    public function notifierFournisseurRecouvrement(Courrier $courrier): void
    {
        $this->notifierExpediteurExterne($courrier, new CourrierFournisseurRecouvrementNotification($courrier));
    }

    protected function notifierExpediteurExterne(Courrier $courrier, object $notification): void
    {
        if (! $courrier->estArrivee() || ! $courrier->estOrigineExterne()) {
            return;
        }

        $email = trim((string) ($courrier->expediteur_email ?? ''));
        $telephone = trim((string) ($courrier->expediteur_telephone ?? ''));
        $whatsappOk = $telephone !== '' && app(WhatsAppService::class)->isConfigured();
        $smsOk = $telephone !== ''
            && app(SmsService::class)->isConfigured()
            && (! $whatsappOk || (bool) config('cosud.whatsapp.also_sms'));

        if ($email === '' && ! $whatsappOk && ! $smsOk) {
            return;
        }

        try {
            $onDemand = null;

            if ($email !== '') {
                $onDemand = Notification::route('mail', $email);
            }

            if ($whatsappOk) {
                $onDemand = $onDemand
                    ? $onDemand->route('cosud_whatsapp', $telephone)
                    : Notification::route('cosud_whatsapp', $telephone);
            }

            if ($smsOk) {
                $onDemand = $onDemand
                    ? $onDemand->route('cosud_sms', $telephone)
                    : Notification::route('cosud_sms', $telephone);
            }

            if ($onDemand === null) {
                return;
            }

            $onDemand->notify($notification);
        } catch (\Throwable $e) {
            Log::channel('cosud')->error('Notification expéditeur externe échouée', [
                'courrier_id' => $courrier->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function notifierEntreeChequeSuiviDepenses(Courrier $courrier, User $acteur, float $montant): void
    {
        $montantFormate = number_format($montant, 0, ',', ' ');
        $detail = 'Chèque établi par l’AC — montant : '.$montantFormate.' FCFA — à inscrire au suivi des dépenses.';

        $this->notifierRoles(
            ['responsable_suivi_depenses'],
            $courrier,
            $acteur,
            self::ENTREE_CHEQUE_SUIVI_DEPENSE,
            $detail
        );
    }

    /**
     * Facture / MAD (circuit facture_prestataire) : SMS + notif au DG pour traiter (Bon pour accord).
     */
    public function notifierFactureEnregistreeDg(Courrier $courrier, User $acteur): void
    {
        $courrier->loadMissing('circuit');

        if ($courrier->circuit?->code !== 'facture_prestataire') {
            return;
        }

        if (! CosudSetting::notifFactureEnregistreeDg()) {
            return;
        }

        $fournisseur = trim((string) ($courrier->expediteur_libelle ?? ''));
        $detail = $fournisseur !== ''
            ? 'Fournisseur / prestataire : '.$fournisseur
            : null;

        $this->notifierRoles(
            ['dg'],
            $courrier,
            $acteur,
            self::FACTURE_ENREGISTREE_DG,
            $detail
        );
    }

    /**
     * Après Bon pour accord DG : SMS + notif à l’AC pour éditer le chèque selon les instructions.
     */
    public function notifierBonPourAccordAc(Courrier $courrier, User $acteur): void
    {
        $courrier->loadMissing('circuit');

        if ($courrier->circuit?->code !== 'facture_prestataire') {
            return;
        }

        $fournisseur = trim((string) ($courrier->expediteur_libelle ?? ''));
        $instructions = trim((string) ($courrier->instructions_dg ?? ''));
        $parties = [];
        if ($fournisseur !== '') {
            $parties[] = 'Fournisseur : '.$fournisseur;
        }
        if ($instructions !== '') {
            $parties[] = 'Instructions DG : '.$instructions;
        }
        $detail = $parties !== [] ? implode(' | ', $parties) : null;

        $this->notifierRoles(
            ['agent_comptable'],
            $courrier,
            $acteur,
            self::BON_POUR_ACCORD_AC,
            $detail
        );
    }
}
