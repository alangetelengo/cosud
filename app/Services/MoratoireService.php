<?php

namespace App\Services;

use App\Models\Moratoire;
use App\Models\MoratoireEcheance;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MoratoireService
{
    public function __construct(
        private readonly FournisseurDetteService $detteService,
        private readonly SuiviPaiementService $suiviPaiements,
        private readonly SuiviDepenseClassementService $classementDepenses,
    ) {}

    /**
     * @param  array{
     *     fournisseur_libelle: string,
     *     montant_dette_initial: float,
     *     montant_echeance_defaut: float,
     *     lieu?: ?string,
     *     date_document?: ?string,
     *     signataire_libelle?: ?string,
     *     observation?: ?string
     * }  $donnees
     */
    public function creer(User $acteur, array $donnees): Moratoire
    {
        $fournisseur = trim($donnees['fournisseur_libelle']);
        $dette = (float) $donnees['montant_dette_initial'];
        $echeance = (float) $donnees['montant_echeance_defaut'];

        if ($fournisseur === '' || $dette <= 0 || $echeance <= 0) {
            throw new InvalidArgumentException('Fournisseur, dette initiale et échéance doivent être renseignés.');
        }

        if ($echeance > $dette) {
            throw new InvalidArgumentException('Le montant d’échéance ne peut pas dépasser la dette initiale.');
        }

        $normalise = $this->detteService->normaliserLibelle($fournisseur);

        $existant = Moratoire::query()
            ->where('fournisseur_normalise', $normalise)
            ->where('statut', Moratoire::STATUT_ACTIF)
            ->exists();

        if ($existant) {
            throw new InvalidArgumentException('Un moratoire actif existe déjà pour ce fournisseur.');
        }

        $lignes = $this->genererLignesEcheancier($dette, $echeance);

        return DB::transaction(function () use ($acteur, $donnees, $fournisseur, $normalise, $dette, $echeance, $lignes): Moratoire {
            $moratoire = Moratoire::query()->create([
                'fournisseur_libelle' => $fournisseur,
                'fournisseur_normalise' => $normalise,
                'montant_dette_initial' => $dette,
                'montant_echeance_defaut' => $echeance,
                'statut' => Moratoire::STATUT_ACTIF,
                'lieu' => $donnees['lieu'] ?? 'Brazzaville',
                'date_document' => $donnees['date_document'] ?? null,
                'signataire_libelle' => $donnees['signataire_libelle'] ?? $acteur->name,
                'observation' => $donnees['observation'] ?? null,
                'created_by' => $acteur->id,
            ]);

            foreach ($lignes as $ligne) {
                $moratoire->echeances()->create($ligne);
            }

            return $moratoire->load('echeances');
        });
    }

    /**
     * @return list<array{numero: int, montant_dette: float, montant_echeance: float, solde: float}>
     */
    public function genererLignesEcheancier(float $detteInitiale, float $montantEcheance): array
    {
        if ($detteInitiale <= 0 || $montantEcheance <= 0) {
            throw new InvalidArgumentException('Montants invalides pour l’échéancier.');
        }

        $lignes = [];
        $reste = round($detteInitiale, 2);
        $numero = 1;
        $maxLignes = 500;

        while ($reste > 0 && $numero <= $maxLignes) {
            $versement = min($montantEcheance, $reste);
            $versement = round($versement, 2);
            $solde = round($reste - $versement, 2);

            if ($solde < 0.005) {
                $solde = 0.0;
                $versement = $reste;
            }

            $lignes[] = [
                'numero' => $numero,
                'montant_dette' => $reste,
                'montant_echeance' => $versement,
                'solde' => $solde,
            ];

            $reste = $solde;
            $numero++;
        }

        return $lignes;
    }

    /**
     * @param  array{
     *     numero_cheque?: ?string,
     *     banque?: ?string,
     *     observation?: ?string,
     *     date_paiement?: ?string
     * }  $donnees
     * @param  list<UploadedFile>  $fichiers
     */
    public function enregistrerPaiementEcheance(
        MoratoireEcheance $echeance,
        User $acteur,
        array $donnees,
        array $fichiers = [],
    ): MoratoireEcheance {
        return DB::transaction(function () use ($echeance, $acteur, $donnees, $fichiers): MoratoireEcheance {
            $echeance->update([
                'numero_cheque' => isset($donnees['numero_cheque']) ? trim((string) $donnees['numero_cheque']) : $echeance->numero_cheque,
                'banque' => isset($donnees['banque']) ? trim((string) $donnees['banque']) : $echeance->banque,
                'observation' => array_key_exists('observation', $donnees)
                    ? $donnees['observation']
                    : $echeance->observation,
                'date_paiement' => $donnees['date_paiement'] ?? $echeance->date_paiement,
            ]);

            $echeance->refresh();

            if ($echeance->estPayee()) {
                if ($echeance->suivi_paiement_id) {
                    $suivi = $this->suiviPaiements->mettreAJourDepuisMoratoireEcheance($echeance);
                } else {
                    $suivi = $this->suiviPaiements->creerDepuisMoratoireEcheance($echeance, $acteur);
                    $echeance->update(['suivi_paiement_id' => $suivi->id]);
                    $echeance->refresh();
                }

                if ($fichiers !== []) {
                    $this->classementDepenses->deposerOuAjouterJustificatifs(
                        $suivi->fresh(),
                        $acteur,
                        $fichiers,
                    );
                }
            }

            $moratoire = $echeance->moratoire()->with('echeances')->first();
            if ($moratoire && $moratoire->soldeRestant() <= 0.009) {
                $moratoire->update(['statut' => Moratoire::STATUT_SOLDE]);
            }

            return $echeance->fresh(['suiviPaiement']);
        });
    }
}
