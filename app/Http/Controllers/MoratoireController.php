<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMoratoireRequest;
use App\Http\Requests\UpdateMoratoireEcheanceRequest;
use App\Models\Moratoire;
use App\Models\MoratoireEcheance;
use App\Services\FournisseurDetteService;
use App\Services\MoratoireService;
use App\Support\MontantFcfa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class MoratoireController extends Controller
{
    public function __construct(
        private readonly MoratoireService $moratoireService,
        private readonly FournisseurDetteService $detteService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Moratoire::class);

        $moratoires = Moratoire::query()
            ->withCount('echeances')
            ->with('createur')
            ->orderByDesc('id')
            ->paginate(20);

        $dettes = $this->detteService->dettesParFournisseur()
            ->filter(fn (array $row) => $row['dette'] > 0)
            ->take(15);

        return view('moratoires.index', [
            'moratoires' => $moratoires,
            'dettes' => $dettes,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Moratoire::class);

        $fournisseur = trim((string) $request->get('fournisseur', ''));
        $dette = null;
        if ($fournisseur !== '') {
            $dette = $this->detteService->dettePourFournisseur($fournisseur);
        }

        return view('moratoires.create', [
            'fournisseur' => $fournisseur !== '' ? ($dette['fournisseur_libelle'] ?? $fournisseur) : '',
            'montantDette' => $dette['dette'] ?? null,
            'montantEcheanceDefaut' => old('montant_echeance_defaut', '1500000'),
        ]);
    }

    public function store(StoreMoratoireRequest $request): RedirectResponse
    {
        try {
            $moratoire = $this->moratoireService->creer($request->user(), $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['fournisseur_libelle' => $e->getMessage()]);
        }

        return redirect()
            ->route('moratoires.show', $moratoire)
            ->with('success', 'Moratoire créé — échéancier généré pour '.$moratoire->fournisseur_libelle.'.');
    }

    public function show(Moratoire $moratoire): View
    {
        $this->authorize('view', $moratoire);

        $moratoire->load(['echeances.suiviPaiement', 'createur']);

        return view('moratoires.show', [
            'moratoire' => $moratoire,
        ]);
    }

    public function updateEcheance(
        UpdateMoratoireEcheanceRequest $request,
        Moratoire $moratoire,
        MoratoireEcheance $echeance,
    ): RedirectResponse {
        if ((int) $echeance->moratoire_id !== (int) $moratoire->id) {
            abort(404);
        }

        $this->moratoireService->enregistrerPaiementEcheance(
            $echeance,
            $request->user(),
            $request->validated(),
            array_values(array_filter((array) $request->file('fichiers', []))),
        );

        return back()->with('success', 'Échéance n° '.$echeance->numero.' mise à jour.');
    }

    public function print(Moratoire $moratoire): View
    {
        $this->authorize('view', $moratoire);

        $moratoire->load('echeances');

        $pdf = Pdf::loadView('moratoires.pdf.etat', [
            'moratoire' => $moratoire,
            'montantEnLettres' => MontantFcfa::enLettres($moratoire->montant_dette_initial),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        return view('moratoires.viewer', [
            'content' => $pdf->output(),
            'moratoire' => $moratoire,
            'titre' => 'État récapitulatif des paiements progressifs',
        ]);
    }
}
