<?php

namespace App\Http\Controllers;

use App\Models\JournalAudit;
use App\Models\TypeDossier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TypeDossierController extends Controller
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
        $types = TypeDossier::orderBy('libelle')->paginate(15);

        return view('parametres.types-dossiers.index', compact('types'));
    }

    public function create()
    {
        return view('parametres.types-dossiers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:type_dossiers,code'],
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icone_defaut' => ['nullable', 'string', 'max:50'],
            'couleur_defaut' => ['nullable', 'string', 'max:7', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'actif' => ['boolean'],
        ]);
        $validated['actif'] = $request->boolean('actif', true);
        $type = TypeDossier::create($validated);
        Log::channel('cosud')->info('Type de dossier créé', ['type_id' => $type->id, 'code' => $type->code, 'user_id' => auth()->id()]);

        return redirect()->route('parametres.types-dossiers.index')->with('success', 'Type de dossier créé.');
    }

    public function edit(TypeDossier $type_dossier)
    {
        return view('parametres.types-dossiers.edit', ['type' => $type_dossier]);
    }

    public function update(Request $request, TypeDossier $type_dossier)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('type_dossiers', 'code')->ignore($type_dossier->id)],
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icone_defaut' => ['nullable', 'string', 'max:50'],
            'couleur_defaut' => ['nullable', 'string', 'max:7', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'actif' => ['boolean'],
        ]);
        $validated['actif'] = $request->boolean('actif', true);
        $type_dossier->update($validated);
        JournalAudit::log('type_dossier.modification', 'types_dossiers', ['commentaire' => $type_dossier->code]);
        Log::channel('cosud')->info('Type de dossier mis à jour', ['type_id' => $type_dossier->id, 'user_id' => auth()->id()]);

        return redirect()->route('parametres.types-dossiers.index')->with('success', 'Type de dossier mis à jour.');
    }

    public function destroy(TypeDossier $type_dossier)
    {
        if ($type_dossier->dossiers()->exists()) {
            Log::channel('cosud')->warning('Suppression type dossier refusée : dossiers associés', ['type_id' => $type_dossier->id]);

            return back()->with('error', 'Impossible de supprimer : des dossiers utilisent ce type.');
        }
        JournalAudit::log('type_dossier.suppression', 'types_dossiers', ['commentaire' => $type_dossier->code]);
        Log::channel('cosud')->info('Type de dossier supprimé', ['type_id' => $type_dossier->id, 'code' => $type_dossier->code, 'user_id' => auth()->id()]);
        $type_dossier->delete();

        return redirect()->route('parametres.types-dossiers.index')->with('success', 'Type de dossier supprimé.');
    }
}
