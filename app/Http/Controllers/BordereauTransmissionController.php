<?php

namespace App\Http\Controllers;

use App\Services\SuiviPaiementService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BordereauTransmissionController extends Controller
{
    public function __construct(
        private readonly SuiviPaiementService $service,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('bordereau-transmission.view');

        $annee = (int) $request->get('annee', now()->year);
        $periode = (string) $request->get('periode', 'hebdomadaire');
        if (! in_array($periode, $this->service->periodesBordereauDisponibles(), true)) {
            $periode = 'hebdomadaire';
        }

        $request->merge(['annee' => $annee]);

        $lignes = $this->service->requeteBordereauTransmission($request)->get();
        $sections = $this->service->grouperBordereauParPeriode($lignes, $periode);
        $totalGeneral = (float) $lignes->sum(fn ($ligne) => (float) $ligne->montant);

        $annees = $this->service->anneesBordereauDisponibles();
        if (! in_array($annee, $annees, true)) {
            $annees[] = $annee;
            rsort($annees);
        }

        return view('bordereau-transmission.index', [
            'annee' => $annee,
            'annees' => $annees,
            'periode' => $periode,
            'periodes' => $this->service->periodesBordereauDisponibles(),
            'periodeLibelle' => $this->service->libellePeriodeBordereau($periode),
            'sections' => $sections,
            'totalGeneral' => $totalGeneral,
            'nbLignes' => $lignes->count(),
            'service' => $this->service,
            'q' => $request->get('q', ''),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('bordereau-transmission.view');

        $annee = (int) $request->get('annee', now()->year);
        $periode = (string) $request->get('periode', 'hebdomadaire');
        if (! in_array($periode, $this->service->periodesBordereauDisponibles(), true)) {
            $periode = 'hebdomadaire';
        }

        $request->merge(['annee' => $annee]);
        $lignes = $this->service->requeteBordereauTransmission($request)->get();

        return $this->service->exporterBordereauCsv($lignes, $annee, $periode);
    }
}
