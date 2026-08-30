<?php

namespace App\Http\Controllers;

use App\Services\SuiviFacturesFournisseursService;
use App\Support\MontantFcfa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuiviFacturesFournisseursController extends Controller
{
    public function __construct(
        private readonly SuiviFacturesFournisseursService $service,
    ) {}

    public function index(Request $request): View
    {
        $this->autoriserAcces();

        $this->normaliserFiltresPeriode($request);

        $lignes = $this->service->lignesPourAffichage($request);
        [$debutSemaine, $finSemaine] = $this->service->bornesSemaineCourante();
        [$debutMois, $finMois] = $this->service->bornesMois($request->get('mois'));

        return view('suivi-factures-fournisseurs.index', [
            'lignes' => $lignes,
            'statuts' => $this->service->libellesStatuts(),
            'periode' => $request->get('periode', 'tous'),
            'periodeLabel' => $this->service->labelPeriode($request),
            'debutSemaine' => $debutSemaine,
            'finSemaine' => $finSemaine,
            'debutMois' => $debutMois,
            'finMois' => $finMois,
            'mois' => $request->get('mois', now()->format('Y-m')),
            'annee' => (int) $request->get('annee', now()->year),
            'service' => $this->service,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->autoriserAcces();

        $this->normaliserFiltresPeriode($request);

        $lignes = $this->service->lignesPourAffichage($request);
        $periodeLabel = $this->service->labelPeriode($request);

        $suffixe = match ($request->get('periode', 'tous')) {
            'semaine' => 'semaine-'.now()->format('Y-m-d'),
            'mois' => 'mois-'.($request->get('mois') ?: now()->format('Y-m')),
            'annee' => 'annee-'.(int) $request->get('annee', now()->year),
            default => now()->format('Y-m-d'),
        };

        return $this->service->exportCsv($lignes, $periodeLabel, $suffixe);
    }

    public function print(Request $request): View
    {
        $this->autoriserAcces();

        $this->normaliserFiltresPeriode($request);

        $lignes = $this->service->lignesPourAffichage($request);
        $periodeLabel = $this->service->labelPeriode($request);
        $totalMontant = $this->service->totalMontants($lignes);
        $totalPaye = $this->service->totalPaye($lignes);
        $totalReliquat = $this->service->totalReliquats($lignes);
        $annee = (int) $request->get('annee', now()->year);

        $pdf = Pdf::loadView('suivi-factures-fournisseurs.pdf.etat', [
            'lignes' => $lignes,
            'periodeLabel' => $periodeLabel,
            'totalMontant' => $totalMontant,
            'totalPaye' => $totalPaye,
            'totalReliquat' => $totalReliquat,
            'montantEnLettres' => MontantFcfa::enLettres($totalMontant),
            'annee' => $annee,
            'signataire' => $request->user()?->name,
            'service' => $this->service,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        return view('suivi-factures-fournisseurs.viewer', [
            'content' => $pdf->output(),
            'annee' => $annee,
            'periodeLabel' => $periodeLabel,
            'titre' => 'Suivi factures fournisseurs et Prestataires',
            'queryRetour' => $request->query(),
        ]);
    }

    private function normaliserFiltresPeriode(Request $request): void
    {
        $periode = $request->get('periode', 'tous');

        if ($periode === 'mois' && ! $request->filled('mois')) {
            $request->merge(['mois' => now()->format('Y-m')]);
        }

        if ($periode === 'annee' && ! $request->filled('annee')) {
            $request->merge(['annee' => now()->year]);
        }
    }

    private function autoriserAcces(): void
    {
        $this->authorize('suivi-factures.view');
    }
}
