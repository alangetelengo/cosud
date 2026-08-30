<?php

namespace App\Services;

use App\Models\Courrier;
use App\Models\SuiviPaiement;
use App\Models\TypeCourrier;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Suivi des factures fournisseurs pour la responsable dossiers prestataires (Mme Taty).
 */
class SuiviFacturesFournisseursService
{
    public const STATUT_ATTENTE_AC = 'attente_ac';

    public const STATUT_CHEQUE = 'cheque';

    public const STATUT_SIGNATURE_DG = 'signature_dg';

    public const STATUT_DECHARGE = 'decharge';

    public const STATUT_CONTROLE = 'controle';

    public const STATUT_CLOTURE = 'cloture';

    public const STATUT_RELIQUAT = 'reliquat';

    /**
     * Libellés génériques pour les filtres (chèque et OV).
     *
     * @return array<string, string>
     */
    public function libellesStatuts(): array
    {
        return [
            self::STATUT_ATTENTE_AC => 'En attente AC',
            self::STATUT_CHEQUE => 'Chèque / OV en préparation',
            self::STATUT_SIGNATURE_DG => 'Signature DG',
            self::STATUT_DECHARGE => 'En attente décharge / accusé banque',
            self::STATUT_CONTROLE => 'Contrôle suivi dépenses',
            self::STATUT_RELIQUAT => 'Reliquat à payer',
            self::STATUT_CLOTURE => 'Payé / clôturé',
        ];
    }

    public function libelleStatutPour(Courrier $courrier, string $code): string
    {
        if ($courrier->estModePaiementOv()) {
            return match ($code) {
                self::STATUT_CHEQUE => 'OV en préparation',
                self::STATUT_DECHARGE => 'En attente accusé banque',
                default => $this->libellesStatuts()[$code] ?? $code,
            };
        }

        return match ($code) {
            self::STATUT_CHEQUE => 'Chèque en préparation',
            self::STATUT_DECHARGE => 'En attente décharge',
            default => $this->libellesStatuts()[$code] ?? $code,
        };
    }

    public function statutPour(Courrier $courrier): string
    {
        if (! $courrier->circuit_etape_actuelle_id) {
            $suivis = $courrier->relationLoaded('suiviPaiements')
                ? $courrier->suiviPaiements
                : $courrier->suiviPaiements()->get();

            $enAttenteControle = $suivis->contains(
                fn (SuiviPaiement $suivi): bool => $suivi->date_decharge !== null && $suivi->controle_at === null
            );

            if ($enAttenteControle) {
                return self::STATUT_CONTROLE;
            }

            if ($this->montantsSurFacture($courrier)['a_reliquat']) {
                return self::STATUT_RELIQUAT;
            }

            return self::STATUT_CLOTURE;
        }

        return match ($courrier->circuitEtapeActuelle?->code) {
            'ac_etablit_cheque' => self::STATUT_CHEQUE,
            'dg_signe_cheque' => self::STATUT_SIGNATURE_DG,
            'preuve_paiement' => self::STATUT_DECHARGE,
            'instructions_dg', 'enregistrement' => self::STATUT_ATTENTE_AC,
            default => self::STATUT_ATTENTE_AC,
        };
    }

    /**
     * Montants facture / payé déchargé / reliquat pour une facture du suivi Taty.
     *
     * Aligné sur FournisseurDetteService : seuls les SuiviPaiement avec date_decharge
     * (décharge chèque ou accusé banque OV) comptent comme payés.
     *
     * @return array{
     *     montant_facture: float,
     *     montant_paye: float,
     *     reliquat: float,
     *     a_reliquat: bool
     * }
     */
    public function montantsSurFacture(Courrier $courrier): array
    {
        $suivis = $courrier->relationLoaded('suiviPaiements')
            ? $courrier->suiviPaiements
            : $courrier->suiviPaiements()->get();

        $montantFacture = $courrier->montant_facture !== null
            ? (float) $courrier->montant_facture
            : null;
        $montantPaye = (float) $suivis
            ->filter(fn (SuiviPaiement $suivi): bool => $suivi->date_decharge !== null)
            ->sum(fn (SuiviPaiement $suivi): float => (float) ($suivi->montant ?? 0));

        if ($montantFacture === null) {
            $montantFacture = $montantPaye;
        }

        $reliquat = max(0.0, round($montantFacture - $montantPaye, 2));

        return [
            'montant_facture' => $montantFacture,
            'montant_paye' => $montantPaye,
            'reliquat' => $reliquat,
            'a_reliquat' => $montantPaye > 0.009 && $reliquat > 0.009,
        ];
    }

    public function libelleStatut(string $code): string
    {
        return $this->libellesStatuts()[$code] ?? $code;
    }

    /**
     * Factures ayant reçu le Bon pour accord DG (instructions enregistrées).
     *
     * @return Builder<Courrier>
     */
    public function requeteListe(Request $request): Builder
    {
        $typeFactureId = TypeCourrier::query()->where('code', 'facture')->value('id');

        $query = Courrier::query()
            ->with([
                'typeCourrier',
                'circuitEtapeActuelle',
                'suiviPaiement',
                'suiviPaiements',
                'serviceDemandeurStructure',
                'dossier',
            ])
            ->where('type_courrier_id', $typeFactureId)
            ->whereNotNull('instructions_dg')
            ->orderByDesc('date_orientation')
            ->orderByDesc('id');

        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function (Builder $sub) use ($q): void {
                $sub->where('objet', 'like', "%{$q}%")
                    ->orWhere('expediteur_libelle', 'like', "%{$q}%")
                    ->orWhere('numero_fulgurant', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%")
                    ->orWhere('instructions_dg', 'like', "%{$q}%");
            });
        }

        if ($request->get('periode') === 'semaine') {
            [$debut, $fin] = $this->bornesSemaineCourante();
            $query->whereBetween('date_orientation', [
                $debut->copy()->startOfDay(),
                $fin->copy()->endOfDay(),
            ]);
        } elseif ($request->get('periode') === 'mois') {
            [$debut, $fin] = $this->bornesMois($request->get('mois'));
            $query->whereBetween('date_orientation', [
                $debut->copy()->startOfDay(),
                $fin->copy()->endOfDay(),
            ]);
        } elseif ($request->get('periode') === 'annee') {
            $query->whereYear('date_orientation', (int) $request->get('annee', now()->year));
        }

        return $query;
    }

    /**
     * @return Collection<int, array{
     *     courrier: Courrier,
     *     statut: string,
     *     libelle_statut: string,
     *     montant_facture: float,
     *     montant_paye: float,
     *     reliquat: float,
     *     a_reliquat: bool
     * }>
     */
    public function lignesPourAffichage(Request $request): Collection
    {
        $lignes = $this->requeteListe($request)->get()
            ->map(function (Courrier $courrier): array {
                $statut = $this->statutPour($courrier);
                $montants = $this->montantsSurFacture($courrier);

                return [
                    'courrier' => $courrier,
                    'statut' => $statut,
                    'libelle_statut' => $this->libelleStatutPour($courrier, $statut),
                    'montant_facture' => $montants['montant_facture'],
                    'montant_paye' => $montants['montant_paye'],
                    'reliquat' => $montants['reliquat'],
                    'a_reliquat' => $montants['a_reliquat'],
                ];
            });

        if ($request->filled('statut') && $request->get('statut') !== 'tous') {
            $filtre = $request->string('statut')->toString();
            $lignes = $lignes->filter(fn (array $ligne) => $ligne['statut'] === $filtre)->values();
        }

        return $lignes;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function bornesSemaineCourante(?Carbon $reference = null): array
    {
        $ref = ($reference ?? now())->copy()->startOfDay();
        $debut = $ref->copy()->startOfWeek(Carbon::MONDAY);
        $fin = $ref->copy()->endOfWeek(Carbon::SUNDAY);

        return [$debut, $fin];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function bornesMois(?string $mois = null): array
    {
        $ref = $mois
            ? Carbon::createFromFormat('Y-m', $mois)->startOfMonth()
            : now()->copy()->startOfMonth();

        return [$ref->copy()->startOfMonth(), $ref->copy()->endOfMonth()];
    }

    public function labelPeriode(Request $request): string
    {
        return match ($request->get('periode', 'tous')) {
            'semaine' => (function () {
                [$debut, $fin] = $this->bornesSemaineCourante();

                return 'Semaine du '.$debut->format('d/m/Y').' au '.$fin->format('d/m/Y');
            })(),
            'mois' => (function () use ($request) {
                [$debut, $fin] = $this->bornesMois($request->get('mois'));

                return 'Mois de '.$debut->locale('fr')->translatedFormat('F Y');
            })(),
            'annee' => 'Année '.(int) $request->get('annee', now()->year),
            default => 'Toutes périodes',
        };
    }

    /**
     * @param  Collection<int, array{courrier: Courrier, statut: string, libelle_statut: string, montant_facture?: float, montant_paye?: float, reliquat?: float}>  $lignes
     */
    public function exportCsv(Collection $lignes, string $periodeLabel, ?string $suffixeFichier = null): StreamedResponse
    {
        $suffixe = $suffixeFichier ?: now()->format('Y-m-d');
        $filename = 'rapport-factures-fournisseurs-prestataires-'.$suffixe.'.csv';

        return response()->streamDownload(function () use ($lignes, $periodeLabel): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'N° registre',
                'Date BPA',
                'Fournisseur',
                'Objet',
                'Montant facture',
                'Montant payé',
                'Reliquat',
                'N° pièce',
                'Banque',
                'Statut paiement',
                'Étape circuit',
                'Instructions DG',
                'Service demandeur',
            ], ';');

            foreach ($lignes as $ligne) {
                /** @var Courrier $courrier */
                $courrier = $ligne['courrier'];
                $suivi = $courrier->suiviPaiement;
                $montants = isset($ligne['montant_facture'], $ligne['montant_paye'], $ligne['reliquat'])
                    ? $ligne
                    : $this->montantsSurFacture($courrier);

                fputcsv($out, [
                    $courrier->numeroRegistreComplet(),
                    $courrier->date_orientation?->format('d/m/Y') ?? '',
                    $courrier->expediteur_libelle ?? '',
                    $courrier->objet,
                    number_format((float) $montants['montant_facture'], 0, ',', ' '),
                    number_format((float) $montants['montant_paye'], 0, ',', ' '),
                    number_format((float) $montants['reliquat'], 0, ',', ' '),
                    $suivi?->numero_piece ?? '',
                    $suivi?->banque ?? '',
                    $ligne['libelle_statut'],
                    $courrier->circuitEtapeActuelle
                        ? $courrier->nomEtapeCircuitPourAffichage($courrier->circuitEtapeActuelle)
                        : 'Terminé',
                    $courrier->instructions_dg ?? '',
                    $courrier->serviceDemandeurStructure?->nom ?? '',
                ], ';');
            }

            fputcsv($out, [], ';');
            fputcsv($out, ['Période', $periodeLabel], ';');
            fputcsv($out, ['Exporté le', now()->format('d/m/Y H:i')], ';');
            fputcsv($out, ['Nombre de factures', (string) $lignes->count()], ';');
            fputcsv($out, ['Total factures', number_format($this->totalMontants($lignes), 0, ',', ' ')], ';');
            fputcsv($out, ['Total payé', number_format($this->totalPaye($lignes), 0, ',', ' ')], ';');
            fputcsv($out, ['Total reliquats', number_format($this->totalReliquats($lignes), 0, ',', ' ')], ';');
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function formaterMontant(float|string|null $montant): string
    {
        if ($montant === null || $montant === '') {
            return '—';
        }

        return number_format((float) $montant, 0, ',', ' ');
    }

    /**
     * @param  Collection<int, array{courrier: Courrier, montant_facture?: float}>  $lignes
     */
    public function totalMontants(Collection $lignes): float
    {
        return (float) $lignes->sum(function (array $ligne): float {
            if (array_key_exists('montant_facture', $ligne)) {
                return (float) $ligne['montant_facture'];
            }

            return $this->montantsSurFacture($ligne['courrier'])['montant_facture'];
        });
    }

    /**
     * @param  Collection<int, array{courrier: Courrier, montant_paye?: float}>  $lignes
     */
    public function totalPaye(Collection $lignes): float
    {
        return (float) $lignes->sum(function (array $ligne): float {
            if (array_key_exists('montant_paye', $ligne)) {
                return (float) $ligne['montant_paye'];
            }

            return $this->montantsSurFacture($ligne['courrier'])['montant_paye'];
        });
    }

    /**
     * @param  Collection<int, array{courrier: Courrier, reliquat?: float}>  $lignes
     */
    public function totalReliquats(Collection $lignes): float
    {
        return (float) $lignes->sum(function (array $ligne): float {
            if (array_key_exists('reliquat', $ligne)) {
                return (float) $ligne['reliquat'];
            }

            return $this->montantsSurFacture($ligne['courrier'])['reliquat'];
        });
    }
}
