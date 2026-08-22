<?php

namespace App\Services;

use App\Models\Courrier;
use App\Models\SuiviPaiement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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
     * Crée une ligne FSP à l’établissement du chèque par l’AC.
     */
    public function creerDepuisEntreeCheque(Courrier $courrier, User $acteur, float $montant): SuiviPaiement
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

        return DB::transaction(function () use ($courrier, $acteur, $montant, $type, $annee): SuiviPaiement {
            Courrier::query()->whereKey($courrier->id)->lockForUpdate()->first();

            if (SuiviPaiement::query()->where('courrier_id', $courrier->id)->exists()) {
                throw new InvalidArgumentException('Une fiche de suivi existe déjà pour ce courrier.');
            }

            $numeroLigne = (int) SuiviPaiement::query()
                ->where('type', $type)
                ->where('numero_annee', $annee)
                ->lockForUpdate()
                ->max('numero_ligne') + 1;

            $serviceDemandeur = $courrier->serviceDemandeurStructure?->nom
                ?? $courrier->structureDestinataire?->nom
                ?? $courrier->structure?->nom;

            $data = [
                'courrier_id' => $courrier->id,
                'type' => $type,
                'numero_ligne' => $numeroLigne,
                'numero_annee' => $annee,
                'date_suivi' => now()->toDateString(),
                'intitule' => (string) $courrier->objet,
                'montant' => $montant,
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

            return SuiviPaiement::query()->create($data);
        });
    }

    /**
     * Complète la fiche FSP avec le bordereau de transmission (décharge bénéficiaire).
     *
     * @param  array{
     *     date_decharge: string,
     *     numero_piece: string,
     *     montant: float|int|string,
     *     banque: string,
     *     beneficiaire_libelle: string,
     *     programmation?: ?string,
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
            'date_suivi' => $donnees['date_decharge'],
            'numero_piece' => $donnees['numero_piece'],
            'montant' => $donnees['montant'],
            'banque' => $donnees['banque'],
            'beneficiaire_libelle' => $donnees['beneficiaire_libelle'],
            'programmation' => $donnees['programmation'] ?? null,
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
     * @return Builder<SuiviPaiement>
     */
    public function requeteListe(Request $request, string $type): Builder
    {
        $annee = (int) $request->get('annee', now()->year);

        $query = SuiviPaiement::query()
            ->with(['courrier', 'responsableDossier', 'etabliPar'])
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
        return number_format((float) $montant, 0, ',', ' ');
    }

    /**
     * @param  Collection<int, SuiviPaiement>  $lignes
     */
    public function exporterCsv(string $type, Collection $lignes, int $annee): StreamedResponse
    {
        $libelleType = $type === SuiviPaiement::TYPE_FSP_MAD ? 'FSP_MAD' : 'FSP_FACTURE';
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
        return match ($request->string('type')->toString()) {
            SuiviPaiement::TYPE_FSP_MAD => SuiviPaiement::TYPE_FSP_MAD,
            default => SuiviPaiement::TYPE_FSP_FACTURE,
        };
    }
}
