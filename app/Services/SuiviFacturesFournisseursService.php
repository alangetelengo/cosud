<?php

namespace App\Services;

use App\Models\Courrier;
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

    /**
     * @return array<string, string>
     */
    public function libellesStatuts(): array
    {
        return [
            self::STATUT_ATTENTE_AC => 'En attente AC',
            self::STATUT_CHEQUE => 'Chèque en préparation',
            self::STATUT_SIGNATURE_DG => 'Signature DG',
            self::STATUT_DECHARGE => 'En attente décharge',
            self::STATUT_CONTROLE => 'Contrôle suivi dépenses',
            self::STATUT_CLOTURE => 'Payé / clôturé',
        ];
    }

    public function statutPour(Courrier $courrier): string
    {
        if (! $courrier->circuit_etape_actuelle_id) {
            $suivi = $courrier->relationLoaded('suiviPaiement')
                ? $courrier->suiviPaiement
                : $courrier->suiviPaiement()->first();

            if ($suivi && $suivi->date_decharge && $suivi->controle_at === null) {
                return self::STATUT_CONTROLE;
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
     * @return Collection<int, array{courrier: Courrier, statut: string, libelle_statut: string}>
     */
    public function lignesPourAffichage(Request $request): Collection
    {
        $lignes = $this->requeteListe($request)->get()
            ->map(function (Courrier $courrier): array {
                $statut = $this->statutPour($courrier);

                return [
                    'courrier' => $courrier,
                    'statut' => $statut,
                    'libelle_statut' => $this->libelleStatut($statut),
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
     * @param  Collection<int, array{courrier: Courrier, statut: string, libelle_statut: string}>  $lignes
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
                'Montant',
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

                fputcsv($out, [
                    $courrier->numeroRegistreComplet(),
                    $courrier->date_orientation?->format('d/m/Y') ?? '',
                    $courrier->expediteur_libelle ?? '',
                    $courrier->objet,
                    $suivi?->montant !== null
                        ? number_format((float) $suivi->montant, 0, ',', ' ')
                        : '',
                    $suivi?->numero_piece ?? '',
                    $suivi?->banque ?? '',
                    $ligne['libelle_statut'],
                    $courrier->circuitEtapeActuelle?->nom ?? 'Terminé',
                    $courrier->instructions_dg ?? '',
                    $courrier->serviceDemandeurStructure?->nom ?? '',
                ], ';');
            }

            fputcsv($out, [], ';');
            fputcsv($out, ['Période', $periodeLabel], ';');
            fputcsv($out, ['Exporté le', now()->format('d/m/Y H:i')], ';');
            fputcsv($out, ['Nombre de factures', (string) $lignes->count()], ';');
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
     * @param  Collection<int, array{courrier: Courrier, statut: string, libelle_statut: string}>  $lignes
     */
    public function totalMontants(Collection $lignes): float
    {
        return (float) $lignes->sum(function (array $ligne): float {
            $montant = $ligne['courrier']->suiviPaiement?->montant;

            return $montant !== null ? (float) $montant : 0.0;
        });
    }
}
