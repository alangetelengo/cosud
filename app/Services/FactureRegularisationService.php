<?php

namespace App\Services;

use App\Models\CategorieDepense;
use App\Models\Courrier;
use App\Models\Document;
use App\Models\JournalAudit;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Enregistrement hors circuit des factures historiques (payées ou impayées).
 */
class FactureRegularisationService
{
    public const PAIEMENT_IMPAYEE = 'impayee';

    public const PAIEMENT_PAYEE = 'payee';

    public function __construct(
        private readonly CourrierNumeroRegistreService $numeroService,
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
     *     numero_piece?: ?string,
     *     banque?: ?string,
     *     date_paiement?: ?string,
     *     observation?: ?string,
     *     fichiers?: list<UploadedFile>
     * }  $donnees
     */
    public function enregistrer(User $acteur, array $donnees): Courrier
    {
        $paiement = $donnees['paiement'] ?? self::PAIEMENT_IMPAYEE;
        if (! in_array($paiement, [self::PAIEMENT_IMPAYEE, self::PAIEMENT_PAYEE], true)) {
            throw new InvalidArgumentException('Statut de paiement de régularisation invalide.');
        }

        $montant = MontantFcfa::versFloat($donnees['montant_facture']);
        if ($montant <= 0) {
            throw new InvalidArgumentException('Le montant de la facture doit être strictement positif.');
        }

        $fournisseur = trim((string) $donnees['fournisseur_libelle']);
        if ($fournisseur === '') {
            throw new InvalidArgumentException('Le fournisseur est obligatoire.');
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

        return DB::transaction(function () use ($acteur, $donnees, $paiement, $montant, $fournisseur, $sens, $type, $statut, $prioriteId, $fichiers): Courrier {
            $nums = $this->numeroService->prochainNumero((int) $sens->id);
            $annee = (int) ($nums['numero_registre_annee'] ?? now()->year);
            $numero = (int) $nums['numero_registre'];

            $objet = trim((string) ($donnees['objet'] ?? ''));
            if ($objet === '') {
                $objet = 'Régularisation facture — '.$fournisseur;
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
                'est_expediteur_externe' => true,
                'service_demandeur_structure_id' => $donnees['service_demandeur_structure_id'] ?? null,
                'objet' => $objet,
                'montant_facture' => $montant,
                'est_regularisation' => true,
                'regularisation_paiement' => $paiement,
                'observations' => $donnees['observation'] ?? 'Enregistrement hors circuit (régularisation historique).',
                'createur_id' => $acteur->id,
                'structure_id' => $acteur->structure_id,
            ]);

            foreach ($fichiers as $index => $fichier) {
                $this->attacherScan($courrier, $acteur, $fichier, $index === 0);
            }

            if ($paiement === self::PAIEMENT_PAYEE) {
                $suivi = $this->creerSuiviPaiementPaye($courrier, $acteur, $montant, $donnees);

                if ($fichiers !== []) {
                    app(SuiviDepenseClassementService::class)->deposerJustificatifsEnAttente(
                        $suivi,
                        $acteur,
                        $fichiers,
                    );
                }
            }

            JournalAudit::log('courrier.regularisation', 'courriers', [
                'commentaire' => json_encode([
                    'courrier_id' => $courrier->id,
                    'paiement' => $paiement,
                    'montant' => $montant,
                    'fournisseur' => $fournisseur,
                ]),
            ]);

            return $courrier->fresh(['typeCourrier', 'statutCourrier', 'suiviPaiement', 'documents']);
        });
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
            'observation' => 'Paiement historique saisi en régularisation.',
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

        if (in_array($paiement, [self::PAIEMENT_IMPAYEE, self::PAIEMENT_PAYEE], true)) {
            $query->where('regularisation_paiement', $paiement);
        }

        return $query->paginate(25)->withQueryString();
    }
}
