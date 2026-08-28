<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayerFactureRegularisationRequest;
use App\Http\Requests\StoreFactureRegularisationRequest;
use App\Http\Requests\UpdateFactureRegularisationRequest;
use App\Models\Courrier;
use App\Models\Structure;
use App\Services\FactureRegularisationService;
use App\Services\FournisseurPrestataireService;
use App\Support\ReturnUrl;
use Illuminate\Database\Eloquent\Collection;
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
        $this->authorize('factures-regularisation.view');

        return view('factures-regularisation.index', [
            'lignes' => $this->service->lister(
                $request->string('q')->toString() ?: null,
                $request->get('paiement'),
            ),
            'fournisseursMoratoireActif' => $this->service->clesFournisseursMoratoireActif(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('factures-regularisation.create');

        return view('factures-regularisation.create', [
            'directions' => $this->directions(),
            'fournisseursPrestataires' => app(FournisseurPrestataireService::class)->actifsPourSelect(),
            'retourUrl' => ReturnUrl::resolve($request->query('return'), route('factures-regularisation.index')),
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

        $libelle = match (true) {
            $courrier->estRegularisationProgrammee() => 'Facture programmée enregistrée — elle alimente la dette jusqu’au paiement effectif.',
            $courrier->estRegularisationContratMensuel() => 'Contrat mensuel enregistré — dette calculée et alimentée (paiement via moratoire).',
            default => 'Facture historique impayée enregistrée — elle alimente la dette fournisseur.',
        };

        return redirect()
            ->route('factures-regularisation.index')
            ->with('success', $libelle.' N° '.$courrier->numeroRegistreComplet());
    }

    public function edit(Request $request, Courrier $courrier): View
    {
        $this->authorize('factures-regularisation.create');
        $this->assertRegularisationEditable($courrier);

        return view('factures-regularisation.edit', [
            'courrier' => $courrier->loadMissing(['documents', 'createur']),
            'directions' => $this->directions(),
            'fournisseursPrestataires' => app(FournisseurPrestataireService::class)->actifsPourSelect(),
            'retourUrl' => ReturnUrl::resolve($request->query('return'), route('factures-regularisation.index')),
        ]);
    }

    public function update(UpdateFactureRegularisationRequest $request, Courrier $courrier): RedirectResponse
    {
        $this->assertRegularisationEditable($courrier);

        $data = $request->validated();
        $data['fichiers'] = array_values(array_filter((array) $request->file('fichiers', [])));

        try {
            $courrier = $this->service->modifier($courrier, $request->user(), $data);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['montant_facture' => $e->getMessage()]);
        }

        return redirect()
            ->route('factures-regularisation.index')
            ->with('success', 'Régularisation mise à jour. N° '.$courrier->numeroRegistreComplet());
    }

    public function destroy(Courrier $courrier): RedirectResponse
    {
        $this->authorize('factures-regularisation.create');
        $this->assertRegularisationEditable($courrier);

        $numero = $courrier->numeroRegistreComplet();

        try {
            $this->service->supprimer($courrier, request()->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('factures-regularisation.index')
            ->with('success', 'Régularisation '.$numero.' supprimée. Vous pouvez la resaisir.');
    }

    public function payerForm(Request $request, Courrier $courrier): View
    {
        $this->authorize('factures-regularisation.payer');

        abort_unless(
            $courrier->estRegularisationProgrammee(),
            404,
            'Cette facture n’est pas en statut programmée.'
        );

        try {
            $this->service->assertPaiementDirectAutorise($courrier);
        } catch (InvalidArgumentException $e) {
            abort(403, $e->getMessage());
        }

        return view('factures-regularisation.payer', [
            'courrier' => $courrier->loadMissing(['createur', 'documents']),
            'retourUrl' => ReturnUrl::resolve($request->query('return'), route('factures-regularisation.index')),
        ]);
    }

    public function payer(PayerFactureRegularisationRequest $request, Courrier $courrier): RedirectResponse
    {
        abort_unless(
            $courrier->estRegularisationProgrammee(),
            404,
            'Cette facture n’est pas en statut programmée.'
        );

        $data = $request->validated();
        $data['fichiers'] = array_values(array_filter((array) $request->file('fichiers', [])));

        try {
            $courrier = $this->service->enregistrerPaiementEffectif($courrier, $request->user(), $data);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['date_paiement' => $e->getMessage()]);
        }

        return redirect()
            ->route('factures-regularisation.index')
            ->with('success', 'Paiement effectif enregistré. N° '.$courrier->numeroRegistreComplet());
    }

    private function assertRegularisationEditable(Courrier $courrier): void
    {
        abort_unless($courrier->estRegularisation(), 404);

        abort_unless(
            $courrier->regularisationModifiable(),
            403,
            'Une facture déjà payée ne peut plus être modifiée ni supprimée.'
        );

        abort_if(
            $this->service->aMoratoireActif($courrier),
            403,
            'Un plan de paiement progressif (actif ou soldé) existe pour ce fournisseur. Cette facture ne peut plus être modifiée ni supprimée.'
        );
    }

    /**
     * @return Collection<int, Structure>
     */
    private function directions()
    {
        return Structure::query()
            ->whereIn('type', ['direction', 'antenne'])
            ->where('actif', true)
            ->orderBy('nom')
            ->get(['id', 'nom']);
    }
}
