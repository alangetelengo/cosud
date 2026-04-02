<?php

namespace App\Http\Controllers;

use App\Models\JournalAudit;
use App\Models\Fonction;
use App\Models\Structure;
use App\Models\TypeDocument;
use App\Models\User;
use App\Models\WorkflowEtape;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class WorkflowEtapeController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! auth()->user()->hasRole('admin')) {
                abort(403, 'Accès réservé aux administrateurs.');
            }

            return $next($request);
        });
    }

    public function index()
    {
        $etapes = WorkflowEtape::with(['typeDocument', 'structureScope', 'projetDossier', 'validateur', 'fonctionRequise', 'etapeSuivante'])
            ->orderByRaw('COALESCE(type_document_id, 0)')
            ->orderBy('ordre')
            ->orderBy('nom')
            ->get();

        Log::channel('eged')->debug('Admin workflow', ['user_id' => auth()->id()]);

        return view('parametres.workflow.index', compact('etapes'));
    }

    public function create()
    {
        $types = TypeDocument::where('actif', true)->orderBy('libelle')->get();
        $fonctions = Fonction::where('actif', true)->orderBy('libelle')->get(['id', 'libelle']);
        $services = Structure::query()
            ->where('actif', true)
            ->where('type', 'service')
            ->orderBy('nom')
            ->get(['id', 'nom', 'code']);
        $utilisateurs = User::orderBy('name')->get(['id', 'name', 'email']);
        $etapes = WorkflowEtape::orderBy('ordre')->get(['id', 'nom', 'ordre']);

        return view('parametres.workflow.create', compact('types', 'fonctions', 'services', 'utilisateurs', 'etapes'));
    }

    /** Formulaire de création d'un circuit complet (plusieurs étapes chaînées). */
    public function createCircuit()
    {
        $types = TypeDocument::where('actif', true)->orderBy('libelle')->get();
        $fonctions = Fonction::where('actif', true)->orderBy('libelle')->get(['id', 'libelle']);
        $services = Structure::query()
            ->where('actif', true)
            ->where('type', 'service')
            ->orderBy('nom')
            ->get(['id', 'nom', 'code']);
        $utilisateurs = User::orderBy('name')->get(['id', 'name', 'email']);
        $roles = Role::where('guard_name', 'web')->orderBy('name')->pluck('name');

        return view('parametres.workflow.create-circuit', compact('types', 'fonctions', 'services', 'utilisateurs', 'roles'));
    }

    /** Enregistre un circuit complet de workflow. */
    public function storeCircuit(Request $request)
    {
        $validated = $request->validate([
            'cible_scope' => ['required', 'in:global,type,service'],
            'type_document_id' => ['nullable', 'exists:type_documents,id', 'required_if:cible_scope,type'],
            'structure_scope_id' => ['nullable', 'exists:structures,id', 'required_if:cible_scope,service'],
            'prefixe_code' => ['required', 'string', 'max:30', 'regex:/^[a-z0-9_]+$/'],
            'etapes' => ['required', 'array', 'min:1'],
            'etapes.*.nom' => ['required', 'string', 'max:255'],
            'etapes.*.mode' => ['required', 'in:hierarchique,role,fonction,utilisateur,libre'],
            'etapes.*.role_requis' => ['nullable', 'string', 'max:50'],
            'etapes.*.fonction_requise_id' => ['nullable', 'exists:fonctions,id'],
            'etapes.*.validateur_id' => ['nullable', 'exists:users,id'],
        ]);

        foreach ($validated['etapes'] as $i => $etape) {
            if (($etape['mode'] ?? '') === 'role' && empty($etape['role_requis'] ?? '')) {
                return back()->withInput()->withErrors(["etapes.{$i}.role_requis" => 'Sélectionnez un rôle pour cette étape.']);
            }
            if (($etape['mode'] ?? '') === 'utilisateur' && empty($etape['validateur_id'] ?? '')) {
                return back()->withInput()->withErrors(["etapes.{$i}.validateur_id" => 'Sélectionnez un validateur pour cette étape.']);
            }
            if (($etape['mode'] ?? '') === 'fonction' && empty($etape['fonction_requise_id'] ?? '')) {
                return back()->withInput()->withErrors(["etapes.{$i}.fonction_requise_id" => 'Sélectionnez une fonction pour cette étape.']);
            }
        }

        $prefixe = $validated['prefixe_code'];
        $scope = (string) $validated['cible_scope'];
        $typeDocumentId = $scope === 'type' ? ($validated['type_document_id'] ?? null) : null;
        $structureScopeId = $scope === 'service' ? ($validated['structure_scope_id'] ?? null) : null;

        $codesAVerifier = array_map(fn ($i) => $prefixe . '_etape_' . $i, range(1, count($validated['etapes'])));
        $codesExistants = WorkflowEtape::whereIn('code', $codesAVerifier)->pluck('code')->toArray();

        if (! empty($codesExistants)) {
            return back()->withInput()->withErrors([
                'prefixe_code' => 'Certains codes existent déjà pour ce préfixe. Choisissez un autre préfixe ou supprimez les étapes existantes.',
            ]);
        }

        $etapesCreees = [];
        $ordreMax = WorkflowEtape::max('ordre') ?? 0;

        foreach ($validated['etapes'] as $index => $etapeData) {
            $ordre = $ordreMax + $index + 1;
            $code = $prefixe . '_etape_' . ($index + 1);
            $isLast = $index === count($validated['etapes']) - 1;

            $etape = WorkflowEtape::create([
                'nom' => $etapeData['nom'],
                'code' => $code,
                'ordre' => $ordre,
                'type_document_id' => $typeDocumentId,
                'projet_dossier_id' => null,
                'structure_scope_id' => $structureScopeId,
                'validation_hierarchique' => ($etapeData['mode'] ?? '') === 'hierarchique',
                'destinataire_libre' => ($etapeData['mode'] ?? '') === 'libre',
                'role_requis' => ($etapeData['mode'] ?? '') === 'role' ? ($etapeData['role_requis'] ?? null) : null,
                'fonction_requise_id' => ($etapeData['mode'] ?? '') === 'fonction' ? ($etapeData['fonction_requise_id'] ?? null) : null,
                'validateur_id' => ($etapeData['mode'] ?? '') === 'utilisateur' ? ($etapeData['validateur_id'] ?? null) : null,
                'workflow_etape_suivante_id' => null,
                'est_derniere_etape' => $isLast,
                'actif' => true,
            ]);

            $etapesCreees[] = $etape;
        }

        for ($i = 0; $i < count($etapesCreees) - 1; $i++) {
            $etapesCreees[$i]->update(['workflow_etape_suivante_id' => $etapesCreees[$i + 1]->id]);
        }

        JournalAudit::log('workflow.creation', 'workflow', ['commentaire' => 'Circuit ' . $prefixe]);
        \Illuminate\Support\Facades\Log::channel('eged')->info('Circuit workflow créé', ['prefixe' => $prefixe, 'nb_etapes' => count($etapesCreees), 'user_id' => auth()->id()]);

        return redirect()
            ->route('parametres.workflow.index')
            ->with('success', 'Circuit de workflow créé avec ' . count($etapesCreees) . ' étape(s).');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:workflow_etapes,code'],
            'ordre' => ['required', 'integer', 'min:0', 'max:255'],
            'type_document_id' => ['nullable', 'exists:type_documents,id'],
            'structure_scope_id' => ['nullable', 'exists:structures,id'],
            'validation_hierarchique' => ['boolean'],
            'destinataire_libre' => ['boolean'],
            'role_requis' => ['nullable', 'string', 'max:50'],
            'fonction_requise_id' => ['nullable', 'exists:fonctions,id'],
            'validateur_id' => ['nullable', 'exists:users,id'],
            'workflow_etape_suivante_id' => ['nullable', 'exists:workflow_etapes,id'],
            'est_derniere_etape' => ['boolean'],
            'actif' => ['boolean'],
        ]);
        if (! empty($validated['type_document_id']) && ! empty($validated['structure_scope_id'])) {
            return back()->withInput()->withErrors([
                'structure_scope_id' => 'Choisissez soit un type de document, soit un service (pas les deux).',
            ]);
        }
        $destinataireLibre = $request->boolean('destinataire_libre');

        WorkflowEtape::create([
            'nom' => $validated['nom'],
            'code' => $validated['code'],
            'ordre' => (int) $validated['ordre'],
            'type_document_id' => $validated['type_document_id'] ?? null,
            'projet_dossier_id' => null,
            'structure_scope_id' => $validated['structure_scope_id'] ?? null,
            'validation_hierarchique' => $destinataireLibre ? false : $request->boolean('validation_hierarchique'),
            'destinataire_libre' => $destinataireLibre,
            'role_requis' => $destinataireLibre ? null : ($validated['role_requis'] ?: null),
            'fonction_requise_id' => $destinataireLibre ? null : ($validated['fonction_requise_id'] ?? null),
            'validateur_id' => $destinataireLibre ? null : ($validated['validateur_id'] ?? null),
            'workflow_etape_suivante_id' => $validated['workflow_etape_suivante_id'] ?? null,
            'est_derniere_etape' => $request->boolean('est_derniere_etape'),
            'actif' => $request->boolean('actif', true),
        ]);

        JournalAudit::log('workflow.creation', 'workflow', ['commentaire' => $validated['code']]);
        Log::channel('eged')->info('Étape de workflow créée', ['code' => $validated['code'], 'user_id' => auth()->id()]);

        return redirect()
            ->route('parametres.workflow.index')
            ->with('success', 'Étape de workflow créée.');
    }

    public function edit(WorkflowEtape $workflow_etape)
    {
        $etape = $workflow_etape;
        $types = TypeDocument::where('actif', true)->orderBy('libelle')->get();
        $fonctions = Fonction::where('actif', true)->orderBy('libelle')->get(['id', 'libelle']);
        $services = Structure::query()
            ->where('actif', true)
            ->where('type', 'service')
            ->orderBy('nom')
            ->get(['id', 'nom', 'code']);
        $utilisateurs = User::orderBy('name')->get(['id', 'name', 'email']);
        $etapes = WorkflowEtape::where('id', '!=', $etape->id)->orderBy('ordre')->get(['id', 'nom', 'ordre']);

        return view('parametres.workflow.edit', compact('etape', 'types', 'fonctions', 'services', 'utilisateurs', 'etapes'));
    }

    public function update(Request $request, WorkflowEtape $workflow_etape)
    {
        $etape = $workflow_etape;

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('workflow_etapes', 'code')->ignore($etape->id)],
            'ordre' => ['required', 'integer', 'min:0', 'max:255'],
            'type_document_id' => ['nullable', 'exists:type_documents,id'],
            'structure_scope_id' => ['nullable', 'exists:structures,id'],
            'validation_hierarchique' => ['boolean'],
            'destinataire_libre' => ['boolean'],
            'role_requis' => ['nullable', 'string', 'max:50'],
            'fonction_requise_id' => ['nullable', 'exists:fonctions,id'],
            'validateur_id' => ['nullable', 'exists:users,id'],
            'workflow_etape_suivante_id' => ['nullable', 'exists:workflow_etapes,id', Rule::notIn([$etape->id])],
            'est_derniere_etape' => ['boolean'],
            'actif' => ['boolean'],
        ]);
        if (! empty($validated['type_document_id']) && ! empty($validated['structure_scope_id'])) {
            return back()->withInput()->withErrors([
                'structure_scope_id' => 'Choisissez soit un type de document, soit un service (pas les deux).',
            ]);
        }
        $destinataireLibre = $request->boolean('destinataire_libre');

        $etape->update([
            'nom' => $validated['nom'],
            'code' => $validated['code'],
            'ordre' => (int) $validated['ordre'],
            'type_document_id' => $validated['type_document_id'] ?? null,
            'projet_dossier_id' => null,
            'structure_scope_id' => $validated['structure_scope_id'] ?? null,
            'validation_hierarchique' => $destinataireLibre ? false : $request->boolean('validation_hierarchique'),
            'destinataire_libre' => $destinataireLibre,
            'role_requis' => $destinataireLibre ? null : ($validated['role_requis'] ?: null),
            'fonction_requise_id' => $destinataireLibre ? null : ($validated['fonction_requise_id'] ?? null),
            'validateur_id' => $destinataireLibre ? null : ($validated['validateur_id'] ?? null),
            'workflow_etape_suivante_id' => $validated['workflow_etape_suivante_id'] ?? null,
            'est_derniere_etape' => $request->boolean('est_derniere_etape'),
            'actif' => $request->boolean('actif', true),
        ]);

        JournalAudit::log('workflow.modification', 'workflow', ['commentaire' => $etape->code]);
        Log::channel('eged')->info('Étape de workflow mise à jour', ['etape_id' => $etape->id, 'user_id' => auth()->id()]);

        return redirect()
            ->route('parametres.workflow.index')
            ->with('success', 'Étape de workflow mise à jour.');
    }

    public function destroy(WorkflowEtape $workflow_etape)
    {
        $etape = $workflow_etape;
        if ($etape->documentValidations()->exists()) {
            return back()->with('error', 'Impossible de supprimer : des validations sont enregistrées pour cette étape.');
        }
        if (WorkflowEtape::where('workflow_etape_suivante_id', $etape->id)->exists()) {
            return back()->with('error', 'Impossible de supprimer : une autre étape pointe vers celle-ci. Modifiez d\'abord la chaîne.');
        }
        $etape->delete();
        JournalAudit::log('workflow.suppression', 'workflow', ['commentaire' => $etape->code]);
        Log::channel('eged')->info('Étape de workflow supprimée', ['etape_id' => $etape->id, 'user_id' => auth()->id()]);

        return redirect()
            ->route('parametres.workflow.index')
            ->with('success', 'Étape supprimée.');
    }
}
