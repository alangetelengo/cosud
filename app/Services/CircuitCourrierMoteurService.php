<?php

namespace App\Services;

use App\Models\CircuitCourrier;
use App\Models\CircuitCourrierEtape;
use App\Models\CircuitCourrierHistorique;
use App\Models\Courrier;
use App\Models\Fonction;
use App\Models\JournalAudit;
use App\Models\TypeCourrier;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CircuitCourrierMoteurService
{
    public function __construct(
        private readonly CourrierNotificationService $notifications,
        private readonly CourrierSecretariatService $secretariat,
    ) {}

    public function demarrer(Courrier $courrier, ?CircuitCourrier $circuit = null, ?User $acteur = null): Courrier
    {
        $courrier->loadMissing('sensCourrier');
        $acteur ??= auth()->user();

        if ($circuit && ! $this->circuitCompatibleAvecSens($circuit, $courrier->sensCourrier?->code)) {
            $circuit = null;
        }

        $circuit ??= $this->resoudreCircuitPour($courrier);

        if (! $circuit) {
            if ($courrier->circuit_courrier_id) {
                $courrier->forceFill([
                    'circuit_courrier_id' => null,
                    'circuit_etape_actuelle_id' => null,
                    'circuit_etape_depuis' => null,
                ])->save();
            }

            if ($courrier->estArrivee() && $acteur) {
                $this->notifications->notifierEnregistrementArrivee($courrier, $acteur);
            }

            return $courrier->fresh(['circuit', 'circuitEtapeActuelle']);
        }

        $premiere = $circuit->premiereEtape();
        if (! $premiere) {
            throw new InvalidArgumentException('Le circuit « '.$circuit->libelle.' » n’a aucune étape active.');
        }

        return DB::transaction(function () use ($courrier, $circuit, $premiere, $acteur) {
            $courrier->circuit_courrier_id = $circuit->id;
            $courrier->circuit_etape_actuelle_id = $premiere->id;
            $courrier->circuit_etape_depuis = now();
            $courrier->save();

            $this->historiser($courrier, $premiere, $acteur, 'demarrage', 'Circuit démarré : '.$circuit->libelle);

            // Un circuit est attaché : c’est l’étape réellement atteinte qui notifie le bon
            // acteur (cf. notifierEtapeCourante ci-dessous) — pas de notification
            // « enregistrement » distincte, qui serait redondante pour le destinataire.
            // L’enregistrement lui-même est déjà fait à la création : on bascule à l’étape suivante.
            if ($premiere->action === CircuitCourrierEtape::ACTION_ENREGISTRER && $acteur) {
                $courrier = $this->avancer($courrier->fresh(['circuitEtapeActuelle']), $acteur, 'Enregistrement effectué');
            } else {
                $this->notifierEtapeCourante($courrier->fresh(['circuitEtapeActuelle']), $acteur);
            }

            return $courrier->fresh(['circuit', 'circuitEtapeActuelle', 'circuitHistoriques.etape', 'circuitHistoriques.user']);
        });
    }

    public function resoudreCircuitPour(Courrier $courrier): ?CircuitCourrier
    {
        $courrier->loadMissing('sensCourrier');
        $sensCode = $courrier->sensCourrier?->code;

        if ($courrier->circuit_courrier_id) {
            $circuit = CircuitCourrier::query()
                ->whereKey($courrier->circuit_courrier_id)
                ->where('actif', true)
                ->first();

            if ($circuit && $this->circuitCompatibleAvecSens($circuit, $sensCode)) {
                return $circuit;
            }
        }

        if ($courrier->type_courrier_id) {
            $type = TypeCourrier::with('circuit')->find($courrier->type_courrier_id);
            if ($type?->circuit?->actif && $this->circuitCompatibleAvecSens($type->circuit, $sensCode)) {
                return $type->circuit;
            }
        }

        return null;
    }

    public function circuitCompatibleAvecSens(CircuitCourrier $circuit, ?string $sensCode): bool
    {
        if ($sensCode === null || $sensCode === '') {
            return false;
        }

        return $circuit->sens_initial === $sensCode;
    }

    public function peutAgir(Courrier $courrier, User $user): bool
    {
        if ($user->aAccesTotal() || $user->hasRole('admin')) {
            return (bool) $courrier->circuit_etape_actuelle_id;
        }

        $etape = $courrier->circuitEtapeActuelle;
        if (! $etape || ! $etape->actif) {
            return false;
        }

        return $this->userCorrespondActeur($user, $etape, $courrier);
    }

    public function userCorrespondActeur(User $user, CircuitCourrierEtape $etape, ?Courrier $courrier = null): bool
    {
        if ($courrier?->agent_confie_id) {
            return (int) $user->id === (int) $courrier->agent_confie_id;
        }

        return $this->userCorrespondActeurParRole($user, $etape, $courrier);
    }

    /**
     * Correspondance acteur / étape selon le rôle ou le type d’étape, sans tenir compte
     * d’un éventuel agent confié sur le courrier.
     */
    public function userCorrespondActeurParRole(User $user, CircuitCourrierEtape $etape, ?Courrier $courrier = null): bool
    {
        return match ($etape->acteur_type) {
            CircuitCourrierEtape::ACTEUR_DG => $user->hasRole('dg') || $user->hasRole('directeur'),
            CircuitCourrierEtape::ACTEUR_DIRECTEUR_DESTINATAIRE => $courrier
                ? (int) $this->resoudreActeurDirecteur($courrier)?->id === (int) $user->id
                : $user->hasRole('dg') || $user->hasRole('directeur'),
            CircuitCourrierEtape::ACTEUR_SECRETARIAT => $user->gereCourrierSecretariat()
                || $user->hasRole('secretaire_direction')
                || $user->hasRole('particulier_dg')
                || $user->hasRole('responsable_dossiers_prestataires')
                || $user->hasRole('responsable_suivi_depenses'),
            CircuitCourrierEtape::ACTEUR_FONCTION => $this->userAFonction($user, (string) $etape->acteur_valeur),
            default => $etape->acteur_valeur
                ? $user->hasRole($etape->acteur_valeur)
                : false,
        };
    }

    /**
     * Résout le directeur habilité à instruire un courrier : celui de la structure
     * destinataire renseignée sur le courrier, avec repli sur le DG lorsque le
     * destinataire est la Direction Générale elle-même ou n’est pas renseigné.
     */
    public function resoudreActeurDirecteur(Courrier $courrier): ?User
    {
        $courrier->loadMissing('structureDestinataire');

        $directeur = $this->secretariat->directeurPourSecretariat($courrier->structureDestinataire);

        return $directeur ?? User::role('dg')->where('actif', true)->orderBy('id')->first();
    }

    /**
     * Libellé de l’acteur attendu sur une étape, résolu pour un courrier donné
     * (nom du directeur concerné pour les étapes de type « directeur destinataire »).
     */
    public function libelleActeurPour(Courrier $courrier, CircuitCourrierEtape $etape): string
    {
        if ($courrier->agent_confie_id) {
            $courrier->loadMissing('agentConfie');
            $nom = $courrier->agentConfie?->name;

            return $nom ? 'Agent confié — '.$nom : 'Agent confié';
        }

        if ($etape->acteur_type === CircuitCourrierEtape::ACTEUR_DIRECTEUR_DESTINATAIRE) {
            $directeur = $this->resoudreActeurDirecteur($courrier);

            return $directeur ? 'Directeur — '.$directeur->name : $etape->libelleActeur();
        }

        return $etape->libelleActeur();
    }

    protected function userAFonction(User $user, string $valeur): bool
    {
        if ($valeur === '') {
            return false;
        }

        $fonctionIds = Fonction::query()
            ->where('code', $valeur)
            ->orWhere('libelle', $valeur)
            ->pluck('id');

        if ($fonctionIds->isEmpty()) {
            return false;
        }

        return $user->structures()
            ->wherePivotIn('fonction_id', $fonctionIds->all())
            ->wherePivotNull('date_fin')
            ->exists();
    }

    public function avancer(Courrier $courrier, User $acteur, ?string $commentaire = null): Courrier
    {
        if (! $courrier->circuit_etape_actuelle_id) {
            throw new InvalidArgumentException('Aucun circuit actif sur ce courrier.');
        }

        if (! $this->peutAgir($courrier, $acteur)) {
            throw new InvalidArgumentException('Vous n’êtes pas autorisé à faire avancer cette étape.');
        }

        $courrier = $this->avancerUneEtape($courrier, $acteur, $commentaire);
        $courrier = $this->poursuivreEtapesAutomatiques($courrier, $acteur);

        // Une seule notification, sur l’étape réellement atteinte à l’issue de l’enchaînement
        // automatique — pas une par étape intermédiaire traversée (ex. notification auto-validée).
        $this->notifierEtapeCourante($courrier, $acteur);

        return $courrier;
    }

    /**
     * Enregistre les instructions de l’acteur sur l’étape courante (type « instruire »),
     * les conserve sur le courrier (`instructions_dg`) puis fait avancer le circuit.
     *
     * Si un agent est désigné (facultatif), il devient le prochain acteur (A2) :
     * on saute à la première étape suivante dont le rôle lui correspond, sinon on avance
     * d’une étape avec override `agent_confie_id` jusqu’à ce qu’il valide.
     */
    public function instruire(Courrier $courrier, User $acteur, string $instructions, ?int $agentConfieId = null): Courrier
    {
        $etape = $courrier->circuitEtapeActuelle;
        if (! $etape || $etape->action !== CircuitCourrierEtape::ACTION_INSTRUIRE) {
            throw new InvalidArgumentException('Aucune étape d’instruction en cours sur ce courrier.');
        }

        $agent = null;
        if ($agentConfieId !== null) {
            $agent = User::query()->where('actif', true)->find($agentConfieId);
            if (! $agent) {
                throw new InvalidArgumentException('L’agent confié est introuvable ou inactif.');
            }
        }

        $courrier->update([
            'instructions_dg' => $instructions,
            'date_orientation' => now(),
            'agent_confie_id' => $agent?->id,
        ]);

        $courrier = $courrier->fresh(['circuitEtapeActuelle', 'circuit.etapesActives', 'agentConfie']);
        $etape = $courrier->circuitEtapeActuelle;

        if ($agent && $etape) {
            $cible = $this->trouverProchaineEtapePourAgent($courrier, $agent, $etape);
            if ($cible && (int) $cible->id !== (int) $etape->id) {
                return $this->sauterVersEtapeApresInstruction($courrier, $acteur, $etape, $cible, $instructions);
            }
        }

        return $this->avancer($courrier, $acteur, $instructions);
    }

    /**
     * Première étape active d’ordre strictement supérieur à l’étape courante pour laquelle
     * l’agent correspond au rôle attendu (sans tenir compte de agent_confie_id).
     */
    protected function trouverProchaineEtapePourAgent(Courrier $courrier, User $agent, CircuitCourrierEtape $depuis): ?CircuitCourrierEtape
    {
        $courrier->loadMissing('circuit.etapesActives');

        return $courrier->circuit?->etapesActives
            ->filter(fn (CircuitCourrierEtape $e) => $e->ordre > $depuis->ordre)
            ->sortBy('ordre')
            ->first(fn (CircuitCourrierEtape $e) => $this->userCorrespondActeurParRole($agent, $e, $courrier));
    }

    protected function sauterVersEtapeApresInstruction(
        Courrier $courrier,
        User $acteur,
        CircuitCourrierEtape $depuis,
        CircuitCourrierEtape $cible,
        string $instructions,
    ): Courrier {
        return DB::transaction(function () use ($courrier, $acteur, $depuis, $cible, $instructions) {
            $this->historiser(
                $courrier,
                $depuis,
                $acteur,
                'avancement',
                $instructions
            );

            $courrier->circuit_etape_actuelle_id = $cible->id;
            $courrier->circuit_etape_depuis = now();
            $courrier->dernier_alerte_retard_at = null;
            $courrier->save();

            $this->historiser(
                $courrier,
                $cible,
                $acteur,
                'etape_suivante',
                'Confié à '.$courrier->agentConfie?->name.' — passage à : '.$cible->nom
            );

            JournalAudit::log('courrier.circuit.instruire', 'courriers', [
                'commentaire' => json_encode([
                    'courrier_id' => $courrier->id,
                    'etape' => $depuis->code,
                    'suivante' => $cible->code,
                    'agent_confie_id' => $courrier->agent_confie_id,
                ]),
            ]);

            $courrier = $courrier->fresh(['circuit', 'circuitEtapeActuelle', 'agentConfie']);
            $this->notifierEtapeCourante($courrier, $acteur);

            return $courrier;
        });
    }

    /**
     * L’AC envoie le chèque au DG (message + scan optionnel déjà attaché au courrier) :
     * enregistre le message et avance automatiquement vers la signature DG.
     */
    public function envoyerChequeAuDg(Courrier $courrier, User $acteur, string $message): Courrier
    {
        $etape = $courrier->circuitEtapeActuelle;
        if (! $etape || $etape->code !== 'ac_etablit_cheque') {
            throw new InvalidArgumentException('Aucune étape « AC établit le chèque » en cours sur ce courrier.');
        }

        if (! $this->peutAgir($courrier, $acteur)) {
            throw new InvalidArgumentException('Vous n’êtes pas autorisé à envoyer le chèque au DG.');
        }

        $courrier->update([
            'message_ac' => $message,
        ]);

        return $this->avancer(
            $courrier->fresh(['circuitEtapeActuelle', 'agentConfie']),
            $acteur,
            $message
        );
    }

    /**
     * Le DG enregistre le scan du chèque signé, notifie éventuellement le fournisseur
     * pour recouvrement, puis avance vers l’AC / caissiers.
     */
    public function signerChequeDg(Courrier $courrier, User $acteur, ?string $message = null, bool $notifierFournisseur = true): Courrier
    {
        $etape = $courrier->circuitEtapeActuelle;
        if (! $etape || $etape->code !== 'dg_signe_cheque') {
            throw new InvalidArgumentException('Aucune étape « DG signe le chèque » en cours sur ce courrier.');
        }

        if (! $this->peutAgir($courrier, $acteur)) {
            throw new InvalidArgumentException('Vous n’êtes pas autorisé à enregistrer la signature du chèque.');
        }

        $commentaire = $message ?: 'Chèque signé par le DG.';

        $courrier = $this->avancer(
            $courrier->fresh(['circuitEtapeActuelle', 'agentConfie']),
            $acteur,
            $commentaire
        );

        if ($notifierFournisseur) {
            $this->notifications->notifierFournisseurRecouvrement($courrier->fresh());
        }

        return $courrier;
    }

    /**
     * Dépôt de la preuve de paiement puis clôture automatique du dossier facture.
     */
    public function deposerPreuvePaiement(Courrier $courrier, User $acteur, ?string $message = null): Courrier
    {
        $etape = $courrier->circuitEtapeActuelle;
        if (! $etape || $etape->code !== 'preuve_paiement') {
            throw new InvalidArgumentException('Aucune étape « preuve de paiement » en cours sur ce courrier.');
        }

        if (! $this->peutAgir($courrier, $acteur)) {
            throw new InvalidArgumentException('Vous n’êtes pas autorisé à déposer la preuve de paiement.');
        }

        $commentaire = $message ?: 'Preuve de paiement enregistrée.';

        $courrier = $this->avancer(
            $courrier->fresh(['circuitEtapeActuelle', 'agentConfie']),
            $acteur,
            $commentaire
        );

        // Clôture automatique si l’étape suivante est la clôture finale.
        if ($courrier->circuitEtapeActuelle?->code === 'cloture_depenses'
            && $this->peutAgir($courrier, $acteur)) {
            $courrier = $this->avancer(
                $courrier,
                $acteur,
                'Clôture du dossier après preuve de paiement.'
            );
        }

        return $courrier;
    }

    /**
     * La particulière soumet un projet de réponse (document + destinataire proposé) à la
     * validation du DG : les champs de préparation sont enregistrés sur le courrier arrivée
     * puis le circuit avance vers l’étape « validation_reponse_dg ». Aucun courrier départ
     * n’est créé à ce stade — cf. `CourrierController::creerReponse()` après validation.
     *
     * @param  array{document_reponse_id: ?int, reponse_confidentielle: bool, reponse_structure_destinataire_id: ?int, destinataire_agent_id: ?int, reponse_objet: ?string}  $donnees
     */
    public function soumettreReponsePourValidation(Courrier $courrier, User $acteur, array $donnees): Courrier
    {
        $etape = $courrier->circuitEtapeActuelle;
        if (! $etape || $etape->code !== 'traitement_particuliere') {
            throw new InvalidArgumentException('Aucune étape de traitement en cours sur ce courrier.');
        }

        $courrier->update([
            'document_reponse_id' => $donnees['document_reponse_id'] ?? null,
            'reponse_confidentielle' => (bool) ($donnees['reponse_confidentielle'] ?? false),
            'reponse_structure_destinataire_id' => $donnees['reponse_structure_destinataire_id'] ?? null,
            'destinataire_agent_id' => $donnees['destinataire_agent_id'] ?? null,
            'reponse_objet' => $donnees['reponse_objet'] ?? null,
            'motif_rejet' => null,
            'rejete_par_id' => null,
            'date_rejet' => null,
        ]);

        return $this->avancer($courrier->fresh(['circuitEtapeActuelle']), $acteur, 'Projet de réponse soumis pour validation.');
    }

    /**
     * Le DG valide le projet de réponse et le renvoie à la particulière pour qu’elle
     * crée le courrier départ en brouillon (étape « creation_depart_particuliere »).
     */
    public function validerProjetVersParticuliere(Courrier $courrier, User $acteur, ?string $commentaire = null): Courrier
    {
        $etape = $courrier->circuitEtapeActuelle;
        if (! $etape || $etape->code !== 'validation_reponse_dg') {
            throw new InvalidArgumentException('Aucune validation de réponse en cours sur ce courrier.');
        }

        if (! $this->peutAgir($courrier, $acteur)) {
            throw new InvalidArgumentException('Vous n’êtes pas autorisé à valider cette réponse.');
        }

        if (! $courrier->document_reponse_id) {
            throw new InvalidArgumentException('Aucun projet de réponse n’est attaché à ce courrier.');
        }

        $courrier = $this->avancer(
            $courrier->fresh(['circuitEtapeActuelle']),
            $acteur,
            $commentaire ?: 'Projet de réponse validé — à créer en courrier départ par la particulière.'
        );

        return $courrier->fresh(['circuit', 'circuitEtapeActuelle']);
    }

    /**
     * Le DG rejette le projet de réponse soumis : retour à l’étape « traitement_particuliere »
     * avec le motif conservé (affiché à la particulière), le document proposé étant effacé
     * pour qu’elle en soumette un nouveau.
     */
    public function rejeterReponse(Courrier $courrier, User $acteur, string $motif): Courrier
    {
        $etape = $courrier->circuitEtapeActuelle;
        if (! $etape || $etape->code !== 'validation_reponse_dg') {
            throw new InvalidArgumentException('Aucune validation de réponse en cours sur ce courrier.');
        }

        if (! $this->peutAgir($courrier, $acteur)) {
            throw new InvalidArgumentException('Vous n’êtes pas autorisé à rejeter cette réponse.');
        }

        $cible = CircuitCourrierEtape::query()
            ->where('circuit_courrier_id', $etape->circuit_courrier_id)
            ->where('code', 'traitement_particuliere')
            ->where('actif', true)
            ->first();

        if (! $cible) {
            throw new InvalidArgumentException('Étape « traitement_particuliere » introuvable sur ce circuit.');
        }

        return DB::transaction(function () use ($courrier, $etape, $cible, $acteur, $motif) {
            $this->historiser($courrier, $etape, $acteur, 'rejet', 'Réponse rejetée : '.$motif);

            $courrier->circuit_etape_actuelle_id = $cible->id;
            $courrier->circuit_etape_depuis = now();
            $courrier->document_reponse_id = null;
            $courrier->motif_rejet = $motif;
            $courrier->rejete_par_id = $acteur->id;
            $courrier->date_rejet = now();
            $courrier->save();

            $this->historiser($courrier, $cible, $acteur, 'etape_suivante', 'Retour à : '.$cible->nom);

            $this->notifications->notifierRoles(
                ['particulier_dg'],
                $courrier->fresh(),
                $acteur,
                CourrierNotificationService::REPONSE_REJETEE,
                $motif
            );

            return $courrier->fresh(['circuit', 'circuitEtapeActuelle']);
        });
    }

    /**
     * Clôture le circuit arrivée après qu’un DG a créé et signé lui-même une réponse
     * (bypass du reste du circuit métier — facture ou général).
     */
    public function terminerApresReponseDirecte(Courrier $courrier, User $acteur, ?string $commentaire = null): Courrier
    {
        $etape = $courrier->circuitEtapeActuelle;
        if (! $etape) {
            return $courrier->fresh(['circuit', 'circuitEtapeActuelle']);
        }

        return DB::transaction(function () use ($courrier, $etape, $acteur, $commentaire) {
            $this->historiser(
                $courrier,
                $etape,
                $acteur,
                'cloture_circuit',
                $commentaire ?: 'Circuit clôturé : réponse signée directement par le DG.'
            );

            $courrier->circuit_etape_actuelle_id = null;
            $courrier->circuit_etape_depuis = null;
            $courrier->save();

            return $courrier->fresh(['circuit', 'circuitEtapeActuelle']);
        });
    }

    /**
     * Fait avancer automatiquement le circuit après la création d’un courrier réponse :
     * complète l’étape courante puis, si elle mène directement à la création d’un départ,
     * l’étape suivante qui matérialise cette transmission — sans validation manuelle séparée.
     * N’échoue pas silencieusement si l’acteur n’est pas habilité : le circuit reste alors inchangé.
     */
    public function completerApresCreationDepart(Courrier $courrier, User $acteur, ?string $commentaire = null): Courrier
    {
        while ($courrier->circuit_etape_actuelle_id) {
            $etape = $courrier->circuitEtapeActuelle;
            if (! $etape || ! $this->peutAgir($courrier, $acteur)) {
                break;
            }

            $etaitCreerDepart = $etape->mouvement === CircuitCourrierEtape::MOUVEMENT_CREER_DEPART;

            try {
                $courrier = $this->avancer($courrier, $acteur, $commentaire ?: ('Courrier réponse créé — '.$etape->nom));
            } catch (InvalidArgumentException) {
                break;
            }

            if ($etaitCreerDepart) {
                break;
            }
        }

        return $courrier->fresh(['circuit', 'circuitEtapeActuelle']);
    }

    protected function avancerUneEtape(Courrier $courrier, User $acteur, ?string $commentaire): Courrier
    {
        $etape = $courrier->circuitEtapeActuelle;
        if (! $etape) {
            throw new InvalidArgumentException('Étape courante introuvable.');
        }

        return DB::transaction(function () use ($courrier, $acteur, $commentaire, $etape) {
            $this->historiser(
                $courrier,
                $etape,
                $acteur,
                'avancement',
                $commentaire ?: ('Étape validée : '.$etape->nom)
            );

            // L’agent confié est désigné à la sortie de l’étape d’instruction : on ne
            // l’efface qu’après qu’il a traité « son » étape (pas au moment de l’instruction).
            if ($etape->action !== CircuitCourrierEtape::ACTION_INSTRUIRE) {
                $courrier->agent_confie_id = null;
            }

            if ($etape->est_finale) {
                $courrier->circuit_etape_actuelle_id = null;
                $courrier->circuit_etape_depuis = null;
                $courrier->save();
                $this->historiser($courrier, $etape, $acteur, 'cloture_circuit', 'Circuit terminé');

                return $courrier->fresh(['circuit', 'circuitEtapeActuelle', 'circuitHistoriques.etape', 'circuitHistoriques.user']);
            }

            $suivante = $etape->etapeSuivante();
            $courrier->circuit_etape_actuelle_id = $suivante?->id;
            $courrier->circuit_etape_depuis = $suivante ? now() : null;
            $courrier->dernier_alerte_retard_at = null;
            $courrier->save();

            if ($suivante) {
                $this->historiser($courrier, $suivante, $acteur, 'etape_suivante', 'Passage à : '.$suivante->nom);
            } else {
                $this->historiser($courrier, $etape, $acteur, 'cloture_circuit', 'Circuit terminé (plus d’étape suivante)');
            }

            JournalAudit::log('courrier.circuit.avancer', 'courriers', [
                'commentaire' => json_encode([
                    'courrier_id' => $courrier->id,
                    'etape' => $etape->code,
                    'suivante' => $suivante?->code,
                ]),
            ]);

            return $courrier->fresh(['circuit', 'circuitEtapeActuelle', 'circuitHistoriques.etape', 'circuitHistoriques.user']);
        });
    }

    /**
     * Enchaîne automatiquement les étapes de simple notification (aucune décision humaine
     * requise) jusqu’à la prochaine étape qui exige une véritable action d’un acteur.
     */
    protected function poursuivreEtapesAutomatiques(Courrier $courrier, User $acteur): Courrier
    {
        $etape = $courrier->circuitEtapeActuelle;

        while ($etape && $etape->action === CircuitCourrierEtape::ACTION_NOTIFIER) {
            $courrier = $this->avancerUneEtape($courrier->fresh(['circuitEtapeActuelle']), $acteur, 'Notification automatique : '.$etape->nom);
            $etape = $courrier->circuitEtapeActuelle;
        }

        return $courrier->fresh(['circuit', 'circuitEtapeActuelle', 'circuitHistoriques.etape', 'circuitHistoriques.user']);
    }

    public function notifierEtapeCourante(Courrier $courrier, User $acteur): void
    {
        $etape = $courrier->circuitEtapeActuelle;
        if (! $etape) {
            return;
        }

        $detail = 'Étape en cours : '.$etape->nom.($etape->instructions_aide ? ' — '.$etape->instructions_aide : '');
        if ($courrier->instructions_dg) {
            $detail .= ' | Instructions : '.$courrier->instructions_dg;
        }

        $courrier->loadMissing('agentConfie');
        if ($courrier->agentConfie) {
            $this->notifications->notifier(
                $courrier->agentConfie,
                $courrier,
                $acteur,
                CourrierNotificationService::DOSSIER_CONFIE,
                $detail
            );

            if (! $courrier->est_confidentiel) {
                $this->notifications->notifierRoles(
                    ['particulier_dg', 'particulier_ac'],
                    $courrier,
                    $acteur,
                    CourrierNotificationService::ETAPE_CIRCUIT,
                    $detail
                );
            }

            return;
        }

        if ($etape->acteur_type === CircuitCourrierEtape::ACTEUR_DIRECTEUR_DESTINATAIRE) {
            $type = $etape->code === 'validation_reponse_dg'
                ? CourrierNotificationService::REPONSE_A_VALIDER
                : CourrierNotificationService::ETAPE_CIRCUIT;

            $directeur = $this->resoudreActeurDirecteur($courrier);
            if ($directeur) {
                $this->notifications->notifier($directeur, $courrier, $acteur, $type, $detail);
            }

            $rolesSupplementaires = $etape->notifie_roles ?? [];
            if (! $courrier->est_confidentiel) {
                $rolesSupplementaires = array_merge($rolesSupplementaires, ['particulier_dg', 'particulier_ac']);
            }
            if ($rolesSupplementaires !== []) {
                $this->notifications->notifierRoles($rolesSupplementaires, $courrier, $acteur, CourrierNotificationService::ETAPE_CIRCUIT, $detail);
            }

            return;
        }

        $roles = $etape->notifie_roles ?? [];
        if ($etape->acteur_type === CircuitCourrierEtape::ACTEUR_ROLE && $etape->acteur_valeur) {
            $roles[] = $etape->acteur_valeur;
        }
        if ($etape->acteur_type === CircuitCourrierEtape::ACTEUR_DG) {
            $roles[] = 'dg';
        }
        if ($etape->acteur_type === CircuitCourrierEtape::ACTEUR_SECRETARIAT) {
            $roles = array_merge($roles, ['secretaire_direction', 'particulier_dg']);
        }

        $type = $etape->code === 'creation_depart_particuliere'
            ? CourrierNotificationService::REPONSE_VALIDEE_A_CREER
            : CourrierNotificationService::ETAPE_CIRCUIT;

        if (! $courrier->est_confidentiel) {
            $roles = array_merge($roles, ['particulier_dg', 'particulier_ac']);
        }

        $this->notifications->notifierRoles($roles, $courrier, $acteur, $type, $detail);
    }

    public function etapesPourAffichage(Courrier $courrier): array
    {
        if (! $courrier->circuit_courrier_id) {
            return [];
        }

        $courrier->loadMissing(['circuit.etapesActives', 'circuitEtapeActuelle']);
        $actuelleId = $courrier->circuit_etape_actuelle_id;
        $actuelleOrdre = $courrier->circuitEtapeActuelle?->ordre;

        return $courrier->circuit->etapesActives->map(function (CircuitCourrierEtape $etape) use ($actuelleId, $actuelleOrdre) {
            $statut = 'a_venir';
            if ($actuelleId === null && $actuelleOrdre === null) {
                $statut = 'terminee';
            } elseif ((int) $etape->id === (int) $actuelleId) {
                $statut = 'en_cours';
            } elseif ($actuelleOrdre !== null && $etape->ordre < $actuelleOrdre) {
                $statut = 'terminee';
            }

            return [
                'etape' => $etape,
                'statut' => $statut,
            ];
        })->all();
    }

    /**
     * @return list<array{evenement: string, libelle: string, commentaire: ?string, user: ?string, etape: ?string, date: Carbon}>
     */
    public function historiquePourAffichage(Courrier $courrier): array
    {
        $courrier->loadMissing(['circuitHistoriques.etape', 'circuitHistoriques.user']);

        $labels = [
            'demarrage' => 'Démarrage du circuit',
            'avancement' => 'Validation d’étape',
            'etape_suivante' => 'Passage à l’étape suivante',
            'cloture_circuit' => 'Clôture du circuit',
            'rejet' => 'Rejet de la réponse',
            'relance' => 'Relance DG',
            'alerte_retard' => 'Alerte retard',
        ];

        return $courrier->circuitHistoriques
            ->sortByDesc('created_at')
            ->values()
            ->map(fn (CircuitCourrierHistorique $h) => [
                'evenement' => $h->evenement,
                'libelle' => $labels[$h->evenement] ?? ucfirst(str_replace('_', ' ', $h->evenement)),
                'commentaire' => $h->commentaire,
                'user' => $h->user?->name,
                'etape' => $h->etape?->nom,
                'date' => $h->created_at,
            ])
            ->all();
    }

    protected function historiser(
        Courrier $courrier,
        ?CircuitCourrierEtape $etape,
        ?User $user,
        string $evenement,
        ?string $commentaire = null
    ): void {
        if (! $user) {
            return;
        }

        CircuitCourrierHistorique::create([
            'courrier_id' => $courrier->id,
            'circuit_courrier_etape_id' => $etape?->id,
            'user_id' => $user->id,
            'evenement' => $evenement,
            'commentaire' => $commentaire,
        ]);
    }
}
