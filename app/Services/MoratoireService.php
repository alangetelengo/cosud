<?php

namespace App\Services;

use App\Models\Document;
use App\Models\FournisseurPrestataire;
use App\Models\Moratoire;
use App\Models\MoratoireEcheance;
use App\Models\StatutDocument;
use App\Models\TypeDocument;
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
     * @param  list<UploadedFile>  $fichiers
     */
    public function creer(User $acteur, array $donnees, array $fichiers = []): Moratoire
    {
        $fournisseur = trim($donnees['fournisseur_libelle']);
        $echeance = (float) $donnees['montant_echeance_defaut'];
        $fichiers = array_values(array_filter($fichiers));

        if ($fournisseur === '' || $echeance <= 0) {
            throw new InvalidArgumentException('Fournisseur et échéance doivent être renseignés.');
        }

        $detteInfo = $this->detteService->dettePourFournisseur($fournisseur);
        if (! $detteInfo || $detteInfo['dette'] <= 0) {
            throw new InvalidArgumentException(
                'Ce fournisseur n’a pas de dette enregistrée. Les dettes sont issues des factures saisies par le responsable Factures / prestataires.'
            );
        }

        if ($detteInfo['moratoire_actif_id']) {
            throw new InvalidArgumentException('Un moratoire actif existe déjà pour ce fournisseur.');
        }

        $fournisseur = $detteInfo['fournisseur_libelle'];
        $dette = (float) $detteInfo['dette'];
        $normalise = $detteInfo['fournisseur_normalise'];

        if ($fichiers === []) {
            throw new InvalidArgumentException('Au moins une pièce d’instruction du DG est obligatoire.');
        }

        if ($echeance > $dette) {
            throw new InvalidArgumentException('Le montant d’échéance ne peut pas dépasser la dette initiale.');
        }

        $existant = Moratoire::query()
            ->where('fournisseur_normalise', $normalise)
            ->where('statut', Moratoire::STATUT_ACTIF)
            ->exists();

        if ($existant) {
            throw new InvalidArgumentException('Un moratoire actif existe déjà pour ce fournisseur.');
        }

        $lignes = $this->genererLignesEcheancier($dette, $echeance);

        return DB::transaction(function () use ($acteur, $donnees, $fournisseur, $normalise, $dette, $echeance, $lignes, $fichiers): Moratoire {
            $fiche = FournisseurPrestataire::query()
                ->where('nom_normalise', $normalise)
                ->first();

            $moratoire = Moratoire::query()->create([
                'fournisseur_libelle' => $fournisseur,
                'fournisseur_normalise' => $normalise,
                'fournisseur_prestataire_id' => $fiche?->id,
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

            foreach ($fichiers as $index => $fichier) {
                $this->attacherJustificatifDette($moratoire, $acteur, $fichier, $index === 0);
            }

            return $moratoire->load(['echeances', 'documents']);
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

        if ($reste > 0) {
            throw new InvalidArgumentException(
                'L’échéancier dépasse 500 échéances : augmentez le montant d’échéance ou réduisez la dette initiale.'
            );
        }

        return $lignes;
    }

    /**
     * @param  array{
     *     mode_paiement?: ?string,
     *     numero_cheque?: ?string,
     *     banque?: ?string,
     *     observation?: ?string,
     *     date_paiement?: ?string,
     *     periode_mois?: ?string,
     *     periode_annee?: int|string|null
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
                'mode_paiement' => isset($donnees['mode_paiement'])
                    ? $donnees['mode_paiement']
                    : ($echeance->mode_paiement ?? MoratoireEcheance::MODE_CHEQUE),
                'numero_cheque' => isset($donnees['numero_cheque']) ? trim((string) $donnees['numero_cheque']) : $echeance->numero_cheque,
                'banque' => isset($donnees['banque']) ? trim((string) $donnees['banque']) : $echeance->banque,
                'observation' => array_key_exists('observation', $donnees)
                    ? $donnees['observation']
                    : $echeance->observation,
                'date_paiement' => $donnees['date_paiement'] ?? $echeance->date_paiement,
                'periode_mois' => $donnees['periode_mois'] ?? $echeance->periode_mois,
                'periode_annee' => isset($donnees['periode_annee'])
                    ? (int) $donnees['periode_annee']
                    : $echeance->periode_annee,
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

    private function attacherJustificatifDette(
        Moratoire $moratoire,
        User $acteur,
        UploadedFile $fichier,
        bool $principal,
    ): void {
        $typeDoc = TypeDocument::query()
            ->whereIn('code', ['PDF', 'COURRIER_IN', 'COURRIER', 'LETTRE'])
            ->where('actif', true)
            ->orderByRaw("CASE WHEN code = 'PDF' THEN 0 WHEN code LIKE 'COURRIER_%' THEN 1 ELSE 2 END")
            ->first();

        if (! $typeDoc) {
            throw new \RuntimeException('Aucun type de document disponible pour l’instruction du DG.');
        }

        $statut = StatutDocument::query()->where('code', 'brouillon')->first();
        $chemin = $fichier->store('documents/moratoires/'.date('Y/m'), 'public');

        $document = Document::query()->create([
            'type_document_id' => $typeDoc->id,
            'user_id' => $acteur->id,
            'createur_id' => $acteur->id,
            'proprietaire_id' => $acteur->id,
            'dossier_id' => null,
            'nom_original' => $fichier->getClientOriginalName(),
            'chemin' => $chemin,
            'extension' => $fichier->getClientOriginalExtension(),
            'taille_octets' => $fichier->getSize(),
            'mime_type' => $fichier->getMimeType(),
            'titre' => 'Instruction DG — '.$moratoire->fournisseur_libelle,
            'description' => 'Pièce justificative de la dette jointe à la création du moratoire',
            'statut' => 'brouillon',
            'statut_document_id' => $statut?->id,
            'en_corbeille' => false,
            'confidentiel' => false,
        ]);

        $moratoire->documents()->attach($document->id, ['est_principal' => $principal]);
    }
}
