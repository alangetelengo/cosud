<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFactureRegularisationRequest;
use App\Models\Structure;
use App\Services\FactureRegularisationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class FactureRegularisationController extends Controller
{
    public function __construct(
        private readonly FactureRegularisationService $service,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('factures-regularisation.create');

        return view('factures-regularisation.index', [
            'lignes' => $this->service->lister(
                $request->string('q')->toString() ?: null,
                $request->get('paiement'),
            ),
        ]);
    }

    public function create(): View
    {
        $this->authorize('factures-regularisation.create');

        $directions = Structure::query()
            ->whereIn('type', ['direction', 'antenne'])
            ->where('actif', true)
            ->orderBy('nom')
            ->get(['id', 'nom']);

        return view('factures-regularisation.create', [
            'directions' => $directions,
        ]);
    }

    public function store(StoreFactureRegularisationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['fichiers'] = array_values(array_filter((array) $request->file('fichiers', [])));

        try {
            $courrier = $this->service->enregistrer($request->user(), $data);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['montant_facture' => $e->getMessage()]);
        }

        $libelle = $courrier->estRegularisationPayee()
            ? 'Facture historique payée enregistrée (hors circuit).'
            : 'Facture historique impayée enregistrée — elle alimente la dette fournisseur.';

        return redirect()
            ->route('factures-regularisation.index')
            ->with('success', $libelle.' N° '.$courrier->numeroRegistreComplet());
    }
}
