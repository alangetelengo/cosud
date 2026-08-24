<?php

namespace App\Services;

use App\Models\CategorieDepense;
use App\Models\Courrier;
use App\Models\SuiviPaiement;
use App\Models\User;
use App\Support\MontantFcfa;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuiviPaiementService
{
    public function resoudreTypePourCourrier(Courrier $courrier): string
    {
        $courrier->loadMissing('typeCourrier');

        return $courrier->typeCourrier?->code === 'mad'
            ? SuiviPaiement::TYPE_FSP_MAD
            : SuiviPaiement::TYPE_FSP_FACTURE;
    }

    /**
     * Crée une ligne FSP à l’établissement du chèque par l’AC (références bordereau obligatoires).
     *
     * @param  array{
     *     numero_piece: string,
     *     banque: string,
     *     beneficiaire_libelle: string,
     *     programmation?: ?string
     * }  $references
     */
    public function creerDepuisEntreeCheque(Courrier $courrier, User $acteur, float $montant, array $references): SuiviPaiement
    {
        if ($montant <= 0) {
            throw new InvalidArgumentException('Le montant du chèque doit être strictement positif.');
        }

        $courrier->loadMissing([
            'typeCourrier',
            'structure',
            'structureDestinataire',
            'serviceDemandeurStructure',
            'structureExpediteur',
            'agentConfie',
            'createur',
        ]);

        $type = $this->resoudreTypePourCourrier($courrier);
        $annee = (int) now()->format('Y');

        return DB::transaction(function () use ($courrier, $acteur, $montant, $type, $annee, $references): SuiviPaiement {
            Courrier::query()->whereKey($courrier->id)->lockForUpdate()->first();

            if (SuiviPaiement::query()->where('courrier_id', $courrier->id)->exists()) {
                throw new InvalidArgumentException('Une fiche de suivi existe déjà pour ce courrier.');
            }

            $categorieDepenseId = CategorieDepense::idPourCode(
                CategorieDepense::codeDepuisTypeLegacy($type)
            );

            $numeroLigne = (int) SuiviPaiement::query()
                ->where('categorie_depense_id', $categorieDepenseId)
                ->where('numero_annee', $annee)
                ->lockForUpdate()
                ->max('numero_ligne') + 1;

            $serviceDemandeur = $courrier->serviceDemandeurStructure?->nom
                ?? $courrier->structureDestinataire?->nom
                ?? $courrier->structure?->nom;

            $data = [
                'courrier_id' => $courrier->id,
                'type' => $type,
                'categorie_depense_id' => $categorieDepenseId,
                'origine' => SuiviPaiement::ORIGINE_CIRCUIT_CHEQUE,
                'numero_ligne' => $numeroLigne,
                'numero_annee' => $annee,
                'date_suivi' => now()->toDateString(),
                'intitule' => (string) $courrier->objet,
                'montant' => $montant,
                'numero_piece' => $references['numero_piece'],
                'banque' => $references['banque'],
                'beneficiaire_libelle' => $references['beneficiaire_libelle'],
                'programmation' => $references['programmation'] ?? null,
                'instruction_dg' => $courrier->instructions_dg,
                'etabli_par_id' => $acteur->id,
            ];

            if ($type === SuiviPaiement::TYPE_FSP_MAD) {
                $data['demandeur_libelle'] = $serviceDemandeur
                    ?? $courrier->expediteur_libelle;
                $data['responsable_dossier_id'] = $courrier->agent_confie_id
                    ?? $courrier->createur_id;
            } else {
                $data['fournisseur_libelle'] = $courrier->expediteur_libelle;
                $data['service_demandeur_libelle'] = $serviceDemandeur;
            }

            if ($courrier->dossier_id) {
                $data['dossier_id'] = $courrier->dossier_id;
            }

            return SuiviPaiement::query()->create($data);
        });
    }

    /**
     * Dépense hors circuit : copies remises personnellement par le DG à Eleni.
     *
     * @param  array{
     *     categorie_depense_id: int,
     *     date_suivi: string,
     *     intitule: string,
     *     montant: float|int|string,
     *     beneficiaire_libelle?: ?string,
     *     numero_piece?: ?string,
     *     instruction_dg?: ?string,
     *     observation?: ?string,
     *     justificatifs?: list<UploadedFile>
     * }  $donnees
     */
    public function creerRemiseDg(User $acteur, array $donnees): SuiviPaiement
    {
        $categorie = CategorieDepense::query()
            ->whereKey((int) $donnees['categorie_depense_id'])
            ->where('actif', true)
            ->first();

        if (! $categorie) {
            throw new InvalidArgumentException('Catégorie de dépense invalide ou inactive.');
        }

        if (in_array($categorie->code, [CategorieDepense::CODE_FACTURE, CategorieDepense::CODE_PAIEMENT_DIVERS], true)) {
            throw new InvalidArgumentException('Cette catégorie est réservée au circuit courrier (chèque).');
        }

        $montant = MontantFcfa::versFloat($donnees['montant']);
        if ($montant <= 0) {
            throw new InvalidArgumentException('Le montant doit être strictement positif.');
        }

        $type = $this->typeLegacyDepuisCategorie($categorie);
        $annee = (int) Carbon::parse($donnees['date_suivi'])->year;
        /** @var list<UploadedFile> $justificatifs */
        $justificatifs = $donnees['justificatifs'] ?? [];

        return DB::transaction(function () use ($acteur, $donnees, $categorie, $type, $montant, $annee, $justificatifs): SuiviPaiement {
            $numeroLigne = (int) SuiviPaiement::query()
                ->where('categorie_depense_id', $categorie->id)
                ->where('numero_annee', $annee)
                ->lockForUpdate()
                ->max('numero_ligne') + 1;

            $ligne = SuiviPaiement::query()->create([
                'courrier_id' => null,
                'type' => $type,
                'categorie_depense_id' => $categorie->id,
                'origine' => SuiviPaiement::ORIGINE_REMISE_DG,
                'numero_ligne' => $numeroLigne,
                'numero_annee' => $annee,
                'date_suivi' => $donnees['date_suivi'],
                'intitule' => $donnees['intitule'],
                'montant' => $montant,
                'beneficiaire_libelle' => $donnees['beneficiaire_libelle'] ?? null,
                'numero_piece' => $donnees['numero_piece'] ?? null,
                'instruction_dg' => $donnees['instruction_dg'] ?? null,
                'observation' => $donnees['observation'] ?? null,
                'etabli_par_id' => $acteur->id,
                'controle_par_id' => $acteur->hasRole('responsable_suivi_depenses') ? $acteur->id : null,
                'controle_at' => $acteur->hasRole('responsable_suivi_depenses') ? now() : null,
            ]);

            app(SuiviDepenseClassementService::class)->classerDepenseAvecJustificatifs(
                $ligne,
                $acteur,
                $categorie,
                $justificatifs,
            );

            return $ligne->fresh(['dossier', 'categorieDepense']);
        });
    }

    public function typeLegacyDepuisCategorie(CategorieDepense $categorie): string
    {
        return match ($categorie->code) {
            CategorieDepense::CODE_FACTURE => SuiviPaiement::TYPE_FSP_FACTURE,
            CategorieDepense::CODE_PAIEMENT_DIVERS => SuiviPaiement::TYPE_FSP_MAD,
            CategorieDepense::CODE_PAIE => SuiviPaiement::TYPE_FSP_PAIE,
            CategorieDepense::CODE_COMMISSION => SuiviPaiement::TYPE_FSP_COMMISSION,
            CategorieDepense::CODE_TTF => SuiviPaiement::TYPE_FSP_TTF,
            default => SuiviPaiement::TYPE_FSP_MANUEL,
        };
    }

    public function libellePourType(string $type): string
    {
        return match ($type) {
            SuiviPaiement::TYPE_FSP_MAD => 'Fiche de suivi paiement divers',
            SuiviPaiement::TYPE_FSP_PAIE => 'Paie',
            SuiviPaiement::TYPE_FSP_COMMISSION => 'Commission',
            SuiviPaiement::TYPE_FSP_TTF => 'TTF',
            SuiviPaiement::TYPE_FSP_MANUEL => 'Dépense',
            default => 'Fiche de suivi paiement facture',
        };
    }

    public function titreFichePourType(string $type): string
    {
        return match ($type) {
            SuiviPaiement::TYPE_FSP_MAD => 'Fiche de suivi paiement divers',
            SuiviPaiement::TYPE_FSP_PAIE => 'Dépenses paie',
            SuiviPaiement::TYPE_FSP_COMMISSION => 'Commissions',
            SuiviPaiement::TYPE_FSP_TTF => 'TTF',
            SuiviPaiement::TYPE_FSP_MANUEL => 'Suivi de dépense',
            default => 'Fiche de suivi paiement facture',
        };
    }

    /**
     * Rapport hebdomadaire consolidé (périmètre Eleni : toutes catégories).
     *
     * @return Collection<int, SuiviPaiement>
     */
    public function lignesRapportHebdomadaire(string $dateDebut, string $dateFin): Collection
    {
        return SuiviPaiement::query()
            ->with(['courrier', 'etabliPar', 'responsableDossier'])
            ->whereDate('date_suivi', '>=', $dateDebut)
            ->whereDate('date_suivi', '<=', $dateFin)
            ->orderBy('date_suivi')
            ->orderBy('type')
            ->orderBy('numero_ligne')
            ->get();
    }

    /**
     * @param  Collection<int, SuiviPaiement>  $lignes
     */
    public function exporterRapportHebdomadaireCsv(Collection $lignes, string $dateDebut, string $dateFin): StreamedResponse
    {
        $filename = 'rapport-depenses-eleni_'.$dateDebut.'_'.$dateFin.'_'.date('Y-m-d_His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($lignes, $dateDebut, $dateFin): void {
            $stream = fopen('php://output', 'w');
            fprintf($stream, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($stream, [
                'Rapport hebdomadaire suivi des dépenses',
                'Du '.$dateDebut.' au '.$dateFin,
            ], ';');
            fputcsv($stream, [], ';');
            fputcsv($stream, [
                'Type', 'N°', 'Date', 'Intitulé', 'Montant', 'Bénéficiaire / Fournisseur',
                'N° pièce', 'Origine', 'Instruction DG', 'Observation', 'Courrier',
            ], ';');

            foreach ($lignes as $ligne) {
                fputcsv($stream, [
                    $this->libellePourType($ligne->type),
                    $ligne->numeroComplet(),
                    $ligne->date_suivi->format('d/m/Y'),
                    $ligne->intitule,
                    $this->formaterMontant($ligne->montant),
                    $ligne->beneficiaire_libelle
                        ?? $ligne->fournisseur_libelle
                        ?? $ligne->demandeur_libelle
                        ?? '',
                    $ligne->numero_piece ?? '',
                    $ligne->origine === SuiviPaiement::ORIGINE_REMISE_DG ? 'Manuel' : 'Circuit',
                    $ligne->instruction_dg ?? '',
                    $ligne->observation ?? '',
                    $ligne->courrier?->numeroRegistreComplet() ?? '',
                ], ';');
            }

            $total = $lignes->sum(fn (SuiviPaiement $ligne) => (float) $ligne->montant);
            fputcsv($stream, [], ';');
            fputcsv($stream, ['Total', '', '', '', $this->formaterMontant($total)], ';');
            fputcsv($stream, ['Nombre de lignes', (string) $lignes->count()], ';');

            fclose($stream);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Enregistre la date de décharge (+ observation) sans modifier les références du chèque.
     *
     * @param  array{
     *     date_decharge: string,
     *     observation?: ?string
     * }  $donnees
     */
    public function enregistrerDechargeBordereau(Courrier $courrier, array $donnees): SuiviPaiement
    {
        $suivi = SuiviPaiement::query()->where('courrier_id', $courrier->id)->first();

        if (! $suivi) {
            throw new InvalidArgumentException('Aucune fiche de suivi des paiements pour ce courrier.');
        }

        $observation = trim((string) ($donnees['observation'] ?? ''));

        $suivi->update([
            'date_decharge' => $donnees['date_decharge'],
            'observation' => $observation !== '' ? $observation : $suivi->observation,
        ]);

        return $suivi->fresh();
    }

    public function marquerControleEffectue(Courrier $courrier, User $acteur): void
    {
        SuiviPaiement::query()
            ->where('courrier_id', $courrier->id)
            ->update([
                'controle_par_id' => $acteur->id,
                'controle_at' => now(),
            ]);
    }

    /**
     * Enregistre l’observation FSP lors du dépôt de la preuve de paiement.
     */
    public function enregistrerObservation(Courrier $courrier, ?string $observation): void
    {
        $texte = trim((string) $observation);
        if ($texte === '') {
            return;
        }

        SuiviPaiement::query()
            ->where('courrier_id', $courrier->id)
            ->update(['observation' => $texte]);
    }

    /**
     * Liste unifiée Suivi de dépense (toutes catégories / origines).
     *
     * @return Builder<SuiviPaiement>
     */
    public function requeteListeUnifiee(Request $request): Builder
    {
        $query = SuiviPaiement::query()
            ->with(['courrier.dossier', 'categorieDepense', 'responsableDossier', 'etabliPar', 'dossier'])
            ->orderByDesc('id');

        if ($request->filled('annee')) {
            $query->where('numero_annee', (int) $request->get('annee'));
        }

        if ($request->filled('categorie_depense_id')) {
            $query->where('categorie_depense_id', (int) $request->get('categorie_depense_id'));
        }

        if ($request->filled('date_debut')) {
            $query->whereDate('date_suivi', '>=', $request->get('date_debut'));
        }

        if ($request->filled('date_fin')) {
            $query->whereDate('date_suivi', '<=', $request->get('date_fin'));
        }

        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function (Builder $sub) use ($q): void {
                $sub->where('intitule', 'like', "%{$q}%")
                    ->orWhere('fournisseur_libelle', 'like', "%{$q}%")
                    ->orWhere('beneficiaire_libelle', 'like', "%{$q}%")
                    ->orWhere('service_demandeur_libelle', 'like', "%{$q}%")
                    ->orWhere('demandeur_libelle', 'like', "%{$q}%")
                    ->orWhere('numero_piece', 'like', "%{$q}%")
                    ->orWhere('instruction_dg', 'like', "%{$q}%")
                    ->orWhere('observation', 'like', "%{$q}%")
                    ->orWhereHas('categorieDepense', fn (Builder $c) => $c->where('libelle', 'like', "%{$q}%"));
            });
        }

        return $query;
    }

    /**
     * @return list<int>
     */
    public function anneesDisponiblesUnifiees(): array
    {
        $annees = SuiviPaiement::query()
            ->select('numero_annee')
            ->distinct()
            ->orderByDesc('numero_annee')
            ->pluck('numero_annee')
            ->map(fn ($a) => (int) $a)
            ->all();

        if ($annees === []) {
            return [(int) now()->year];
        }

        return $annees;
    }

    /**
     * @param  Collection<int, SuiviPaiement>  $lignes
     */
    public function exporterCsvUnifie(Collection $lignes, int $annee): StreamedResponse
    {
        $filename = 'suivi-depenses_'.$annee.'_'.date('Y-m-d_His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($lignes, $annee): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Suivi de dépense', (string) $annee], ';');
            fputcsv($stream, [
                'Ref pièce', 'Date', 'Catégorie', 'Intitulé', 'Montant', 'Bénéficiaire / Fournisseur',
                'Instruction DG', 'Observation', 'Origine',
            ], ';');

            foreach ($lignes as $ligne) {
                fputcsv($stream, [
                    $ligne->numero_piece ?? '',
                    $ligne->date_suivi?->format('d/m/Y') ?? '',
                    $ligne->categorieDepense?->libelle ?? $this->libellePourType($ligne->type),
                    $ligne->intitule,
                    $this->formaterMontant($ligne->montant),
                    $ligne->beneficiaire_libelle ?: ($ligne->fournisseur_libelle ?? ''),
                    $ligne->instruction_dg ?? '',
                    $ligne->observation ?? '',
                    $ligne->origine === SuiviPaiement::ORIGINE_CIRCUIT_CHEQUE ? 'Circuit chèque' : 'Saisie manuelle',
                ], ';');
            }

            $total = $lignes->sum(fn (SuiviPaiement $l) => (float) $l->montant);
            fputcsv($stream, [], ';');
            fputcsv($stream, ['Total', '', '', '', $this->formaterMontant($total)], ';');
            fclose($stream);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * @return Builder<SuiviPaiement>
     */
    public function requeteListe(Request $request, string $type): Builder
    {
        $annee = (int) $request->get('annee', now()->year);

        $query = SuiviPaiement::query()
            ->with(['courrier', 'categorieDepense', 'responsableDossier', 'etabliPar'])
            ->where('type', $type)
            ->where('numero_annee', $annee)
            ->orderBy('numero_ligne');

        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function (Builder $sub) use ($q, $type): void {
                $sub->where('intitule', 'like', "%{$q}%")
                    ->orWhere('fournisseur_libelle', 'like', "%{$q}%")
                    ->orWhere('service_demandeur_libelle', 'like', "%{$q}%")
                    ->orWhere('demandeur_libelle', 'like', "%{$q}%")
                    ->orWhere('instruction_dg', 'like', "%{$q}%")
                    ->orWhere('observation', 'like', "%{$q}%");

                if ($type === SuiviPaiement::TYPE_FSP_MAD) {
                    $sub->orWhereHas('responsableDossier', fn (Builder $user) => $user->where('name', 'like', "%{$q}%"));
                }
            });
        }

        return $query;
    }

    /**
     * @return Collection<int, int>
     */
    public function anneesDisponibles(string $type): Collection
    {
        $annees = SuiviPaiement::query()
            ->where('type', $type)
            ->select('numero_annee')
            ->distinct()
            ->orderByDesc('numero_annee')
            ->pluck('numero_annee');

        if ($annees->isEmpty()) {
            return collect([(int) now()->year]);
        }

        return $annees;
    }

    public function formaterMontant(float|string $montant): string
    {
        return MontantFcfa::formater($montant);
    }

    /**
     * @param  Collection<int, SuiviPaiement>  $lignes
     */
    public function exporterCsv(string $type, Collection $lignes, int $annee): StreamedResponse
    {
        $libelleType = str_replace(' ', '_', $this->libellePourType($type));
        $filename = strtolower($libelleType).'_'.$annee.'_'.date('Y-m-d_His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($type, $lignes): void {
            $stream = fopen('php://output', 'w');
            fprintf($stream, chr(0xEF).chr(0xBB).chr(0xBF));

            if ($type === SuiviPaiement::TYPE_FSP_MAD) {
                fputcsv($stream, [
                    'N°', 'Date', 'Intitulé', 'Montant', 'Demandeur',
                    'Responsable chargé du dossier', 'Instruction du DG', 'Observation',
                ], ';');

                foreach ($lignes as $ligne) {
                    fputcsv($stream, [
                        $ligne->numeroComplet(),
                        $ligne->date_suivi->format('d/m/Y'),
                        $ligne->intitule,
                        $this->formaterMontant($ligne->montant),
                        $ligne->demandeur_libelle ?? '',
                        $ligne->responsableDossier?->name ?? '',
                        $ligne->instruction_dg ?? '',
                        $ligne->observation ?? '',
                    ], ';');
                }
            } elseif (in_array($type, SuiviPaiement::typesRemiseDg(), true)) {
                fputcsv($stream, [
                    'N°', 'Date', 'Intitulé', 'Montant', 'Bénéficiaire',
                    'N° pièce', 'Instruction du DG', 'Observation',
                ], ';');

                foreach ($lignes as $ligne) {
                    fputcsv($stream, [
                        $ligne->numeroComplet(),
                        $ligne->date_suivi->format('d/m/Y'),
                        $ligne->intitule,
                        $this->formaterMontant($ligne->montant),
                        $ligne->beneficiaire_libelle ?? '',
                        $ligne->numero_piece ?? '',
                        $ligne->instruction_dg ?? '',
                        $ligne->observation ?? '',
                    ], ';');
                }
            } else {
                fputcsv($stream, [
                    'N°', 'Date', 'Intitulé', 'Montant', 'Fournisseur',
                    'Service demandeur', 'Instruction du DG', 'Observation',
                ], ';');

                foreach ($lignes as $ligne) {
                    fputcsv($stream, [
                        $ligne->numeroComplet(),
                        $ligne->date_suivi->format('d/m/Y'),
                        $ligne->intitule,
                        $this->formaterMontant($ligne->montant),
                        $ligne->fournisseur_libelle ?? '',
                        $ligne->service_demandeur_libelle ?? '',
                        $ligne->instruction_dg ?? '',
                        $ligne->observation ?? '',
                    ], ';');
                }
            }

            $total = $lignes->sum(fn (SuiviPaiement $ligne) => (float) $ligne->montant);
            fputcsv($stream, [], ';');
            fputcsv($stream, ['Total', '', '', $this->formaterMontant($total)], ';');

            fclose($stream);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function resoudreTypeDepuisRequete(Request $request): string
    {
        $type = $request->string('type')->toString();

        return in_array($type, SuiviPaiement::tousLesTypes(), true)
            ? $type
            : SuiviPaiement::TYPE_FSP_FACTURE;
    }

    /**
     * Bordereau de transmission : chèques circuit (dès envoi AC → DG, y compris en attente de décharge).
     *
     * @return Builder<SuiviPaiement>
     */
    public function requeteBordereauTransmission(Request $request): Builder
    {
        $query = SuiviPaiement::query()
            ->with(['courrier.circuitEtapeActuelle', 'etabliPar'])
            ->where('origine', SuiviPaiement::ORIGINE_CIRCUIT_CHEQUE)
            ->whereNotNull('numero_piece')
            ->where('numero_piece', '!=', '')
            ->orderByDesc('date_suivi')
            ->orderByDesc('id');

        if ($request->filled('annee')) {
            $query->where('numero_annee', (int) $request->get('annee'));
        }

        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function (Builder $sub) use ($q): void {
                $sub->where('numero_piece', 'like', "%{$q}%")
                    ->orWhere('banque', 'like', "%{$q}%")
                    ->orWhere('beneficiaire_libelle', 'like', "%{$q}%")
                    ->orWhere('intitule', 'like', "%{$q}%")
                    ->orWhere('programmation', 'like', "%{$q}%");
            });
        }

        return $query;
    }

    /**
     * @param  Collection<int, SuiviPaiement>  $lignes
     * @return Collection<int, array{debut: Carbon, fin: Carbon, libelle: string, lignes: Collection<int, SuiviPaiement>, total: float}>
     */
    public function grouperBordereauParSemaine(Collection $lignes): Collection
    {
        return $this->grouperBordereauParPeriode($lignes, 'hebdomadaire');
    }

    /**
     * Regroupe le bordereau selon la périodicité choisie.
     *
     * @param  Collection<int, SuiviPaiement>  $lignes
     * @param  'hebdomadaire'|'mensuel'|'trimestriel'  $periode
     * @return Collection<int, array{debut: Carbon, fin: Carbon, libelle: string, lignes: Collection<int, SuiviPaiement>, total: float}>
     */
    public function grouperBordereauParPeriode(Collection $lignes, string $periode = 'hebdomadaire'): Collection
    {
        $periode = in_array($periode, ['hebdomadaire', 'mensuel', 'trimestriel'], true)
            ? $periode
            : 'hebdomadaire';

        $groupes = $lignes
            ->groupBy(fn (SuiviPaiement $ligne): string => $this->cleGroupeBordereau($ligne, $periode))
            ->sortKeysDesc();

        return $groupes->map(function (Collection $groupeLignes, string $cle) use ($periode): array {
            [$debut, $fin, $libelle] = $this->borneEtLibelleGroupeBordereau($cle, $periode);

            return [
                'debut' => $debut,
                'fin' => $fin,
                'libelle' => $libelle,
                'lignes' => $groupeLignes->sortBy(fn (SuiviPaiement $l) => $l->date_suivi?->format('Y-m-d').'-'.$l->id)->values(),
                'total' => (float) $groupeLignes->sum(fn (SuiviPaiement $l) => (float) $l->montant),
            ];
        })->values();
    }

    /**
     * @return list<'hebdomadaire'|'mensuel'|'trimestriel'>
     */
    public function periodesBordereauDisponibles(): array
    {
        return ['hebdomadaire', 'mensuel', 'trimestriel'];
    }

    public function libellePeriodeBordereau(string $periode): string
    {
        return match ($periode) {
            'mensuel' => 'Mensuel',
            'trimestriel' => 'Trimestriel',
            default => 'Hebdomadaire',
        };
    }

    private function cleGroupeBordereau(SuiviPaiement $ligne, string $periode): string
    {
        $date = ($ligne->date_suivi ?? now())->copy();

        return match ($periode) {
            'mensuel' => $date->format('Y-m'),
            'trimestriel' => $date->format('Y').'-Q'.$date->quarter,
            default => $date->startOfWeek(Carbon::MONDAY)->format('Y-m-d'),
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function borneEtLibelleGroupeBordereau(string $cle, string $periode): array
    {
        if ($periode === 'mensuel') {
            $debut = Carbon::createFromFormat('Y-m', $cle)->locale('fr')->startOfMonth();
            $fin = $debut->copy()->endOfMonth();

            return [
                $debut,
                $fin,
                'Total mensuel — '.$debut->translatedFormat('F Y'),
            ];
        }

        if ($periode === 'trimestriel') {
            [$annee, $trimestre] = explode('-Q', $cle);
            $trimestre = (int) $trimestre;
            $moisDebut = (($trimestre - 1) * 3) + 1;
            $debut = Carbon::create((int) $annee, $moisDebut, 1)->locale('fr')->startOfMonth();
            $fin = $debut->copy()->addMonths(2)->endOfMonth();
            $moisLibelle = $debut->translatedFormat('F').'–'.$fin->translatedFormat('F Y');

            return [
                $debut,
                $fin,
                'Total trimestriel — T'.$trimestre.' '.$annee.' ('.$moisLibelle.')',
            ];
        }

        $debut = Carbon::parse($cle)->startOfWeek(Carbon::MONDAY);
        $fin = $debut->copy()->endOfWeek(Carbon::SUNDAY);

        return [
            $debut,
            $fin,
            'Total hebdomadaire du '.$debut->format('d/m/Y').' au '.$fin->format('d/m/Y'),
        ];
    }

    public function statutBordereau(SuiviPaiement $ligne): string
    {
        if ($ligne->date_decharge) {
            return 'Déchargé';
        }

        $etape = $ligne->courrier?->circuitEtapeActuelle?->code;

        return match ($etape) {
            'dg_signe_cheque' => 'Signature DG',
            'preuve_paiement' => 'Attente décharge',
            'cloture_depenses' => 'Contrôle dépenses (obsolète)',
            default => 'En circuit',
        };
    }

    /**
     * @param  Collection<int, SuiviPaiement>  $lignes
     */
    public function exporterBordereauCsv(Collection $lignes, int $annee, string $periode = 'hebdomadaire'): StreamedResponse
    {
        $filename = 'bordereau-transmission-'.$annee.'-'.$periode.'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $sections = $this->grouperBordereauParPeriode($lignes, $periode);

        $callback = function () use ($sections, $annee, $periode): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['BORDEREAU DE TRANSMISSION', (string) $annee, $this->libellePeriodeBordereau($periode)], ';');
            fputcsv($stream, ['Date', 'N° Pièce', 'Montant', 'Banque', 'Bénéficiaire', 'Programmation', 'Statut'], ';');

            foreach ($sections as $section) {
                foreach ($section['lignes'] as $ligne) {
                    fputcsv($stream, [
                        $ligne->date_suivi?->format('d/m/Y') ?? '',
                        $ligne->numero_piece ?? '',
                        $this->formaterMontant($ligne->montant),
                        $ligne->banque ?? '',
                        $ligne->beneficiaire_libelle ?? '',
                        $ligne->programmation ?? '',
                        $this->statutBordereau($ligne),
                    ], ';');
                }
                fputcsv($stream, [
                    $section['libelle'],
                    '',
                    $this->formaterMontant($section['total']),
                    '',
                    '',
                    '',
                    '',
                ], ';');
                fputcsv($stream, [], ';');
            }

            fclose($stream);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * @return list<int>
     */
    public function anneesBordereauDisponibles(): array
    {
        return SuiviPaiement::query()
            ->where('origine', SuiviPaiement::ORIGINE_CIRCUIT_CHEQUE)
            ->whereNotNull('numero_piece')
            ->distinct()
            ->orderByDesc('numero_annee')
            ->pluck('numero_annee')
            ->map(fn ($a) => (int) $a)
            ->unique()
            ->values()
            ->all();
    }
}
