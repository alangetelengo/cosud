<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMoratoireRequest;
use App\Http\Requests\UpdateMoratoireEcheanceRequest;
use App\Models\Moratoire;
use App\Models\MoratoireEcheance;
use App\Services\FournisseurDetteService;
use App\Services\MoratoireService;
use App\Support\MontantFcfa;
use App\Support\ReturnUrl;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use InvalidArgumentException;

class MoratoireController extends Controller
{
    public function __construct(
        private readonly MoratoireService $moratoireService,
        private readonly FournisseurDetteService $detteService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Moratoire::class);

        $filtresDettes = $this->filtresDettes($request);
        $filtresPlans = $this->filtresPlans($request);
        $peutVoirPlans = $request->user()?->can('create', Moratoire::class) ?? false;

        $dettes = $this->dettesFiltrees($filtresDettes);
        $moratoires = $peutVoirPlans
            ? $this->plansQuery($filtresPlans)->paginate(20)->withQueryString()
            : Moratoire::query()->whereRaw('0 = 1')->paginate(20)->withQueryString();

        return view('moratoires.index', [
            'moratoires' => $moratoires,
            'dettes' => $dettes,
            'filtresDettes' => $filtresDettes,
            'filtresPlans' => $filtresPlans,
            'peutVoirPlans' => $peutVoirPlans,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Moratoire::class);

        $eligibles = $this->detteService->fournisseursEligiblesMoratoire();
        $fournisseur = trim((string) $request->get('fournisseur', old('fournisseur_libelle', '')));
        $dette = null;
        if ($fournisseur !== '') {
            $dette = $eligibles->first(
                fn (array $row) => $row['fournisseur_libelle'] === $fournisseur
                    || $row['fournisseur_normalise'] === $this->detteService->normaliserLibelle($fournisseur)
            );
        }

        return view('moratoires.create', [
            'fournisseursEligibles' => $eligibles,
            'fournisseur' => $dette['fournisseur_libelle'] ?? '',
            'montantDette' => $dette['dette'] ?? null,
            'nbFactures' => $dette['nb_factures'] ?? null,
            'montantEcheanceDefaut' => old('montant_echeance_defaut', '1500000'),
            'retourUrl' => ReturnUrl::resolve($request->query('return'), route('moratoires.index')),
        ]);
    }

    public function detailDettes(Request $request): View
    {
        $this->authorize('viewAny', Moratoire::class);

        $fournisseur = trim((string) $request->get('fournisseur', ''));
        abort_if($fournisseur === '', 404);

        $detail = $this->detteService->detailFacturesPourFournisseur($fournisseur);
        abort_if($detail === null, 404);

        return view('moratoires.detail-dettes', [
            'synthese' => $detail['synthese'],
            'factures' => $detail['factures'],
            'retourUrl' => ReturnUrl::resolve($request->query('return'), route('moratoires.index')),
        ]);
    }

    public function store(StoreMoratoireRequest $request): RedirectResponse
    {
        try {
            $moratoire = $this->moratoireService->creer(
                $request->user(),
                $request->safe()->except('fichiers'),
                array_values(array_filter((array) $request->file('fichiers', []))),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['fournisseur_libelle' => $e->getMessage()]);
        }

        return redirect()
            ->route('moratoires.show', $moratoire)
            ->with('success', 'Moratoire créé — échéancier généré pour '.$moratoire->fournisseur_libelle.'.');
    }

    public function show(Request $request, Moratoire $moratoire): View
    {
        $this->authorize('view', $moratoire);

        $moratoire->load(['echeances.suiviPaiement', 'createur', 'documents']);

        return view('moratoires.show', [
            'moratoire' => $moratoire,
            'retourUrl' => ReturnUrl::resolve($request->query('return'), route('moratoires.index')),
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

        $moratoire->load(['echeances', 'createur']);

        $pdf = Pdf::loadView('moratoires.pdf.etat', [
            'moratoire' => $moratoire,
            'montantEnLettres' => MontantFcfa::enLettres($moratoire->montant_dette_initial),
            'titreSignataire' => $moratoire->createur?->titreSignatureDocument()
                ?? request()->user()?->titreSignatureDocument()
                ?? 'Responsable suivi des dépenses',
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

    public function printDettes(Request $request): View
    {
        $this->authorize('viewAny', Moratoire::class);

        $filtres = $this->filtresDettes($request);
        $dettes = $this->dettesFiltrees($filtres);
        $totalFacture = (float) $dettes->sum('montant_facture');
        $totalPaye = (float) $dettes->sum('montant_paye');
        $totalDette = (float) $dettes->sum('dette');
        $imprimeLe = now();

        $pdf = Pdf::loadView('moratoires.pdf.dettes', [
            'dettes' => $dettes,
            'filtres' => $filtres,
            'periodeLabel' => $this->libelleFiltresDettes($filtres),
            'imprimeLe' => $imprimeLe,
            'totalFacture' => $totalFacture,
            'totalPaye' => $totalPaye,
            'totalDette' => $totalDette,
            'montantEnLettres' => MontantFcfa::enLettres($totalDette),
            'signataire' => $request->user()?->name,
            'titreSignataire' => $request->user()?->titreSignatureDocument() ?? 'Responsable',
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        return view('moratoires.viewer-liste', [
            'content' => $pdf->output(),
            'titre' => 'Dettes fournisseurs',
            'sousTitre' => $this->libelleFiltresDettes($filtres),
            'downloadName' => 'dettes-fournisseurs.pdf',
            'retourUrl' => route('moratoires.index', array_filter([
                'dette_q' => $filtres['q'] ?: null,
                'dette_solde' => $filtres['solde'] !== 'oui' ? $filtres['solde'] : null,
            ])),
        ]);
    }

    public function printPlans(Request $request): View
    {
        $this->authorize('create', Moratoire::class);

        $filtres = $this->filtresPlans($request);
        $plans = $this->plansQuery($filtres)->get();
        $totalDetteInitiale = (float) $plans->sum('montant_dette_initial');
        $imprimeLe = now();

        $pdf = Pdf::loadView('moratoires.pdf.plans', [
            'plans' => $plans,
            'filtres' => $filtres,
            'periodeLabel' => $this->libelleFiltresPlans($filtres),
            'imprimeLe' => $imprimeLe,
            'totalDetteInitiale' => $totalDetteInitiale,
            'montantEnLettres' => MontantFcfa::enLettres($totalDetteInitiale),
            'signataire' => $request->user()?->name,
            'titreSignataire' => $request->user()?->titreSignatureDocument() ?? 'Responsable',
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        return view('moratoires.viewer-liste', [
            'content' => $pdf->output(),
            'titre' => 'Plans de paiement progressif',
            'sousTitre' => $this->libelleFiltresPlans($filtres),
            'downloadName' => 'plans-paiement-progressif.pdf',
            'retourUrl' => route('moratoires.index', array_filter([
                'plan_q' => $filtres['q'] ?: null,
                'plan_statut' => $filtres['statut'] !== '' ? $filtres['statut'] : null,
            ])),
        ]);
    }

    /**
     * @return array{q: string, solde: string}
     */
    private function filtresDettes(Request $request): array
    {
        $solde = (string) $request->query('dette_solde', 'oui');
        if (! in_array($solde, ['oui', 'non', 'tous'], true)) {
            $solde = 'oui';
        }

        return [
            'q' => trim((string) $request->query('dette_q', '')),
            'solde' => $solde,
        ];
    }

    /**
     * @return array{q: string, statut: string}
     */
    private function filtresPlans(Request $request): array
    {
        $statut = trim((string) $request->query('plan_statut', ''));
        if (! in_array($statut, ['', Moratoire::STATUT_ACTIF, Moratoire::STATUT_SOLDE, Moratoire::STATUT_ANNULE], true)) {
            $statut = '';
        }

        return [
            'q' => trim((string) $request->query('plan_q', '')),
            'statut' => $statut,
        ];
    }

    /**
     * @param  array{q: string, solde: string}  $filtres
     * @return Collection<int, array{
     *     fournisseur_libelle: string,
     *     fournisseur_normalise: string,
     *     nb_factures: int,
     *     montant_facture: float,
     *     montant_paye: float,
     *     dette: float,
     *     moratoire_actif_id: int|null
     * }>
     */
    private function dettesFiltrees(array $filtres): Collection
    {
        $dettes = $this->detteService->dettesParFournisseur();

        if ($filtres['solde'] === 'oui') {
            $dettes = $dettes->filter(fn (array $row) => $row['dette'] > 0);
        } elseif ($filtres['solde'] === 'non') {
            $dettes = $dettes->filter(fn (array $row) => $row['dette'] <= 0);
        }

        if ($filtres['q'] !== '') {
            $needle = mb_strtolower($filtres['q']);
            $dettes = $dettes->filter(
                fn (array $row) => str_contains(mb_strtolower($row['fournisseur_libelle']), $needle)
            );
        }

        return $dettes->values();
    }

    /**
     * @param  array{q: string, statut: string}  $filtres
     */
    private function plansQuery(array $filtres): Builder
    {
        $query = Moratoire::query()
            ->withCount('echeances')
            ->with('createur')
            ->orderByDesc('id');

        if ($filtres['statut'] !== '') {
            $query->where('statut', $filtres['statut']);
        }

        if ($filtres['q'] !== '') {
            $needle = $filtres['q'];
            $query->where(function ($q) use ($needle): void {
                $q->where('fournisseur_libelle', 'like', '%'.$needle.'%')
                    ->orWhere('fournisseur_normalise', 'like', '%'.mb_strtolower($needle).'%');
            });
        }

        return $query;
    }

    /**
     * @param  array{q: string, solde: string}  $filtres
     */
    private function libelleFiltresDettes(array $filtres): string
    {
        $parts = [];
        if ($filtres['q'] !== '') {
            $parts[] = 'Fournisseur : '.$filtres['q'];
        }
        $parts[] = match ($filtres['solde']) {
            'non' => 'Dettes soldées (reste = 0)',
            'tous' => 'Toutes les dettes',
            default => 'Dettes en cours (reste > 0)',
        };

        return implode(' · ', $parts);
    }

    /**
     * @param  array{q: string, statut: string}  $filtres
     */
    private function libelleFiltresPlans(array $filtres): string
    {
        $parts = [];
        if ($filtres['q'] !== '') {
            $parts[] = 'Fournisseur : '.$filtres['q'];
        }
        $parts[] = match ($filtres['statut']) {
            Moratoire::STATUT_ACTIF => 'Statut : Actif',
            Moratoire::STATUT_SOLDE => 'Statut : Soldé',
            Moratoire::STATUT_ANNULE => 'Statut : Annulé',
            default => 'Tous les statuts',
        };

        return implode(' · ', $parts);
    }
}
