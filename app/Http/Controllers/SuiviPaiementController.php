<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClasserSuiviDepenseDossierRequest;
use App\Http\Requests\StoreSuiviDepenseRemiseDgRequest;
use App\Models\CategorieDepense;
use App\Models\SuiviPaiement;
use App\Services\SuiviDepenseClassementService;
use App\Services\SuiviPaiementService;
use App\Support\MontantFcfa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuiviPaiementController extends Controller
{
    public function __construct(
        private readonly SuiviPaiementService $service,
        private readonly SuiviDepenseClassementService $classement,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SuiviPaiement::class);

        $annee = (int) $request->get('annee', now()->year);
        if (! $request->filled('annee')) {
            $request->merge(['annee' => $annee]);
        }

        $lignes = $this->service->requeteListeUnifiee($request)->get();
        $totalMontant = $lignes->sum(fn (SuiviPaiement $ligne) => (float) $ligne->montant);

        $lundi = now()->startOfWeek()->toDateString();
        $dimanche = now()->endOfWeek()->toDateString();

        return view('suivi-paiements.index', [
            'annee' => $annee,
            'annees' => $this->service->anneesDisponiblesUnifiees(),
            'lignes' => $lignes,
            'totalMontant' => $totalMontant,
            'categoriesFiltre' => CategorieDepense::toutesActives(),
            'categoriesSaisie' => CategorieDepense::activesPourSaisie(),
            'peutSaisirRemiseDg' => $request->user()?->can('create', SuiviPaiement::class) ?? false,
            'dateDebutHebdo' => $request->get('date_debut_hebdo', $lundi),
            'dateFinHebdo' => $request->get('date_fin_hebdo', $dimanche),
            'filtres' => [
                'categorie_depense_id' => $request->get('categorie_depense_id'),
                'date_debut' => $request->get('date_debut'),
                'date_fin' => $request->get('date_fin'),
                'q' => $request->get('q', ''),
            ],
        ]);
    }

    public function print(Request $request): View
    {
        $this->authorize('viewAny', SuiviPaiement::class);

        $annee = (int) $request->get('annee', now()->year);
        if (! $request->filled('annee')) {
            $request->merge(['annee' => $annee]);
        }

        $lignes = $this->service->requeteListeUnifiee($request)->get();
        $totalMontant = $lignes->sum(fn (SuiviPaiement $ligne) => (float) $ligne->montant);

        $periodeDebut = $request->get('date_debut');
        $periodeFin = $request->get('date_fin');
        $categorieLibelle = $request->filled('categorie_depense_id')
            ? CategorieDepense::query()->find($request->integer('categorie_depense_id'))?->libelle
            : null;

        $pdf = Pdf::loadView('suivi-paiements.pdf.etat-paiement', [
            'annee' => $annee,
            'lignes' => $lignes,
            'totalMontant' => $totalMontant,
            'montantEnLettres' => MontantFcfa::enLettres($totalMontant),
            'periodeDebut' => $periodeDebut,
            'periodeFin' => $periodeFin,
            'categorieLibelle' => $categorieLibelle,
            'signataire' => $request->user()?->name,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        return view('suivi-paiements.viewer', [
            'content' => $pdf->output(),
            'annee' => $annee,
            'categorieLibelle' => $categorieLibelle,
            'titre' => 'État récapitulatif de suivi des dépenses — '.$annee,
            'queryRetour' => $request->query(),
        ]);
    }

    public function classerForm(SuiviPaiement $suiviPaiement): View
    {
        $this->authorize('classerDossier', $suiviPaiement);

        $suggestion = $this->classement->nomDossierPrestataire($suiviPaiement);
        $dossiersClassement = $this->classement->dossiersCiblesPour(
            request()->user(),
            $suggestion
        );

        return view('suivi-paiements.classer', [
            'ligne' => $suiviPaiement->load(['categorieDepense', 'dossier.parent']),
            'dossiersClassement' => $dossiersClassement,
            'suggestionNom' => $suggestion,
        ]);
    }

    public function classer(ClasserSuiviDepenseDossierRequest $request, SuiviPaiement $suiviPaiement): RedirectResponse
    {
        $dossier = $this->classement->classerManuellement(
            $suiviPaiement,
            $request->user(),
            $request->validated()
        );

        return redirect()
            ->route('suivi-paiements.index', ['annee' => $suiviPaiement->numero_annee])
            ->with('success', 'Dépense classée dans le dossier « '.$dossier->nom.' ».');
    }

    public function storeRemiseDg(StoreSuiviDepenseRemiseDgRequest $request): RedirectResponse
    {
        $donnees = $request->validated();
        $donnees['justificatifs'] = array_values(array_filter(
            (array) $request->file('justificatifs', [])
        ));

        $ligne = $this->service->creerRemiseDg($request->user(), $donnees);

        $message = 'Dépense enregistrée — n° '.$ligne->numeroComplet().'.';
        if ($donnees['justificatifs'] !== []) {
            $message .= ' Justificatifs déposés — à classer dans un dossier prestataire.';
        }

        return redirect()
            ->route('suivi-paiements.index', ['annee' => $ligne->numero_annee])
            ->with('success', $message);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', SuiviPaiement::class);

        $annee = (int) $request->get('annee', now()->year);
        if (! $request->filled('annee')) {
            $request->merge(['annee' => $annee]);
        }

        $lignes = $this->service->requeteListeUnifiee($request)->get();

        return $this->service->exporterCsvUnifie($lignes, $annee);
    }

    public function exportHebdomadaire(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', SuiviPaiement::class);

        $validated = $request->validate([
            'date_debut_hebdo' => ['required', 'date'],
            'date_fin_hebdo' => ['required', 'date', 'after_or_equal:date_debut_hebdo'],
        ], [
            'date_debut_hebdo.required' => 'Indiquez la date de début de la semaine.',
            'date_fin_hebdo.required' => 'Indiquez la date de fin de la semaine.',
            'date_fin_hebdo.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ]);

        $lignes = $this->service->lignesRapportHebdomadaire(
            $validated['date_debut_hebdo'],
            $validated['date_fin_hebdo'],
        );

        return $this->service->exporterRapportHebdomadaireCsv(
            $lignes,
            $validated['date_debut_hebdo'],
            $validated['date_fin_hebdo'],
        );
    }
}
