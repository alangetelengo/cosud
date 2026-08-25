<?php

namespace App\Http\Controllers;

use App\Models\Fonction;
use App\Models\JournalAudit;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class StructureController extends Controller
{
    public function __construct()
    {
        // L'organigramme (index) est en lecture autorisée pour les directeurs/chefs de service.
        // Les opérations de modification restent réservées aux administrateurs.
        $this->middleware(function ($request, $next) {
            if (! Auth::user()->hasRole('admin')) {
                abort(403, 'Accès réservé aux administrateurs.');
            }

            return $next($request);
        })->except('index');
    }

    public function create()
    {
        $structures = Structure::with('parent')->orderByRaw('COALESCE(parent_id, 0)')->orderBy('nom')->get();
        $fonctions = Fonction::where('actif', true)->orderBy('libelle')->get();
        $rolesTechniques = Role::where('guard_name', 'web')->orderBy('name')->pluck('name');
        $utilisateurs = User::where('actif', true)->orderBy('name')->get(['id', 'name', 'email']);

        return view('parametres.structures.create', compact('structures', 'fonctions', 'rolesTechniques', 'utilisateurs'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'exists:structures,id'],
            'nom' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:structures,code'],
            'responsable_id' => ['nullable', 'exists:users,id'],
            'fonction_id' => ['nullable', 'exists:fonctions,id'],
            'role_technique' => ['nullable', 'string', 'max:100', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'actif' => ['boolean'],
        ]);

        $structure = Structure::create([
            'parent_id' => $validated['parent_id'] ?? null,
            'nom' => $validated['nom'],
            'code' => $validated['code'],
            'responsable_id' => $validated['responsable_id'] ?? null,
            'fonction_id' => $validated['fonction_id'] ?? null,
            'role_technique' => $validated['role_technique'] ?? null,
            'actif' => $request->boolean('actif', true),
        ]);

        JournalAudit::log('structure.creation', 'structures', ['commentaire' => 'Structure #'.$structure->id.' '.$structure->nom]);
        Log::channel('cosud')->info('Structure créée', ['structure_id' => $structure->id, 'user_id' => auth()->id()]);

        return redirect()
            ->route('parametres.structures.index')
            ->with('success', 'Structure créée avec succès.');
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $canViewOrg = $user->hasRole('admin')
            || $user->can('utilisateurs.view')
            || $user->hasRole('directeur')
            || $user->hasRole('chef_service');

        if (! $canViewOrg) {
            abort(403, 'Accès réservé à l’organigramme.');
        }

        // Pour éviter d’exposer tout l’organigramme : on limite au périmètre de la direction concernée.
        if ($user->hasRole('admin')) {
            $allStructures = Structure::where('actif', true)
                ->with([
                    'fonction',
                    'parent',
                    'users' => fn ($q) => $q->wherePivotNull('date_fin')->select('users.id', 'users.name', 'users.email'),
                ])
                ->orderByRaw('COALESCE(parent_id, 0)')
                ->orderBy('nom')
                ->get();
        } else {
            $scopeIds = $user->structureIdsVueHierarchique();
            $directionIds = Structure::whereIn('id', $scopeIds)->where('type', 'direction')->pluck('id')->all();

            if ($directionIds === []) {
                abort(403, 'Vous n’avez pas de direction dans votre périmètre.');
            }

            $subtreeIds = [];
            foreach ($directionIds as $did) {
                $subtreeIds = array_merge($subtreeIds, Structure::find($did)?->idsAvecDescendants() ?? []);
            }
            $subtreeIds = array_values(array_unique(array_map('intval', $subtreeIds)));

            $allStructures = Structure::where('actif', true)
                ->whereIn('id', $subtreeIds)
                ->with([
                    'fonction',
                    'parent',
                    'users' => fn ($q) => $q->wherePivotNull('date_fin')->select('users.id', 'users.name', 'users.email'),
                ])
                ->orderByRaw('COALESCE(parent_id, 0)')
                ->orderBy('nom')
                ->get();
        }

        if ($request->filled('parent_id')) {
            $parent = $allStructures->firstWhere('id', (int) $request->parent_id);
            if ($parent) {
                $ids = $this->collectSubtreeIds($allStructures, $parent->id);
                $structures = $allStructures->whereIn('id', $ids)->values();
                $racines = collect([$parent]);
            } else {
                $structures = collect();
                $racines = collect();
            }
        } elseif ($request->filled('q') || $request->filled('fonction_id') || $request->filled('titulaire_user_id')) {
            $filtered = $allStructures;
            if ($request->filled('q')) {
                $q = $request->q;
                $filtered = $filtered->filter(fn ($s) => stripos($s->nom, $q) !== false || stripos($s->code ?? '', $q) !== false);
            }
            if ($request->filled('fonction_id')) {
                $filtered = $filtered->where('fonction_id', (int) $request->fonction_id);
            }
            if ($request->filled('titulaire_user_id')) {
                $tid = (int) $request->titulaire_user_id;
                $filtered = $filtered->filter(fn ($s) => (int) $s->titulaireValidationActuel()?->id === $tid);
            }
            $matchingIds = $filtered->pluck('id')->all();
            $idsWithContext = $this->idsWithAncestors($allStructures, $matchingIds);
            foreach ($matchingIds as $id) {
                $idsWithContext = array_merge($idsWithContext, $this->collectSubtreeIds($allStructures, $id));
            }
            $idsWithContext = array_unique($idsWithContext);
            $structures = $allStructures->whereIn('id', $idsWithContext)->values();
            $racines = $structures->filter(fn ($s) => ! $s->parent_id || ! $structures->contains('id', $s->parent_id))->sortBy('nom')->values();
        } else {
            $structures = $allStructures;
            $racines = $structures->whereNull('parent_id')->sortBy('nom')->values();
        }

        $byParent = $structures->groupBy('parent_id');
        $titulaireIds = $allStructures->map(fn ($s) => $s->titulaireValidationActuel()?->id)->filter()->unique()->values();
        $utilisateursTitulaires = User::whereIn('id', $titulaireIds)->orderBy('name')->get(['id', 'name']);
        $fonctions = Fonction::where('actif', true)->orderBy('libelle')->get();
        $structuresForParent = Structure::whereIn('id', $allStructures->pluck('id')->all())
            ->orderBy('nom')
            ->get(['id', 'nom', 'code']);

        Log::channel('cosud')->debug('Admin organigramme', ['user_id' => Auth::id()]);

        return view('parametres.structures.index', compact('structures', 'racines', 'byParent', 'utilisateursTitulaires', 'structuresForParent', 'fonctions'));
    }

    /** IDs de la structure + tous ses descendants. */
    private function collectSubtreeIds($structures, int $parentId): array
    {
        $ids = [$parentId];
        foreach ($structures->where('parent_id', $parentId) as $child) {
            $ids = array_merge($ids, $this->collectSubtreeIds($structures, $child->id));
        }

        return $ids;
    }

    /** IDs des structures + tous leurs ancêtres (pour préserver la hiérarchie). */
    private function idsWithAncestors($structures, array $ids): array
    {
        $result = $ids;
        foreach ($ids as $id) {
            $s = $structures->firstWhere('id', $id);
            if ($s && $s->parent_id) {
                $result = array_merge($result, $this->idsWithAncestors($structures, [$s->parent_id]));
            }
        }

        return array_unique($result);
    }

    public function edit(Structure $structure)
    {
        $structure->load('fonction', 'parent');
        $structures = Structure::with('parent')->orderByRaw('COALESCE(parent_id, 0)')->orderBy('nom')->get();
        $fonctions = Fonction::where('actif', true)->orderBy('libelle')->get();
        $rolesTechniques = Role::where('guard_name', 'web')->orderBy('name')->pluck('name');
        $utilisateurs = User::where('actif', true)->orderBy('name')->get(['id', 'name', 'email']);

        return view('parametres.structures.edit', compact('structure', 'structures', 'fonctions', 'rolesTechniques', 'utilisateurs'));
    }

    public function update(Request $request, Structure $structure)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('structures', 'code')->ignore($structure->id)],
            'responsable_id' => ['nullable', 'exists:users,id'],
            'fonction_id' => ['nullable', 'exists:fonctions,id'],
            'role_technique' => ['nullable', 'string', 'max:100', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'actif' => ['boolean'],
        ]);

        $structure->update([
            'nom' => $validated['nom'],
            'code' => $validated['code'],
            'responsable_id' => $validated['responsable_id'] ?? null,
            'fonction_id' => $validated['fonction_id'] ?? null,
            'role_technique' => $validated['role_technique'] ?? null,
            'actif' => $request->boolean('actif', true),
        ]);

        JournalAudit::log('structure.modification', 'structures', ['commentaire' => 'Structure #'.$structure->id.' '.$structure->nom]);
        Log::channel('cosud')->info('Structure mise à jour', ['structure_id' => $structure->id, 'user_id' => auth()->id()]);

        return redirect()
            ->route('parametres.structures.index')
            ->with('success', 'Structure mise à jour.');
    }

    public function destroy(Structure $structure)
    {
        if ($structure->children()->exists()) {
            return back()->with('error', 'Impossible de supprimer : cette structure a des sous-structures. Supprimez ou réaffectez les sous-structures d\'abord.');
        }

        User::where('structure_id', $structure->id)->update(['structure_id' => null]);

        $nom = $structure->nom;
        $structure->delete();

        JournalAudit::log('structure.suppression', 'structures', ['commentaire' => 'Structure supprimée : '.$nom]);
        Log::channel('cosud')->info('Structure supprimée', ['structure_nom' => $nom, 'user_id' => auth()->id()]);

        return redirect()
            ->route('parametres.structures.index')
            ->with('success', 'Structure supprimée.');
    }
}
