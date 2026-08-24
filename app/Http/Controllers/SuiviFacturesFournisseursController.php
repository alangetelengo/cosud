<?php

namespace App\Http\Controllers;

use App\Services\SuiviFacturesFournisseursService;
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

        $lignes = $this->service->lignesPourAffichage($request);
        [$debutSemaine, $finSemaine] = $this->service->bornesSemaineCourante();

        $periode = $request->get('periode', 'tous');
        $periodeLabel = $periode === 'semaine'
            ? 'Semaine du '.$debutSemaine->format('d/m/Y').' au '.$finSemaine->format('d/m/Y')
            : ($request->filled('annee') ? 'Année '.$request->get('annee') : 'Toutes périodes');

        return view('suivi-factures-fournisseurs.index', [
            'lignes' => $lignes,
            'statuts' => $this->service->libellesStatuts(),
            'periode' => $periode,
            'periodeLabel' => $periodeLabel,
            'debutSemaine' => $debutSemaine,
            'finSemaine' => $finSemaine,
            'annee' => (int) $request->get('annee', now()->year),
            'service' => $this->service,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->autoriserAcces();

        $lignes = $this->service->lignesPourAffichage($request);
        [$debutSemaine, $finSemaine] = $this->service->bornesSemaineCourante();

        $periodeLabel = $request->get('periode') === 'semaine'
            ? 'Semaine du '.$debutSemaine->format('d/m/Y').' au '.$finSemaine->format('d/m/Y')
            : ($request->filled('annee') ? 'Année '.$request->get('annee') : 'Toutes périodes');

        return $this->service->exportCsv($lignes, $periodeLabel);
    }

    private function autoriserAcces(): void
    {
        $this->authorize('suivi-factures.view');
    }
}
