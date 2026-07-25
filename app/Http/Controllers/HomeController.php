<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Dossier;
use App\Models\Structure;
use App\Models\TypeDocument;
use App\Models\User;
use App\Services\CourrierRetardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = auth()->user();

        // DG ou Admin : tableau de bord global
        if ($user->aAccesTotal()) {
            return $this->dashboardDG($user);
        }

        // Responsable de structure(s) : tableau de bord par structure
        $structureIdsGerees = $user->structureIdsGerees();
        if (! empty($structureIdsGerees)) {
            $structureId = $request->get('structure_id');
            if ($structureId && in_array((int) $structureId, $structureIdsGerees, true)) {
                return $this->dashboardStructure($user, Structure::find($structureId));
            }
            $premiereStructure = Structure::whereIn('id', $structureIdsGerees)->orderBy('nom')->first();

            return $this->dashboardStructure($user, $premiereStructure);
        }

        // Utilisateur simple (avec ou sans structure) : tableau personnel
        return $this->dashboardUtilisateur($user);
    }

    /** Tableau de bord DG / Admin : vue globale sur toute l'organisation. */
    protected function dashboardDG(User $user)
    {
        $nbDocuments = Document::horsCorbeille()->count();
        $nbDossiers = Dossier::where('actif', true)->count();
        $nbUsers = User::where('actif', true)->count();
        $nbTypes = TypeDocument::count();

        // Documents par statut (via colonne statut)
        $nbEnAttente = Document::horsCorbeille()->where('statut', 'en_attente')->count();
        $nbValides = Document::horsCorbeille()->whereIn('statut', ['valide', 'validé'])->count();
        $nbBrouillons = Document::horsCorbeille()->where('statut', 'brouillon')->count();
        $nbRejetes = Document::horsCorbeille()->whereIn('statut', ['rejete', 'rejeté'])->count();
        $nbArchives = Document::horsCorbeille()->whereIn('statut', ['archive', 'archivé'])->count();

        // Répartition par structure (via créateur ou dossier)
        $structures = Structure::where('actif', true)->orderBy('nom')->get();
        $statsParStructure = [];
        foreach ($structures as $s) {
            $ids = $s->idsAvecDescendants();
            $nbDocs = Document::horsCorbeille()
                ->where(function ($q) use ($ids) {
                    $q->whereHas('createur', fn ($cq) => $cq->whereIn('structure_id', $ids))
                        ->orWhereHas('dossier', fn ($dq) => $dq->whereIn('structure_id', $ids));
                })
                ->count();
            $nbDoss = Dossier::where('actif', true)->whereIn('structure_id', $ids)->count();
            $nbUsr = User::where('actif', true)->whereIn('structure_id', $ids)->count();
            $statsParStructure[] = [
                'structure' => $s,
                'nb_documents' => $nbDocs,
                'nb_dossiers' => $nbDoss,
                'nb_utilisateurs' => $nbUsr,
            ];
        }

        // Documents récents (7 derniers jours)
        $documentsRecents = Document::horsCorbeille()
            ->with(['createur', 'dossier', 'statutDocument'])
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Documents en attente de validation (pour le DG)
        $documentsEnAttente = Document::horsCorbeille()
            ->with(['createur', 'dossier', 'statutDocument'])
            ->where('statut', 'en_attente')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $retardService = app(CourrierRetardService::class);
        $courriersEnRetard = $retardService->courriersEnRetard();
        $nbCourriersEnRetard = $courriersEnRetard->count();
        $delaiRetardHeures = $retardService->delaiHeures();

        return view('home.dg', compact(
            'nbDocuments', 'nbDossiers', 'nbUsers', 'nbTypes',
            'nbEnAttente', 'nbValides', 'nbBrouillons', 'nbRejetes', 'nbArchives',
            'statsParStructure', 'documentsRecents', 'documentsEnAttente',
            'courriersEnRetard', 'nbCourriersEnRetard', 'delaiRetardHeures'
        ));
    }

    /** Tableau de bord par structure (responsable, directeur, etc.). */
    protected function dashboardStructure(User $user, ?Structure $structure)
    {
        if (! $structure) {
            return redirect()->route('home');
        }
        $ids = $structure->idsAvecDescendants();
        $structureIdsGerees = $user->structureIdsGerees();
        $structureIdsDirects = $this->structureIdsPiloteesDirectement($user);
        $idsSelect = $structureIdsDirects !== [] ? $structureIdsDirects : $structureIdsGerees;
        $structuresDisponibles = Structure::whereIn('id', $idsSelect)->orderBy('nom')->get();

        // Comptage aligné sur la visibilité réelle (sinon le tableau de bord annonce des éléments invisibles dans la page dossiers).
        $nbDocuments = Document::horsCorbeille()->visibleBy($user)->count();
        // Aligner le compteur avec l'affichage réel du plan (même règle de confidentialité).
        $nbDossiers = Dossier::where('actif', true)
            ->whereNull('parent_id')
            ->visibleBy($user)
            ->get()
            ->filter(fn (Dossier $d) => $this->utilisateurPeutVoirNoeudConfidentiel($user, $d))
            ->count();
        $nbUtilisateurs = User::where('actif', true)->whereIn('structure_id', $ids)->count();

        $documentsRecents = Document::horsCorbeille()
            ->visibleBy($user)
            ->with(['createur', 'dossier', 'statutDocument'])
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $documentsEnAttente = Document::horsCorbeille()
            ->visibleBy($user)
            ->with(['createur', 'dossier', 'statutDocument'])
            ->where('statut', 'en_attente')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('home.structure', compact(
            'structure', 'structuresDisponibles',
            'nbDocuments', 'nbDossiers', 'nbUtilisateurs',
            'documentsRecents', 'documentsEnAttente'
        ));
    }

    /**
     * Structures pilotées directement par l'utilisateur (sans inclure automatiquement les descendants),
     * pour garder une liste de sélection lisible dans le dashboard.
     *
     * @return list<int>
     */
    private function structureIdsPiloteesDirectement(User $user): array
    {
        $idsResponsable = Structure::where('responsable_id', $user->id)->pluck('id')->all();
        $idsTitulaire = DB::table('user_structure')
            ->join('structures', 'structures.id', '=', 'user_structure.structure_id')
            ->where('user_structure.user_id', $user->id)
            ->whereNull('user_structure.date_fin')
            ->whereNotNull('structures.fonction_id')
            ->whereColumn('user_structure.fonction_id', 'structures.fonction_id')
            ->pluck('structures.id')
            ->all();

        return array_values(array_unique(array_map('intval', array_merge($idsResponsable, $idsTitulaire))));
    }

    private function utilisateurPeutVoirNoeudConfidentiel(User $user, Dossier $dossier): bool
    {
        if (! $dossier->confidentiel) {
            return true;
        }
        if ($user->can('dossiers.view-confidentiel') || $user->aAccesTotal()) {
            return true;
        }

        return (int) $dossier->createur_id === (int) $user->id
            || (int) $dossier->proprietaire_id === (int) $user->id;
    }

    /** Tableau de bord utilisateur simple (vue personnelle). */
    protected function dashboardUtilisateur(User $user)
    {
        $nbMesDocuments = Document::horsCorbeille()
            ->where(function ($q) use ($user) {
                $q->where('createur_id', $user->id)
                    ->orWhere('proprietaire_id', $user->id)
                    ->orWhere('user_id', $user->id);
            })
            ->count();
        $nbDossiersFavoris = $user->dossierFavoris()->count();

        $documentsRecents = Document::horsCorbeille()
            ->with(['dossier', 'statutDocument'])
            ->where(function ($q) use ($user) {
                $q->where('createur_id', $user->id)
                    ->orWhere('proprietaire_id', $user->id)
                    ->orWhere('user_id', $user->id);
            })
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $dossiersRecents = Dossier::where('actif', true)
            ->where(function ($q) use ($user) {
                $q->where('createur_id', $user->id)
                    ->orWhere('proprietaire_id', $user->id);
            })
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get();

        return view('home.utilisateur', compact(
            'nbMesDocuments', 'nbDossiersFavoris',
            'documentsRecents', 'dossiersRecents'
        ));
    }
}
