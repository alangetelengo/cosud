<?php

namespace App\Http\Controllers;

use App\Models\SuiviPaiement;
use App\Services\SuiviPaiementService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuiviPaiementController extends Controller
{
    public function __construct(
        private readonly SuiviPaiementService $service,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SuiviPaiement::class);

        $type = $this->service->resoudreTypeDepuisRequete($request);
        $annee = (int) $request->get('annee', now()->year);
        $lignes = $this->service->requeteListe($request, $type)->get();
        $totalMontant = $lignes->sum(fn (SuiviPaiement $ligne) => (float) $ligne->montant);

        return view('suivi-paiements.index', [
            'type' => $type,
            'annee' => $annee,
            'annees' => $this->service->anneesDisponibles($type),
            'lignes' => $lignes,
            'totalMontant' => $totalMontant,
            'libelleType' => $type === SuiviPaiement::TYPE_FSP_MAD ? 'FSP MAD' : 'FSP FACTURE',
            'titreFiche' => $type === SuiviPaiement::TYPE_FSP_MAD
                ? 'Fiche de suivi des paiements MAD'
                : 'Fiche de suivi des paiements facture',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', SuiviPaiement::class);

        $type = $this->service->resoudreTypeDepuisRequete($request);
        $annee = (int) $request->get('annee', now()->year);
        $lignes = $this->service->requeteListe($request, $type)->get();

        return $this->service->exporterCsv($type, $lignes, $annee);
    }
}
