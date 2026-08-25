<?php

namespace App\Services;

use App\Models\CircuitCourrier;
use App\Models\CircuitCourrierEtape;
use App\Models\CircuitCourrierHistorique;
use App\Models\Courrier;
use App\Models\Fonction;
use App\Models\JournalAudit;
use App\Models\SuiviPaiement;
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
        private readonly SuiviPaiementService $suiviPaiements,
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

            $courrier = $courrier->fresh(['circuit', 'circuitEtapeActuelle', 'circuitHistoriques.etape', 'circuitHistoriques.user']);
            if ($acteur) {
                $this->notifications->notifierFactureEnregistreeDg($courrier, $acteur);
            }

            return $courrier;
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
        if ($courrier && $this->idsAgentsConfies($courrier) !== []) {
            return in_array((int) $user->id, $this->idsAgentsConfies($courrier), true);
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
        $libelles = $courrier->libellesAgentsConfies();
        if ($libelles !== []) {
            return 'Confié à — '.implode(', ', $libelles);
        }

        if ($etape->acteur_type === CircuitCourrierEtape::ACTEUR_DIRECTEUR_DESTINATAIRE) {
            $directeur = $this->resoudreActeurDirecteur($courrier);

            return $directeur
                ? 'Directeur — '.$directeur->libelleDestinataireCourrier()
                : $etape->libelleActeur();
        }

        return $etape->libelleActeur();
    }

    /**
     * @return list<int>
     */
    public function idsAgentsConfies(Courrier $courrier): array
    {
        $courrier->loadMissing('agentsConfies');

        $ids = $courrier->agentsConfies->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($courrier->agent_confie_id) {
            $ids[] = (int) $courrier->agent_confie_id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<int>  $agentConfieIds
     */
    public function synchroniserAgentsConfies(Courrier $courrier, array $agentConfieIds): void
    {
        $ids = collect($agentConfieIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $courrier->agentsConfies()->sync($ids);
        $courrier->forceFill([
            'agent_confie_id' => $ids[0] ?? null,
        ])->save();
    }

    public function viderAgentsConfies(Courrier $courrier): void
    {
        $courrier->agentsConfies()->detach();
        if ($courrier->agent_confie_id !== null) {
            $courrier->forceFill(['agent_confie_id' => null])->save();
        }
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
        $courrier = $this->avancerEtapesSansNotifier($courrier, $acteur, $commentaire);

        // Une seule notification, sur l’étape réellement atteinte à l’issue de l’enchaînement
        // automatique — pas une par étape intermédiaire traversée (ex. notification auto-validée).
        $this->notifierEtapeCourante($courrier, $acteur);

        return $courrier;
    }

    /**
     * Avance le circuit (y compris étapes automatiques) sans envoyer de notification.
     */
    protected function avancerEtapesSansNotifier(Courrier $courrier, User $acteur, ?string $commentaire = null): Courrier
    {
        if (! $courrier->circuit_etape_actuelle_id) {
            throw new InvalidArgumentException('Aucun circuit actif sur ce courrier.');
        }

        if (! $this->peutAgir($courrier, $acteur)) {
            throw new InvalidArgumentException('Vous n’êtes pas autorisé à faire avancer cette étape.');
        }

        $this->assurerDechargeAvantCloturePreuvePaiement($courrier);

        $courrier = $this->avancerUneEtape($courrier, $acteur, $commentaire);

        return $this->poursuivreEtapesAutomatiques($courrier, $acteur);
    }

    /**
     * L’étape finale « preuve_paiement » ne peut être clôturée que via le formulaire de décharge AC
     * (date_decharge déjà enregistrée), jamais via « Valider l’étape » générique.
     */
    protected function assurerDechargeAvantCloturePreuvePaiement(Courrier $courrier): void
    {
        $courrier->loadMissing('circuitEtapeActuelle');
        if ($courrier->circuitEtapeActuelle?->code !== 'preuve_paiement') {
            return;
        }

        $aDecharge = SuiviPaiement::query()
            ->where('courrier_id', $courrier->id)
            ->whereNotNull('date_decharge')
            ->exists();

        if (! $aDecharge) {
            throw new InvalidArgumentException(
                'La décharge bénéficiaire doit être enregistrée via le formulaire dédié (Actions) avant de clôturer le circuit.'
            );
        }
    }

    /**
     * Enregistre les instructions de l’acteur sur l’étape courante (type « instruire »),
     * les conserve sur le courrier (`instructions_dg`) puis fait avancer le circuit.
     *
     * Si un ou plusieurs agents sont désignés (facultatif), ils deviennent les destinataires
     * du dossier : notification à chacun, et le premier agent dont le rôle correspond à une
     * étape ultérieure déclenche le saut (sinon avance d’une étape avec override multi).
     *
     * @param  list<int>|null  $agentConfieIds
     */
    public function instruire(
        Courrier $courrier,
        User $acteur,
        string $instructions,
        ?int $agentConfieId = null,
        ?array $agentConfieIds = null,
        ?int $delaiExecutionJours = null,
    ): Courrier {
        $etape = $courrier->circuitEtapeActuelle;
        if (! $etape || $etape->action !== CircuitCourrierEtape::ACTION_INSTRUIRE) {
            throw new InvalidArgumentException('Aucune étape d’instruction en cours sur ce courrier.');
        }

        $ids = collect($agentConfieIds ?? [])
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->when($agentConfieId !== null, fn ($c) => $c->prepend((int) $agentConfieId))
            ->unique()
            ->values();

        $agents = $ids->isEmpty()
            ? collect()
            : User::query()->where('actif', true)->whereIn('id', $ids->all())->get();

        if ($ids->isNotEmpty() && $agents->count() !== $ids->count()) {
            throw new InvalidArgumentException('Un ou plusieurs destinataires sont introuvables ou inactifs.');
        }

        $courrier->update([
            'instructions_dg' => $instructions,
            'delai_execution_jours' => $delaiExecutionJours,
            'date_orientation' => now(),
        ]);
        $this->synchroniserAgentsConfies($courrier, $agents->pluck('id')->all());

        $courrier = $courrier->fresh(['circuitEtapeActuelle', 'circuit.etapesActives', 'agentConfie', 'agentsConfies']);
        $etape = $courrier->circuitEtapeActuelle;

        if ($agents->isNotEmpty() && $etape) {
            $cible = null;
            $agentPourSaut = null;
            foreach ($agents as $agent) {
                $candidate = $this->trouverProchaineEtapePourAgent($courrier, $agent, $etape);
                if ($candidate && ($cible === null || $candidate->ordre < $cible->ordre)) {
                    $cible = $candidate;
                    $agentPourSaut = $agent;
                }
            }

            if ($cible && $agentPourSaut && (int) $cible->id !== (int) $etape->id) {
                $courrier = $this->sauterVersEtapeApresInstruction($courrier, $acteur, $etape, $cible, $instructions);
            } else {
                $courrier = $this->avancer($courrier, $acteur, $instructions);
            }
        } else {
            $courrier = $this->avancer($courrier, $acteur, $instructions);
        }

        $this->notifierSuiviParalleleDossiersPrestataires($courrier, $acteur);
        $this->notifications->notifierBonPourAccordAc($courrier, $acteur);

        return $courrier;
    }

    /**
     * Après Bon pour accord DG : informer la responsable dossiers (suivi parallèle,
     * sans étape de transmission vers l’AC).
     */
    protected function notifierSuiviParalleleDossiersPrestataires(Courrier $courrier, User $acteur): void
    {
        $courrier->loadMissing('circuit');

        if ($courrier->circuit?->code !== 'facture_prestataire') {
            return;
        }

        $detail = 'Bon pour accord DG — classer la facture dans le dossier fournisseur et suivre le paiement.'
            .($courrier->instructions_dg ? ' | Instructions : '.$courrier->instructions_dg : '');

        $this->notifications->notifierRoles(
            ['responsable_dossiers_prestataires'],
            $courrier,
            $acteur,
            CourrierNotificationService::ETAPE_CIRCUIT,
            $detail
        );
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
                'Confié à '.implode(', ', $courrier->libellesAgentsConfies()).' — passage à : '.$cible->nom
            );

            JournalAudit::log('courrier.circuit.instruire', 'courriers', [
                'commentaire' => json_encode([
                    'courrier_id' => $courrier->id,
                    'etape' => $depuis->code,
                    'suivante' => $cible->code,
                    'agent_confie_id' => $courrier->agent_confie_id,
                    'agent_confie_ids' => $this->idsAgentsConfies($courrier),
                ]),
            ]);

            $courrier = $courrier->fresh(['circuit', 'circuitEtapeActuelle', 'agentConfie', 'agentsConfies']);
            $this->notifierEtapeCourante($courrier, $acteur);

            return $courrier;
        });
    }

    /**
     * L’AC envoie le chèque au DG (message + références bordereau) :
     * enregistre le message, crée la fiche FSP et notifie la responsable suivi dépenses,
     * puis avance automatiquement vers la signature DG.
     *
     * @param  array{
     *     numero_piece: string,
     *     banque: string,
     *     beneficiaire_libelle: string,
     *     programmation?: ?string
     * }  $references
     */
    public function envoyerChequeAuDg(Courrier $courrier, User $acteur, string $message, float $montant, array $references): Courrier
    {
        $etape = $courrier->circuitEtapeActuelle;
        if (! $etape || $etape->code !== 'ac_etablit_cheque') {
            throw new InvalidArgumentException('Aucune étape « AC établit le chèque » en cours sur ce courrier.');
        }

        if (! $this->peutAgir($courrier, $acteur)) {
            throw new InvalidArgumentException('Vous n’êtes pas autorisé à envoyer le chèque au DG.');
        }

        $courrier = DB::transaction(function () use ($courrier, $acteur, $message, $montant, $references): Courrier {
            $courrier->update([
                'message_ac' => $message,
            ]);

            $this->suiviPaiements->creerDepuisEntreeCheque($courrier->fresh(), $acteur, $montant, $references);

            return $this->avancerEtapesSansNotifier(
                $courrier->fresh(['circuitEtapeActuelle', 'agentConfie']),
                $acteur,
                $message
            );
        });

        $this->notifierEtapeCourante($courrier, $acteur);
        $this->notifications->notifierEntreeChequeSuiviDepenses($courrier, $acteur, $montant);

        return $courrier;
    }

    /**
     * Le DG confirme la signature du chèque (sans scan dans COSUD), notifie éventuellement
     * le fournisseur pour recouvrement, puis renvoie le dossier à l’AC pour la décharge.
     */
    public function signerChequeDg(Courrier $courrier, User $acteur, ?string $message = null, bool $notifierFournisseur = true): Courrier
    {
        $etape = $courrier->circuitEtapeActuelle;
        if (! $etape || $etape->code !== 'dg_signe_cheque') {
            throw new InvalidArgumentException('Aucune étape « DG signe le chèque » en cours sur ce courrier.');
        }

        if (! $this->peutAgir($courrier, $acteur)) {
            throw new InvalidArgumentException('Vous n’êtes pas autorisé à confirmer la signature du chèque.');
        }

        $commentaire = $message ?: 'Chèque signé par le DG — dossier renvoyé à l’AC pour décharge bénéficiaire.';

        $courrier = $this->avancer(
            $courrier->fresh(['circuitEtapeActuelle', 'agentConfie']),
            $acteur,
            $commentaire
        );

        // L’étape suivante (décharge AC) est nominative par rôle : ne pas bloquer sur d’anciens destinataires confiés.
        $this->viderAgentsConfies($courrier);

        if ($notifierFournisseur) {
            $this->notifications->notifierFournisseurRecouvrement($courrier->fresh());
        }

        return $courrier->fresh(['circuitEtapeActuelle', 'agentConfie', 'agentsConfies']);
    }

    /**
     * L’AC enregistre la décharge bénéficiaire (date + pièces + observation),
     * sans modifier les références du chèque déjà saisies à l’envoi DG.
     * Cette action clôture le circuit ; le contrôle Eleni se fait ensuite hors circuit.
     *
     * @param  array{
     *     date_decharge: string,
     *     observation?: ?string
     * }  $bordereau
     */
    public function enregistrerDechargeAc(Courrier $courrier, User $acteur, array $bordereau, ?string $message = null): Courrier
    {
        $etape = $courrier->circuitEtapeActuelle;
        if (! $etape || $etape->code !== 'preuve_paiement') {
            throw new InvalidArgumentException('Aucune étape d’enregistrement de décharge en cours sur ce courrier.');
        }

        if (! $this->peutAgir($courrier, $acteur)) {
            throw new InvalidArgumentException('Vous n’êtes pas autorisé à enregistrer la décharge / le paiement.');
        }

        $commentaire = $message ?: 'Décharge bénéficiaire enregistrée (bordereau + pièces) — circuit clôturé.';

        $courrier = DB::transaction(function () use ($courrier, $acteur, $bordereau, $commentaire): Courrier {
            $this->suiviPaiements->enregistrerDechargeBordereau($courrier, $bordereau);

            $avance = $this->avancerEtapesSansNotifier(
                $courrier->fresh(['circuitEtapeActuelle', 'agentConfie', 'suiviPaiement']),
                $acteur,
                $commentaire
            );

            $this->viderAgentsConfies($avance);

            return $avance->fresh(['circuitEtapeActuelle', 'agentConfie', 'agentsConfies', 'suiviPaiement']);
        });

        $this->notifications->notifierRoles(
            ['responsable_suivi_depenses'],
            $courrier,
            $acteur,
            CourrierNotificationService::ETAPE_CIRCUIT,
            'Décharge enregistrée — contrôler les pièces physiques et joindre les pièces manquantes si besoin (hors circuit).'
        );

        return $courrier;
    }

    /**
     * Mme Eleni confirme le contrôle des pièces physiques (hors circuit).
     * N’avance / ne clôture pas le circuit — la clôture a déjà eu lieu à la décharge AC.
     */
    public function confirmerControleDepense(Courrier $courrier, User $acteur, ?string $message = null): Courrier
    {
        if (! $this->peutConfirmerControleDepenseHorsCircuit($acteur, $courrier)) {
            throw new InvalidArgumentException('Vous n’êtes pas autorisé à confirmer le contrôle de cette dépense, ou le contrôle a déjà été effectué.');
        }

        $this->suiviPaiements->marquerControleEffectue($courrier, $acteur);

        $commentaire = $message ?: 'Contrôle des pièces physiques confirmé.';

        $this->historiser(
            $courrier->fresh(),
            null,
            $acteur,
            'controle_depense',
            $commentaire
        );

        return $courrier->fresh(['circuitEtapeActuelle', 'agentConfie', 'agentsConfies', 'suiviPaiement']);
    }

    /**
     * Contrôle Eleni après décharge AC (circuit déjà terminé).
     */
    public function peutConfirmerControleDepenseHorsCircuit(User $user, Courrier $courrier): bool
    {
        if (! $user->can('courriers.view')) {
            return false;
        }

        if (! $user->aAccesTotal()
            && ! $user->hasRole('responsable_suivi_depenses')
            && ! $user->hasRole('admin')) {
            return false;
        }

        if (! $user->aAccesTotal() && ! $courrier->visiblePar($user)) {
            return false;
        }

        $courrier->loadMissing('suiviPaiement');
        $suivi = $courrier->suiviPaiement;

        return $suivi
            && $courrier->circuit_etape_actuelle_id === null
            && $suivi->date_decharge !== null
            && $suivi->controle_at === null;
    }

    /**
     * @deprecated Conservé pour les tests historiques — préférer enregistrerDechargeAc.
     */
    public function deposerPreuvePaiement(Courrier $courrier, User $acteur, ?string $message = null, ?string $observation = null): Courrier
    {
        return $this->enregistrerDechargeAc($courrier, $acteur, [
            'date_decharge' => now()->toDateString(),
            'observation' => $observation,
        ], $message);
    }

    /**
     * La particulière crée le courrier départ (projet = départ) et le place
     * en attente de signature DG — le circuit avance vers « validation_reponse_dg ».
     *
     * @param  array{document_id: int, objet?: ?string, reponse_id: int}  $donnees
     */
    public function soumettreDepartPourSignature(Courrier $arrivee, User $acteur, array $donnees): Courrier
    {
        $etape = $arrivee->circuitEtapeActuelle;
        if (! $etape || $etape->code !== 'traitement_particuliere') {
            throw new InvalidArgumentException('Aucune étape de préparation de réponse en cours sur ce courrier.');
        }

        // Exclure le départ qu’on vient de créer (même transaction) : il est déjà
        // en « transmis_directeur » et serait sinon pris pour un doublon.
        $dejaEnAttente = $arrivee->reponseDepartEnAttenteSignature();
        if ($dejaEnAttente && (int) $dejaEnAttente->id !== (int) ($donnees['reponse_id'] ?? 0)) {
            throw new InvalidArgumentException('Un courrier de réponse est déjà en attente de signature.');
        }

        $arrivee->update([
            'document_reponse_id' => $donnees['document_id'] ?? null,
            'reponse_objet' => $donnees['objet'] ?? null,
            'motif_rejet' => null,
            'rejete_par_id' => null,
            'date_rejet' => null,
        ]);

        $arrivee = $this->avancer(
            $arrivee->fresh(['circuitEtapeActuelle']),
            $acteur,
            'Courrier de réponse n° '.($donnees['numero_reponse'] ?? '').' transmis pour signature.'
        );

        // Une seule notif DG : notifierEtapeCourante (via avancer) envoie déjà REPONSE_A_VALIDER.

        return $arrivee->fresh(['circuit', 'circuitEtapeActuelle']);
    }

    /**
     * Le DG signe le courrier de réponse : le départ passe à « signé », le circuit
     * avance vers l’expédition, l’expéditeur externe est informé (dossier validé).
     */
    public function signerReponseDepart(Courrier $arrivee, Courrier $depart, User $acteur, ?string $commentaire = null): Courrier
    {
        $etape = $arrivee->circuitEtapeActuelle;
        if (! $etape || $etape->code !== 'validation_reponse_dg') {
            throw new InvalidArgumentException('Aucune signature de réponse en cours sur ce courrier.');
        }

        if (! $this->peutAgir($arrivee, $acteur)) {
            throw new InvalidArgumentException('Vous n’êtes pas autorisé à signer cette réponse.');
        }

        if ((int) $depart->courrier_parent_id !== (int) $arrivee->id) {
            throw new InvalidArgumentException('Ce courrier départ n’est pas lié à cette arrivée.');
        }

        if (! in_array($depart->statutCourrier?->code, ['transmis_directeur', 'signe'], true)) {
            throw new InvalidArgumentException('Ce courrier de réponse n’est pas en attente de signature.');
        }

        $arrivee = $this->avancer(
            $arrivee->fresh(['circuitEtapeActuelle']),
            $acteur,
            $commentaire ?: 'Réponse signée — à expédier par la particulière (n° '.$depart->numeroRegistreComplet().').'
        );

        // Une seule notif particulière : avancer → expedition_reponse (REPONSE_VALIDEE_A_CREER).
        $this->notifications->notifierExpediteurExterneValide($arrivee->fresh(['sensCourrier', 'statutCourrier']));

        return $arrivee->fresh(['circuit', 'circuitEtapeActuelle']);
    }

    /**
     * Signataire historique (rétrocompat) : DG ayant signé / validé l’étape.
     */
    public function signataireApresValidationProjet(Courrier $courrier): ?User
    {
        $historique = CircuitCourrierHistorique::query()
            ->where('courrier_id', $courrier->id)
            ->where('evenement', 'avancement')
            ->whereHas('etape', fn ($q) => $q->where('code', 'validation_reponse_dg'))
            ->latest('id')
            ->first();

        if ($historique?->user_id) {
            return User::query()->find($historique->user_id);
        }

        return $this->resoudreActeurDirecteur($courrier);
    }

    /**
     * Le DG rejette le courrier de réponse : retour à « traitement_particuliere ».
     */
    public function rejeterReponse(Courrier $courrier, User $acteur, string $motif): Courrier
    {
        $etape = $courrier->circuitEtapeActuelle;
        if (! $etape || $etape->code !== 'validation_reponse_dg') {
            throw new InvalidArgumentException('Aucune signature de réponse en cours sur ce courrier.');
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
     * Après expédition du départ réponse : clôture l’étape finale du circuit arrivée.
     */
    public function completerApresExpeditionReponse(Courrier $arrivee, User $acteur, ?string $commentaire = null): Courrier
    {
        $etape = $arrivee->circuitEtapeActuelle;
        if (! $etape || $etape->code !== 'expedition_reponse') {
            return $arrivee->fresh(['circuit', 'circuitEtapeActuelle']);
        }

        if (! $this->peutAgir($arrivee, $acteur) && ! $acteur->aAccesTotal() && ! $acteur->hasRole('admin')) {
            return $arrivee->fresh(['circuit', 'circuitEtapeActuelle']);
        }

        try {
            return $this->avancer(
                $arrivee->fresh(['circuitEtapeActuelle']),
                $acteur,
                $commentaire ?: 'Réponse expédiée — circuit terminé.'
            );
        } catch (InvalidArgumentException) {
            return $arrivee->fresh(['circuit', 'circuitEtapeActuelle']);
        }
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

            $fresh = $courrier->fresh(['circuit', 'circuitEtapeActuelle', 'sensCourrier', 'statutCourrier']);
            $this->notifications->notifierExpediteurExterneValide($fresh);

            return $fresh;
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

            // Les destinataires confiés sont désignés à la sortie de l’étape d’instruction :
            // on ne les efface qu’après qu’ils ont traité « leur » étape (pas au moment de l’instruction).
            if ($etape->action !== CircuitCourrierEtape::ACTION_INSTRUIRE) {
                $this->viderAgentsConfies($courrier);
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
     * Rattrapage : si le courrier est bloqué sur une étape désormais automatique
     * (ex. « Traitement dossiers → AC »), l’avance et notifie l’acteur suivant.
     */
    public function assurerEtapesAutomatiques(Courrier $courrier, User $acteur): Courrier
    {
        if (! $courrier->circuit_etape_actuelle_id) {
            return $courrier;
        }

        $courrier->loadMissing(['circuitEtapeActuelle', 'agentConfie']);
        $avantId = $courrier->circuit_etape_actuelle_id;

        if (! $courrier->circuitEtapeActuelle || ! $this->etapeEstAutomatique($courrier, $courrier->circuitEtapeActuelle)) {
            return $courrier;
        }

        $courrier = $this->poursuivreEtapesAutomatiques($courrier, $acteur);

        if ((int) $courrier->circuit_etape_actuelle_id !== (int) $avantId) {
            $this->notifierEtapeCourante($courrier, $acteur);
        }

        return $courrier->fresh(['circuit', 'circuitEtapeActuelle', 'agentConfie']);
    }

    /**
     * Enchaîne automatiquement les étapes qui n’exigent aucune décision humaine
     * jusqu’à la prochaine étape métier (instruction, traitement dédié, signature…).
     */
    protected function poursuivreEtapesAutomatiques(Courrier $courrier, User $acteur): Courrier
    {
        $etape = $courrier->circuitEtapeActuelle;

        while ($etape && $this->etapeEstAutomatique($courrier, $etape)) {
            $courrier = $this->avancerUneEtape(
                $courrier->fresh(['circuitEtapeActuelle', 'agentConfie']),
                $acteur,
                'Validation automatique : '.$etape->nom
            );
            $etape = $courrier->circuitEtapeActuelle;
        }

        return $courrier->fresh(['circuit', 'circuitEtapeActuelle', 'circuitHistoriques.etape', 'circuitHistoriques.user']);
    }

    /**
     * Étapes sans action métier dédiée (pas de « Valider l’étape ») :
     * - notification pure ;
     * - relais facture : AC → caissiers, retour caisse.
     */
    public function etapeEstAutomatique(Courrier $courrier, CircuitCourrierEtape $etape): bool
    {
        if ($etape->action === CircuitCourrierEtape::ACTION_NOTIFIER) {
            return true;
        }

        return in_array($etape->code, [
            'ac_vers_caissiers',
            'retour_caisse_depenses',
        ], true);
    }

    /**
     * Étapes à compter dans la barre de progression UI : uniquement celles
     * qui exigent une action humaine (hors relais auto, enregistrement initial, clôture auto).
     */
    public function etapeCompteDansProgression(Courrier $courrier, CircuitCourrierEtape $etape): bool
    {
        if ($this->etapeEstAutomatique($courrier, $etape)) {
            return false;
        }

        if ($etape->action === CircuitCourrierEtape::ACTION_ENREGISTRER
            || $etape->code === 'enregistrement') {
            return false;
        }

        return true;
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
        if ($courrier->libelleDelaiExecution()) {
            $detail .= ' | Délai d’exécution : '.$courrier->libelleDelaiExecution();
        }

        $courrier->loadMissing(['agentConfie', 'agentsConfies']);
        $agentsConfies = $courrier->agentsConfies->isNotEmpty()
            ? $courrier->agentsConfies
            : collect($courrier->agentConfie ? [$courrier->agentConfie] : []);

        if ($agentsConfies->isNotEmpty()) {
            foreach ($agentsConfies as $agent) {
                $this->notifications->notifier(
                    $agent,
                    $courrier,
                    $acteur,
                    CourrierNotificationService::DOSSIER_CONFIE,
                    $detail
                );
            }

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

        $type = $etape->code === 'expedition_reponse'
            ? CourrierNotificationService::REPONSE_VALIDEE_A_CREER
            : CourrierNotificationService::ETAPE_CIRCUIT;

        if (! $courrier->est_confidentiel) {
            $roles = array_merge($roles, ['particulier_dg', 'particulier_ac']);
        }

        // Facture/MAD : le DG est déjà alerté par notifierFactureEnregistreeDg (cloche + SMS).
        // Éviter le doublon « À traiter : Bon pour accord… » sur la même étape.
        if ($this->etapeFactureInstructionsDgSansNotifCircuitDg($courrier, $etape)) {
            $roles = array_values(array_filter($roles, fn (string $role): bool => $role !== 'dg'));
        }

        $this->notifications->notifierRoles($roles, $courrier, $acteur, $type, $detail);
    }

    /**
     * Étape « Bon pour accord / instructions DG » du circuit facture prestataire.
     */
    protected function etapeFactureInstructionsDgSansNotifCircuitDg(Courrier $courrier, CircuitCourrierEtape $etape): bool
    {
        $courrier->loadMissing('circuit');

        if ($courrier->circuit?->code !== 'facture_prestataire') {
            return false;
        }

        return in_array($etape->code, ['instructions_dg', 'instruction_dg'], true);
    }

    /**
     * Étapes pour la barre de progression : actions manuelles uniquement
     * (les relais automatiques du système sont exclus du décompte).
     *
     * @return list<array{etape: CircuitCourrierEtape, statut: string}>
     */
    public function etapesPourAffichage(Courrier $courrier): array
    {
        if (! $courrier->circuit_courrier_id) {
            return [];
        }

        $courrier->loadMissing(['circuit.etapesActives', 'circuitEtapeActuelle', 'agentConfie']);
        $actuelleId = $courrier->circuit_etape_actuelle_id;
        $actuelleOrdre = $courrier->circuitEtapeActuelle?->ordre;

        return $courrier->circuit->etapesActives
            ->filter(fn (CircuitCourrierEtape $etape) => $this->etapeCompteDansProgression($courrier, $etape))
            ->values()
            ->map(function (CircuitCourrierEtape $etape) use ($actuelleId, $actuelleOrdre) {
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
            })
            ->all();
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
            'controle_depense' => 'Contrôle des pièces (suivi dépenses)',
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
