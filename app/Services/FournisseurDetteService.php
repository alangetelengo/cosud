<?php

namespace App\Services;

use App\Models\Courrier;
use App\Models\Moratoire;
use App\Models\MoratoireEcheance;
use App\Models\SuiviPaiement;
use App\Models\TypeCourrier;
use Illuminate\Support\Collection;

/**
 * Cumul des montants facture par fournisseur et calcul de la dette COSUD.
 *
 * dette = facturé − payé (chèques déchargés / échéances de moratoire renseignées).
 */
class FournisseurDetteService
{
    public function normaliserLibelle(?string $libelle): string
    {
        $texte = mb_strtolower(trim((string) $libelle));
        $texte = preg_replace('/\s+/u', ' ', $texte) ?? '';

        return $texte;
    }

    /**
     * @return Collection<int, array{
     *     fournisseur_libelle: string,
     *     fournisseur_normalise: string,
     *     nb_factures: int,
     *     montant_facture: float,
     *     montant_paye: float,
     *     dette: float,
     *     moratoire_actif_id: int|null
     * }>
     */
    public function dettesParFournisseur(): Collection
    {
        $typeFactureId = TypeCourrier::query()->where('code', 'facture')->value('id');
        if (! $typeFactureId) {
            return collect();
        }

        $courriers = Courrier::query()
            ->with('suiviPaiement')
            ->where('type_courrier_id', $typeFactureId)
            ->whereNotNull('expediteur_libelle')
            ->where('expediteur_libelle', '!=', '')
            ->get(['id', 'expediteur_libelle', 'montant_facture']);

        $groupes = $courriers->groupBy(fn (Courrier $c) => $this->normaliserLibelle($c->expediteur_libelle));

        $paiements = SuiviPaiement::query()
            ->whereNotNull('fournisseur_libelle')
            ->where('fournisseur_libelle', '!=', '')
            ->whereNotNull('date_decharge')
            ->get(['id', 'fournisseur_libelle', 'montant']);

        $paiementsParFournisseur = $paiements->groupBy(
            fn (SuiviPaiement $p) => $this->normaliserLibelle($p->fournisseur_libelle)
        );

        $echeancesMoratoire = MoratoireEcheance::query()
            ->with('moratoire:id,fournisseur_normalise,statut')
            ->whereHas('moratoire', fn ($q) => $q->whereIn('statut', [Moratoire::STATUT_ACTIF, Moratoire::STATUT_SOLDE]))
            ->where(function ($q): void {
                $q->whereNotNull('numero_cheque')
                    ->where('numero_cheque', '!=', '')
                    ->orWhereNotNull('date_paiement')
                    ->orWhereNotNull('suivi_paiement_id');
            })
            ->get();

        $moratoirePayeParFournisseur = $echeancesMoratoire
            ->filter(fn (MoratoireEcheance $e) => $e->suivi_paiement_id === null)
            ->groupBy(fn (MoratoireEcheance $e) => (string) $e->moratoire?->fournisseur_normalise);

        $moratoiresActifs = Moratoire::query()
            ->where('statut', Moratoire::STATUT_ACTIF)
            ->get(['id', 'fournisseur_normalise'])
            ->keyBy('fournisseur_normalise');

        return $groupes
            ->map(function (Collection $lignes, string $cle) use ($paiementsParFournisseur, $moratoirePayeParFournisseur, $moratoiresActifs) {
                /** @var Courrier $premier */
                $premier = $lignes->first();
                $libelleAffiche = trim((string) $premier->expediteur_libelle);

                $montantFacture = (float) $lignes->sum(function (Courrier $c): float {
                    if ($c->montant_facture !== null) {
                        return (float) $c->montant_facture;
                    }

                    return (float) ($c->suiviPaiement?->montant ?? 0);
                });

                $montantPayeCheques = (float) ($paiementsParFournisseur->get($cle)?->sum('montant') ?? 0);
                $montantPayeMoratoire = (float) ($moratoirePayeParFournisseur->get($cle)?->sum('montant_echeance') ?? 0);
                $montantPaye = $montantPayeCheques + $montantPayeMoratoire;

                return [
                    'fournisseur_libelle' => $libelleAffiche,
                    'fournisseur_normalise' => $cle,
                    'nb_factures' => $lignes->count(),
                    'montant_facture' => $montantFacture,
                    'montant_paye' => $montantPaye,
                    'dette' => max(0, round($montantFacture - $montantPaye, 2)),
                    'moratoire_actif_id' => $moratoiresActifs->get($cle)?->id,
                ];
            })
            ->filter(fn (array $row) => $row['montant_facture'] > 0 || $row['dette'] > 0)
            ->sortByDesc('dette')
            ->values();
    }

    /**
     * @return array{
     *     fournisseur_libelle: string,
     *     fournisseur_normalise: string,
     *     nb_factures: int,
     *     montant_facture: float,
     *     montant_paye: float,
     *     dette: float,
     *     moratoire_actif_id: int|null
     * }|null
     */
    public function dettePourFournisseur(string $libelle): ?array
    {
        $cle = $this->normaliserLibelle($libelle);
        if ($cle === '') {
            return null;
        }

        return $this->dettesParFournisseur()
            ->first(fn (array $row) => $row['fournisseur_normalise'] === $cle);
    }
}
