<?php

namespace App\Services;

use App\Models\CategorieDepense;
use App\Models\Courrier;
use App\Models\Document;
use App\Models\FournisseurPrestataire;
use App\Models\JournalAudit;
use App\Models\Moratoire;
use App\Models\PrioriteCourrier;
use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use App\Models\StatutDocument;
use App\Models\SuiviPaiement;
use App\Models\TypeCourrier;
use App\Models\TypeDocument;
use App\Models\User;
use App\Support\MontantFcfa;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Enregistrement hors circuit des factures historiques (impayées ou programmées).
 */
class FactureRegularisationService
{
    public const PAIEMENT_IMPAYEE = 'impayee';

    public const PAIEMENT_PROGRAMMEE = 'programmee';

    public const PAIEMENT_PAYEE = 'payee';

    public const PAIEMENT_CONTRAT_MENSUEL = 'contrat_mensuel';

    public const MODE_CHEQUE = 'cheque';

    public const MODE_ESPECE = 'espece';

    public const MODE_OV = 'ov';

    /** @var list<string> */
    public const MODES_PAIEMENT = [
        self::MODE_CHEQUE,
        self::MODE_ESPECE,
        self::MODE_OV,
    ];

    public function __construct(
        private readonly CourrierNumeroRegistreService $numeroService,
        private readonly FournisseurDetteService $detteService,
    ) {}

    /**
     * @param  array{
     *     fournisseur_libelle: string,
     *     montant_facture: float|int|string,
     *     objet?: ?string,
     *     reference?: ?string,
     *     date_facture?: ?string,
     *     date_reception?: ?string,
     *     service_demandeur_structure_id?: ?int,
     *     paiement: string,
     *     mode_paiement?: ?string,
     *     date_programmation?: ?string,
     *     numero_piece?: ?string,
     *     banque?: ?string,
     *     observation?: ?string,
     *     fichiers?: list<UploadedFile>
     * }  $donnees
     */
    public function enregistrer(User $acteur, array $donnees): Courrier
    {
        $paiement = $donnees['paiement'] ?? self::PAIEMENT_IMPAYEE;
        if (! in_array($paiement, [self::PAIEMENT_IMPAYEE, self::PAIEMENT_PROGRAMMEE, self::PAIEMENT_CONTRAT_MENSUEL], true)) {
            throw new InvalidArgumentException('Statut de paiement de régularisation invalide.');
        }

        [$montant, $montantMensuel, $nbMois] = $this->resoudreMontantRegularisation($paiement, $donnees);

        [$fournisseur, $fournisseurFiche] = $this->resoudreFournisseurDepuisDonnees($acteur, $donnees);

        $mode = null;
        $dateProgrammation = null;
        $numeroPiece = null;
        $banque = null;

        if ($paiement === self::PAIEMENT_PROGRAMMEE) {
            $mode = $donnees['mode_paiement'] ?? null;
            if (! in_array($mode, self::MODES_PAIEMENT, true)) {
                throw new InvalidArgumentException('Mode de paiement invalide pour une facture programmée.');
            }
            $dateProgrammation = $donnees['date_programmation'] ?? null;
            if (! $dateProgrammation) {
                throw new InvalidArgumentException('La date de programmation est obligatoire.');
            }
            $numeroPiece = isset($donnees['numero_piece']) ? trim((string) $donnees['numero_piece']) : null;
            $banque = isset($donnees['banque']) ? trim((string) $donnees['banque']) : null;

            if (in_array($mode, [self::MODE_CHEQUE, self::MODE_OV], true) && ($numeroPiece === null || $numeroPiece === '')) {
                throw new InvalidArgumentException(
                    $mode === self::MODE_OV
                        ? 'La référence OV est obligatoire.'
                        : 'Le N° de pièce / chèque est obligatoire.'
                );
            }
        }

        $sens = SensCourrier::query()->where('code', SensCourrier::ARRIVEE)->firstOrFail();
        $type = TypeCourrier::query()->where('code', 'facture')->where('actif', true)->firstOrFail();
        $statut = StatutCourrier::query()
            ->where('sens_courrier_id', $sens->id)
            ->where('code', 'cloture')
            ->where('actif', true)
            ->firstOrFail();
        $prioriteId = PrioriteCourrier::query()->where('code', 'normale')->value('id');

        /** @var list<UploadedFile> $fichiers */
        $fichiers = array_values(array_filter($donnees['fichiers'] ?? []));
        if ($fichiers === []) {
            $libelleScan = $paiement === self::PAIEMENT_CONTRAT_MENSUEL
                ? 'Au moins un scan du contrat est obligatoire.'
                : 'Au moins un scan (facture) est obligatoire.';
            throw new InvalidArgumentException($libelleScan);
        }

        return DB::transaction(function () use (
            $acteur,
            $donnees,
            $paiement,
            $montant,
            $montantMensuel,
            $nbMois,
            $fournisseur,
            $fournisseurFiche,
            $sens,
            $type,
            $statut,
            $prioriteId,
            $fichiers,
            $mode,
            $dateProgrammation,
            $numeroPiece,
            $banque,
        ): Courrier {
            $nums = $this->numeroService->prochainNumero((int) $sens->id);
            $annee = (int) ($nums['numero_registre_annee'] ?? now()->year);
            $numero = (int) $nums['numero_registre'];

            $objet = trim((string) ($donnees['objet'] ?? ''));
            if ($objet === '') {
                $objet = $paiement === self::PAIEMENT_CONTRAT_MENSUEL
                    ? sprintf(
                        'Contrat mensuel — %s (%d mois × %s FCFA)',
                        $fournisseur,
                        $nbMois,
                        number_format($montantMensuel, 0, ',', ' ')
                    )
                    : 'Régularisation facture — '.$fournisseur;
            }

            $courrier = Courrier::query()->create([
                'sens_courrier_id' => $sens->id,
                'type_courrier_id' => $type->id,
                'statut_courrier_id' => $statut->id,
                'priorite_courrier_id' => $prioriteId,
                'circuit_courrier_id' => null,
                'circuit_etape_actuelle_id' => null,
                'circuit_etape_depuis' => null,
                'numero_registre' => $numero,
                'numero_registre_annee' => $annee,
                'numero_fulgurant' => 'REG-'.$numero.'/'.$annee,
                'reference' => $donnees['reference'] ?? null,
                'origine' => Courrier::ORIGINE_EXTERNE,
                'date_reception' => $donnees['date_reception'] ?? now()->toDateString(),
                'date_courrier' => $donnees['date_facture'] ?? null,
                'expediteur_libelle' => $fournisseur,
                'fournisseur_prestataire_id' => $fournisseurFiche?->id,
                'est_expediteur_externe' => true,
                'service_demandeur_structure_id' => $donnees['service_demandeur_structure_id'] ?? null,
                'objet' => $objet,
                'montant_facture' => $montant,
                'est_regularisation' => true,
                'regularisation_paiement' => $paiement,
                'regularisation_mode_paiement' => $mode,
                'regularisation_date_programmation' => $dateProgrammation,
                'regularisation_numero_piece' => $numeroPiece !== '' ? $numeroPiece : null,
                'regularisation_banque' => $banque !== '' ? $banque : null,
                'regularisation_montant_mensuel' => $montantMensuel,
                'regularisation_nb_mois_impayes' => $nbMois,
                'observations' => $donnees['observation'] ?? 'Enregistrement hors circuit (régularisation historique).',
                'createur_id' => $acteur->id,
                'structure_id' => $acteur->structure_id,
            ]);

            foreach ($fichiers as $index => $fichier) {
                $this->attacherScan($courrier, $acteur, $fichier, $index === 0);
            }

            JournalAudit::log('courrier.regularisation', 'courriers', [
                'commentaire' => json_encode([
                    'courrier_id' => $courrier->id,
                    'paiement' => $paiement,
                    'mode_paiement' => $mode,
                    'montant' => $montant,
                    'fournisseur' => $fournisseur,
                ]),
            ]);

            return $courrier->fresh(['typeCourrier', 'statutCourrier', 'suiviPaiement', 'documents']);
        });
    }

    /**
     * Corrige une régularisation non payée (Taty) : Impayée ↔ Programmée, montants, refs, scans optionnels.
     *
     * @param  array{
     *     fournisseur_prestataire_id?: int|null,
     *     fournisseur_libelle?: ?string,
     *     montant_facture: float|int|string,
     *     objet?: ?string,
     *     reference?: ?string,
     *     date_facture?: ?string,
     *     date_reception?: ?string,
     *     service_demandeur_structure_id?: ?int,
     *     paiement: string,
     *     mode_paiement?: ?string,
     *     date_programmation?: ?string,
     *     numero_piece?: ?string,
     *     banque?: ?string,
     *     observation?: ?string,
     *     fichiers?: list<UploadedFile>
     * }  $donnees
     */
    public function modifier(Courrier $courrier, User $acteur, array $donnees): Courrier
    {
        return DB::transaction(function () use ($courrier, $acteur, $donnees): Courrier {
            $courrierVerrouille = Courrier::query()
                ->whereKey($courrier->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertRegularisationModifiable($courrierVerrouille);

            $paiement = $donnees['paiement'] ?? self::PAIEMENT_IMPAYEE;
            if (! in_array($paiement, [self::PAIEMENT_IMPAYEE, self::PAIEMENT_PROGRAMMEE, self::PAIEMENT_CONTRAT_MENSUEL], true)) {
                throw new InvalidArgumentException('Statut de paiement de régularisation invalide.');
            }

            [$montant, $montantMensuel, $nbMois] = $this->resoudreMontantRegularisation($paiement, $donnees);

            [$fournisseur, $fournisseurFiche] = $this->resoudreFournisseurDepuisDonnees($acteur, $donnees);

            $this->assertPasDeMoratoireCouvrantPourFournisseur(
                $fournisseur,
                'Un plan de paiement progressif (actif ou soldé) existe pour ce fournisseur. Cette facture ne peut plus être modifiée ni déplacée vers ce fournisseur.',
            );

            [$mode, $dateProgrammation, $numeroPiece, $banque] = $this->resoudreChampsProgrammation($paiement, $donnees);

            $objet = trim((string) ($donnees['objet'] ?? ''));
            if ($objet === '') {
                $objet = $paiement === self::PAIEMENT_CONTRAT_MENSUEL
                    ? sprintf(
                        'Contrat mensuel — %s (%d mois × %s FCFA)',
                        $fournisseur,
                        $nbMois,
                        number_format((float) $montantMensuel, 0, ',', ' ')
                    )
                    : 'Régularisation facture — '.$fournisseur;
            }

            $courrierVerrouille->update([
                'reference' => $donnees['reference'] ?? null,
                'date_reception' => $donnees['date_reception'] ?? $courrierVerrouille->date_reception,
                'date_courrier' => $donnees['date_facture'] ?? null,
                'expediteur_libelle' => $fournisseur,
                'fournisseur_prestataire_id' => $fournisseurFiche->id,
                'service_demandeur_structure_id' => $donnees['service_demandeur_structure_id'] ?? null,
                'objet' => $objet,
                'montant_facture' => $montant,
                'regularisation_paiement' => $paiement,
                'regularisation_mode_paiement' => $mode,
                'regularisation_date_programmation' => $dateProgrammation,
                'regularisation_numero_piece' => $numeroPiece,
                'regularisation_banque' => $banque,
                'regularisation_montant_mensuel' => $montantMensuel,
                'regularisation_nb_mois_impayes' => $nbMois,
                'observations' => $donnees['observation'] ?? $courrierVerrouille->observations,
            ]);

            /** @var list<UploadedFile> $fichiers */
            $fichiers = array_values(array_filter($donnees['fichiers'] ?? []));
            $dejaDesDocs = $courrierVerrouille->documents()->exists();
            if ($fichiers === [] && ! $dejaDesDocs) {
                throw new InvalidArgumentException('Au moins un scan (facture) est obligatoire.');
            }

            foreach ($fichiers as $index => $fichier) {
                $this->attacherScan($courrierVerrouille, $acteur, $fichier, ! $dejaDesDocs && $index === 0);
            }

            JournalAudit::log('courrier.regularisation.modifier', 'courriers', [
                'commentaire' => json_encode([
                    'courrier_id' => $courrierVerrouille->id,
                    'paiement' => $paiement,
                    'mode_paiement' => $mode,
                    'montant' => $montant,
                    'acteur_id' => $acteur->id,
                ]),
            ]);

            return $courrierVerrouille->fresh(['typeCourrier', 'statutCourrier', 'suiviPaiement', 'documents']);
        });
    }

    /**
     * Supprime une régularisation non payée (Taty) pour permettre une resaisie.
     */
    public function supprimer(Courrier $courrier, User $acteur): void
    {
        DB::transaction(function () use ($courrier, $acteur): void {
            $courrierVerrouille = Courrier::query()
                ->whereKey($courrier->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertRegularisationModifiable($courrierVerrouille);

            $documents = $courrierVerrouille->documents()->get();
            $courrierVerrouille->documents()->detach();

            foreach ($documents as $document) {
                if ($document->courriers()->exists()) {
                    continue;
                }
                if ($document->chemin) {
                    Storage::disk('public')->delete($document->chemin);
                }
                $document->delete();
            }

            JournalAudit::log('courrier.regularisation.supprimer', 'courriers', [
                'commentaire' => json_encode([
                    'courrier_id' => $courrierVerrouille->id,
                    'numero' => $courrierVerrouille->numeroRegistreComplet(),
                    'fournisseur' => $courrierVerrouille->expediteur_libelle,
                    'acteur_id' => $acteur->id,
                ]),
            ]);

            $courrierVerrouille->delete();
        });
    }

    private function assertRegularisationModifiable(Courrier $courrier): void
    {
        if (! $courrier->est_regularisation) {
            throw new InvalidArgumentException('Ce courrier n’est pas une régularisation.');
        }

        if ($courrier->regularisation_paiement === self::PAIEMENT_PAYEE
            || SuiviPaiement::query()->where('courrier_id', $courrier->id)->exists()) {
            throw new InvalidArgumentException('Une facture déjà payée ne peut plus être modifiée ni supprimée.');
        }

        $this->assertPasDeMoratoireCouvrantPourFournisseur(
            $courrier->expediteur_libelle,
            'Un plan de paiement progressif (actif ou soldé) existe pour ce fournisseur. Cette facture ne peut plus être modifiée ni supprimée.',
        );
    }

    /**
     * Bloque le paiement direct d’une régularisation déjà couverte par un moratoire (actif ou soldé).
     */
    public function assertPaiementDirectAutorise(Courrier $courrier): void
    {
        $this->assertPasDeMoratoireCouvrantPourFournisseur(
            $courrier->expediteur_libelle,
            'Un plan de paiement progressif (actif ou soldé) existe pour ce fournisseur. Le règlement passe par les échéances du moratoire — pas de paiement direct.',
        );
    }

    /**
     * Indique si un moratoire actif ou soldé bloque les actions sur la régularisation.
     */
    public function aMoratoireActif(Courrier $courrier): bool
    {
        return $this->moratoireCouvrantPourFournisseur($courrier->expediteur_libelle) !== null;
    }

    /**
     * Clés normalisées des fournisseurs ayant un moratoire actif ou soldé (pour l’UI liste).
     *
     * @return array<string, true>
     */
    public function clesFournisseursMoratoireActif(): array
    {
        return Moratoire::query()
            ->whereIn('statut', [Moratoire::STATUT_ACTIF, Moratoire::STATUT_SOLDE])
            ->pluck('fournisseur_normalise')
            ->filter()
            ->mapWithKeys(fn (string $cle) => [$cle => true])
            ->all();
    }

    private function assertPasDeMoratoireCouvrantPourFournisseur(?string $libelle, string $message): void
    {
        if ($this->moratoireCouvrantPourFournisseur($libelle) !== null) {
            throw new InvalidArgumentException($message);
        }
    }

    private function moratoireCouvrantPourFournisseur(?string $libelle): ?Moratoire
    {
        $normalise = $this->detteService->normaliserLibelle($libelle);
        if ($normalise === '') {
            return null;
        }

        return Moratoire::query()
            ->where('fournisseur_normalise', $normalise)
            ->whereIn('statut', [Moratoire::STATUT_ACTIF, Moratoire::STATUT_SOLDE])
            ->orderByRaw('CASE WHEN statut = ? THEN 0 ELSE 1 END', [Moratoire::STATUT_ACTIF])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string}
     */
    private function resoudreChampsProgrammation(string $paiement, array $donnees): array
    {
        if ($paiement !== self::PAIEMENT_PROGRAMMEE) {
            return [null, null, null, null];
        }

        $mode = $donnees['mode_paiement'] ?? null;
        if (! in_array($mode, self::MODES_PAIEMENT, true)) {
            throw new InvalidArgumentException('Mode de paiement invalide pour une facture programmée.');
        }

        $dateProgrammation = $donnees['date_programmation'] ?? null;
        if (! $dateProgrammation) {
            throw new InvalidArgumentException('La date de programmation est obligatoire.');
        }

        $numeroPiece = isset($donnees['numero_piece']) ? trim((string) $donnees['numero_piece']) : null;
        $banque = isset($donnees['banque']) ? trim((string) $donnees['banque']) : null;

        if (in_array($mode, [self::MODE_CHEQUE, self::MODE_OV], true) && ($numeroPiece === null || $numeroPiece === '')) {
            throw new InvalidArgumentException(
                $mode === self::MODE_OV
                    ? 'La référence OV est obligatoire.'
                    : 'Le N° de pièce / chèque est obligatoire.'
            );
        }

        return [
            $mode,
            $dateProgrammation,
            $numeroPiece !== '' ? $numeroPiece : null,
            $banque !== '' ? $banque : null,
        ];
    }

    /**
     * Enregistre le paiement effectif d'une facture programmée (Eleni).
     *
     * @param  array{
     *     date_paiement: string,
     *     numero_piece?: ?string,
     *     banque?: ?string,
     *     observation?: ?string,
     *     fichiers?: list<UploadedFile>
     * }  $donnees
     */
    public function enregistrerPaiementEffectif(Courrier $courrier, User $acteur, array $donnees): Courrier
    {
        $datePaiement = $donnees['date_paiement'] ?? null;
        if (! $datePaiement) {
            throw new InvalidArgumentException('La date de paiement est obligatoire.');
        }

        /** @var list<UploadedFile> $fichiers */
        $fichiers = array_values(array_filter($donnees['fichiers'] ?? []));

        try {
            return DB::transaction(function () use (
                $courrier,
                $acteur,
                $donnees,
                $datePaiement,
                $fichiers,
            ): Courrier {
                $courrierVerrouille = Courrier::query()
                    ->whereKey($courrier->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($courrierVerrouille->regularisation_paiement !== self::PAIEMENT_PROGRAMMEE
                    || ! $courrierVerrouille->est_regularisation) {
                    throw new InvalidArgumentException('Seule une facture programmée peut recevoir un paiement effectif.');
                }

                if (SuiviPaiement::query()->where('courrier_id', $courrierVerrouille->id)->lockForUpdate()->exists()) {
                    throw new InvalidArgumentException('Cette facture a déjà un suivi de paiement.');
                }

                $this->assertPaiementDirectAutorise($courrierVerrouille);

                $montant = MontantFcfa::versFloat($courrierVerrouille->montant_facture);
                if ($montant <= 0) {
                    throw new InvalidArgumentException('Le montant de la facture est invalide.');
                }

                $mode = $courrierVerrouille->regularisation_mode_paiement;
                $numeroPiece = isset($donnees['numero_piece'])
                    ? trim((string) $donnees['numero_piece'])
                    : (string) ($courrierVerrouille->regularisation_numero_piece ?? '');
                $banque = isset($donnees['banque'])
                    ? trim((string) $donnees['banque'])
                    : (string) ($courrierVerrouille->regularisation_banque ?? '');

                if (in_array($mode, [self::MODE_CHEQUE, self::MODE_OV], true) && $numeroPiece === '') {
                    throw new InvalidArgumentException(
                        $mode === self::MODE_OV
                            ? 'La référence OV est obligatoire.'
                            : 'Le N° de pièce / chèque est obligatoire.'
                    );
                }

                $payloadSuivi = [
                    'numero_piece' => $numeroPiece !== '' ? $numeroPiece : null,
                    'banque' => $banque !== '' ? $banque : null,
                    'date_paiement' => $datePaiement,
                ];

                $suivi = $this->creerSuiviPaiementPaye($courrierVerrouille, $acteur, $montant, $payloadSuivi);

                if ($fichiers !== []) {
                    app(SuiviDepenseClassementService::class)->deposerJustificatifsEnAttente(
                        $suivi,
                        $acteur,
                        $fichiers,
                    );
                }

                $courrierVerrouille->update([
                    'regularisation_paiement' => self::PAIEMENT_PAYEE,
                    'regularisation_numero_piece' => $numeroPiece !== '' ? $numeroPiece : $courrierVerrouille->regularisation_numero_piece,
                    'regularisation_banque' => $banque !== '' ? $banque : $courrierVerrouille->regularisation_banque,
                    'observations' => $donnees['observation'] ?? $courrierVerrouille->observations,
                ]);

                JournalAudit::log('courrier.regularisation.paiement', 'courriers', [
                    'commentaire' => json_encode([
                        'courrier_id' => $courrierVerrouille->id,
                        'suivi_paiement_id' => $suivi->id,
                        'date_paiement' => $datePaiement,
                        'montant' => $montant,
                    ]),
                ]);

                return $courrierVerrouille->fresh(['typeCourrier', 'statutCourrier', 'suiviPaiement', 'documents']);
            });
        } catch (UniqueConstraintViolationException $e) {
            throw new InvalidArgumentException('Cette facture a déjà un suivi de paiement.', 0, $e);
        }
    }

    /**
     * @param  array{
     *     numero_piece?: ?string,
     *     banque?: ?string,
     *     date_paiement?: ?string
     * }  $donnees
     */
    private function creerSuiviPaiementPaye(Courrier $courrier, User $acteur, float $montant, array $donnees): SuiviPaiement
    {
        $categorieId = CategorieDepense::idPourCode(CategorieDepense::CODE_FACTURE);
        if (! $categorieId) {
            throw new InvalidArgumentException('Catégorie de dépense « facture » introuvable.');
        }
        $annee = (int) ($courrier->numero_registre_annee ?: now()->year);
        $datePaiement = $donnees['date_paiement'] ?? now()->toDateString();

        $numeroLigne = (int) SuiviPaiement::query()
            ->where('categorie_depense_id', $categorieId)
            ->where('numero_annee', $annee)
            ->lockForUpdate()
            ->max('numero_ligne') + 1;

        return SuiviPaiement::query()->create([
            'courrier_id' => $courrier->id,
            'type' => SuiviPaiement::TYPE_FSP_FACTURE,
            'categorie_depense_id' => $categorieId,
            'origine' => SuiviPaiement::ORIGINE_REGULARISATION,
            'numero_ligne' => $numeroLigne,
            'numero_annee' => $annee,
            'date_suivi' => $datePaiement,
            'date_decharge' => $datePaiement,
            'intitule' => $courrier->objet,
            'montant' => $montant,
            'numero_piece' => $donnees['numero_piece'] ?? null,
            'banque' => $donnees['banque'] ?? null,
            'beneficiaire_libelle' => $courrier->expediteur_libelle,
            'fournisseur_libelle' => $courrier->expediteur_libelle,
            'fournisseur_prestataire_id' => $courrier->fournisseur_prestataire_id,
            'observation' => 'Paiement effectif d’une facture programmée (régularisation).',
            'etabli_par_id' => $acteur->id,
            'controle_par_id' => $acteur->id,
            'controle_at' => now(),
        ]);
    }

    private function attacherScan(Courrier $courrier, User $acteur, UploadedFile $file, bool $principal): void
    {
        $typeDoc = TypeDocument::query()
            ->whereIn('code', ['COURRIER_IN', 'COURRIER'])
            ->where('actif', true)
            ->orderByRaw("CASE WHEN code LIKE 'COURRIER_%' THEN 0 ELSE 1 END")
            ->first();

        if (! $typeDoc) {
            return;
        }

        $statut = StatutDocument::query()->where('code', 'brouillon')->first();
        $chemin = $file->store('documents/courriers', 'public');

        $document = Document::query()->create([
            'type_document_id' => $typeDoc->id,
            'user_id' => $acteur->id,
            'createur_id' => $acteur->id,
            'proprietaire_id' => $acteur->id,
            'dossier_id' => $courrier->dossier_id,
            'nom_original' => $file->getClientOriginalName(),
            'chemin' => $chemin,
            'extension' => $file->getClientOriginalExtension(),
            'taille_octets' => $file->getSize(),
            'statut' => 'brouillon',
            'statut_document_id' => $statut?->id,
            'en_corbeille' => false,
        ]);

        $courrier->documents()->attach($document->id, ['est_principal' => $principal]);
    }

    /**
     * @return LengthAwarePaginator<int, Courrier>
     */
    public function lister(?string $recherche = null, ?string $paiement = null)
    {
        $typeFactureId = TypeCourrier::query()->where('code', 'facture')->value('id');

        $query = Courrier::query()
            ->with(['createur', 'suiviPaiement', 'serviceDemandeurStructure'])
            ->where('est_regularisation', true)
            ->when($typeFactureId, fn ($q) => $q->where('type_courrier_id', $typeFactureId))
            ->orderByDesc('id');

        if ($recherche) {
            $query->where(function ($sub) use ($recherche): void {
                $sub->where('expediteur_libelle', 'like', "%{$recherche}%")
                    ->orWhere('objet', 'like', "%{$recherche}%")
                    ->orWhere('reference', 'like', "%{$recherche}%")
                    ->orWhere('numero_fulgurant', 'like', "%{$recherche}%");
            });
        }

        if (in_array($paiement, [self::PAIEMENT_IMPAYEE, self::PAIEMENT_PROGRAMMEE, self::PAIEMENT_PAYEE, self::PAIEMENT_CONTRAT_MENSUEL], true)) {
            $query->where('regularisation_paiement', $paiement);
        }

        return $query->paginate(25)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array{0: string, 1: FournisseurPrestataire}
     */
    private function resoudreFournisseurDepuisDonnees(User $acteur, array $donnees): array
    {
        $fournisseurId = isset($donnees['fournisseur_prestataire_id'])
            ? (int) $donnees['fournisseur_prestataire_id']
            : 0;
        $fournisseurFiche = $fournisseurId > 0
            ? FournisseurPrestataire::query()->find($fournisseurId)
            : null;

        $fournisseur = $fournisseurFiche
            ? $fournisseurFiche->nom
            : trim((string) ($donnees['fournisseur_libelle'] ?? ''));

        if ($fournisseur === '') {
            throw new InvalidArgumentException('Le fournisseur est obligatoire.');
        }

        if (! $fournisseurFiche) {
            $fournisseurFiche = app(FournisseurPrestataireService::class)
                ->trouverOuCreerParLibelle($acteur, $fournisseur);
        }

        return [$fournisseur, $fournisseurFiche];
    }

    /**
     * @return array{0: float, 1: ?float, 2: ?int}
     */
    private function resoudreMontantRegularisation(string $paiement, array $donnees): array
    {
        if ($paiement === self::PAIEMENT_CONTRAT_MENSUEL) {
            $montantMensuel = MontantFcfa::versFloat($donnees['montant_mensuel_contrat'] ?? 0);
            $nbMois = (int) ($donnees['nb_mois_impayes'] ?? 0);

            if ($montantMensuel <= 0) {
                throw new InvalidArgumentException('Le montant mensuel du contrat doit être strictement positif.');
            }

            if ($nbMois < 1) {
                throw new InvalidArgumentException('Le nombre de mois impayés doit être au moins 1.');
            }

            return [$montantMensuel * $nbMois, $montantMensuel, $nbMois];
        }

        $montant = MontantFcfa::versFloat($donnees['montant_facture'] ?? 0);
        if ($montant <= 0) {
            throw new InvalidArgumentException('Le montant de la facture doit être strictement positif.');
        }

        return [$montant, null, null];
    }

    public static function libelleModePaiement(?string $mode): string
    {
        return match ($mode) {
            self::MODE_CHEQUE => 'Chèque',
            self::MODE_ESPECE => 'Espèces',
            self::MODE_OV => 'OV (ordre de virement)',
            default => '—',
        };
    }

    public static function libelleStatutPaiement(?string $paiement): string
    {
        return match ($paiement) {
            self::PAIEMENT_IMPAYEE => 'Impayée',
            self::PAIEMENT_PROGRAMMEE => 'Programmée',
            self::PAIEMENT_PAYEE => 'Payée',
            self::PAIEMENT_CONTRAT_MENSUEL => 'Contrat mensuel',
            default => '—',
        };
    }
}
