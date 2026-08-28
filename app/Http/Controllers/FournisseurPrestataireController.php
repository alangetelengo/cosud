<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFournisseurPrestataireRequest;
use App\Http\Requests\UpdateFournisseurPrestataireRequest;
use App\Models\Courrier;
use App\Models\Dossier;
use App\Models\FournisseurPrestataire;
use App\Models\Moratoire;
use App\Services\FournisseurDetteService;
use App\Services\FournisseurPrestataireService;
use App\Support\ReturnUrl;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class FournisseurPrestataireController extends Controller
{
    public function __construct(
        private readonly FournisseurPrestataireService $service,
        private readonly FournisseurDetteService $detteService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', FournisseurPrestataire::class);

        $filtres = [
            'q' => trim((string) $request->get('q', '')),
            'type' => trim((string) $request->get('type', '')),
            'contrat' => trim((string) $request->get('contrat', '')),
            'fiscal' => trim((string) $request->get('fiscal', '')),
            'actif' => trim((string) $request->get('actif', 'oui')),
        ];

        $fiches = $this->service->queryListe($filtres)->paginate(25)->withQueryString();

        return view('fournisseurs-prestataires.index', compact('fiches', 'filtres'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', FournisseurPrestataire::class);

        $dossiers = $this->dossiersPourSelect($request);
        $retourUrl = ReturnUrl::resolve($request->query('return'), route('fournisseurs-prestataires.index'));

        return view('fournisseurs-prestataires.create', compact('dossiers', 'retourUrl'));
    }

    public function store(StoreFournisseurPrestataireRequest $request): RedirectResponse
    {
        $fiche = $this->service->creer($request->user(), $request->validated());

        return redirect()
            ->route('fournisseurs-prestataires.show', $fiche)
            ->with('success', 'Fiche « '.$fiche->nom.' » enregistrée.');
    }

    public function show(Request $request, FournisseurPrestataire $fournisseur_prestataire): View
    {
        $this->authorize('view', $fournisseur_prestataire);

        $fournisseur_prestataire->load(['dossier', 'createur']);
        $retourUrl = ReturnUrl::resolve($request->query('return'), route('fournisseurs-prestataires.index'));

        $detail = $this->detteService->detailFacturesPourFournisseur($fournisseur_prestataire->nom);
        $synthese = $detail['synthese'] ?? [
            'nb_factures' => 0,
            'montant_facture' => 0.0,
            'montant_paye' => 0.0,
            'dette' => 0.0,
            'moratoire_actif_id' => null,
        ];

        $factures = Courrier::query()
            ->with(['statutCourrier', 'typeCourrier'])
            ->where(function ($q) use ($fournisseur_prestataire): void {
                $q->where('fournisseur_prestataire_id', $fournisseur_prestataire->id)
                    ->orWhere(function ($sub) use ($fournisseur_prestataire): void {
                        $sub->whereNull('fournisseur_prestataire_id')
                            ->whereRaw('LOWER(TRIM(expediteur_libelle)) = ?', [$fournisseur_prestataire->nom_normalise]);
                    });
            })
            ->whereHas('typeCourrier', fn ($q) => $q->where('code', 'facture'))
            ->latest('id')
            ->limit(50)
            ->get();

        $moratoires = Moratoire::query()
            ->withCount('echeances')
            ->where(function ($q) use ($fournisseur_prestataire): void {
                $q->where('fournisseur_prestataire_id', $fournisseur_prestataire->id)
                    ->orWhere('fournisseur_normalise', $fournisseur_prestataire->nom_normalise);
            })
            ->latest('id')
            ->limit(20)
            ->get();

        return view('fournisseurs-prestataires.show', [
            'fiche' => $fournisseur_prestataire,
            'retourUrl' => $retourUrl,
            'synthese' => $synthese,
            'factures' => $factures,
            'moratoires' => $moratoires,
        ]);
    }

    public function edit(Request $request, FournisseurPrestataire $fournisseur_prestataire): View
    {
        $this->authorize('update', $fournisseur_prestataire);

        $dossiers = $this->dossiersPourSelect($request, $fournisseur_prestataire);
        $retourUrl = ReturnUrl::resolve($request->query('return'), route('fournisseurs-prestataires.show', $fournisseur_prestataire));

        return view('fournisseurs-prestataires.edit', [
            'fiche' => $fournisseur_prestataire,
            'dossiers' => $dossiers,
            'retourUrl' => $retourUrl,
        ]);
    }

    public function update(
        UpdateFournisseurPrestataireRequest $request,
        FournisseurPrestataire $fournisseur_prestataire
    ): RedirectResponse {
        $fiche = $this->service->mettreAJour($fournisseur_prestataire, $request->user(), $request->validated());

        return redirect()
            ->route('fournisseurs-prestataires.show', $fiche)
            ->with('success', 'Fiche « '.$fiche->nom.' » mise à jour.');
    }

    public function destroy(FournisseurPrestataire $fournisseur_prestataire): RedirectResponse
    {
        $this->authorize('delete', $fournisseur_prestataire);

        $nom = $fournisseur_prestataire->nom;
        $this->service->desactiver($fournisseur_prestataire, request()->user());

        return redirect()
            ->route('fournisseurs-prestataires.index')
            ->with('success', 'Fiche « '.$nom.' » désactivée (conservée pour l’historique).');
    }

    public function print(Request $request): View
    {
        $this->authorize('viewAny', FournisseurPrestataire::class);

        $filtres = [
            'q' => trim((string) $request->get('q', '')),
            'type' => trim((string) $request->get('type', '')),
            'contrat' => trim((string) $request->get('contrat', '')),
            'fiscal' => trim((string) $request->get('fiscal', '')),
            'actif' => trim((string) $request->get('actif', 'oui')),
        ];

        $lignes = $this->service->queryListe($filtres)->get();

        $pdf = Pdf::loadView('fournisseurs-prestataires.pdf.recapitulatif', [
            'lignes' => $lignes,
            'genereLe' => now(),
            'signataire' => $request->user()?->name,
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        return view('fournisseurs-prestataires.viewer', [
            'content' => $pdf->output(),
            'titre' => 'Tableau récapitulatif des contrats et dossiers fiscaux',
            'sousTitre' => $lignes->count().' ligne(s)',
            'downloadName' => 'tableau-contrats-dossiers-fiscaux.pdf',
            'queryRetour' => array_filter($filtres),
        ]);
    }

    /**
     * Dossiers fournisseurs / prestataires : sous-dossiers de « Mes dossiers » de l’utilisateur
     * (hors racine personnelle, hors plan institutionnel).
     *
     * @return Collection<int, Dossier>
     */
    private function dossiersPourSelect(Request $request, ?FournisseurPrestataire $fiche = null)
    {
        $user = $request->user();
        if (! $user?->can('dossiers.view')) {
            return collect();
        }

        $ids = Dossier::idsDossiersFournisseursPrestatairesPour((int) $user->id);

        if ($fiche?->dossier_id && ! in_array((int) $fiche->dossier_id, $ids, true)) {
            $ids[] = (int) $fiche->dossier_id;
        }

        if ($ids === []) {
            return collect();
        }

        return Dossier::query()
            ->whereIn('id', $ids)
            ->where('actif', true)
            ->orderBy('nom')
            ->get(['id', 'nom', 'parent_id']);
    }
}
