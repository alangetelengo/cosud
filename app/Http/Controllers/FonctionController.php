<?php

namespace App\Http\Controllers;

use App\Models\Fonction;
use App\Models\JournalAudit;
use App\Models\Structure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class FonctionController extends Controller
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
        $fonctions = Fonction::orderBy('libelle')->paginate(20);

        return view('parametres.fonctions.index', compact('fonctions'));
    }

    public function create()
    {
        return view('parametres.fonctions.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', 'unique:fonctions,code'],
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'actif' => ['boolean'],
        ]);
        $validated['actif'] = $request->boolean('actif', true);
        $f = Fonction::create($validated);
        JournalAudit::log('fonction.creation', 'fonctions', ['commentaire' => $f->code]);
        Log::channel('cosud')->info('Fonction métier créée', ['fonction_id' => $f->id, 'user_id' => auth()->id()]);

        return redirect()->route('parametres.fonctions.index')->with('success', 'Fonction métier créée.');
    }

    public function edit(Fonction $fonction)
    {
        return view('parametres.fonctions.edit', ['fonction' => $fonction]);
    }

    public function update(Request $request, Fonction $fonction)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', Rule::unique('fonctions', 'code')->ignore($fonction->id)],
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'actif' => ['boolean'],
        ]);
        $validated['actif'] = $request->boolean('actif', true);
        $fonction->update($validated);
        JournalAudit::log('fonction.modification', 'fonctions', ['commentaire' => $fonction->code]);
        Log::channel('cosud')->info('Fonction métier mise à jour', ['fonction_id' => $fonction->id, 'user_id' => auth()->id()]);

        return redirect()->route('parametres.fonctions.index')->with('success', 'Fonction métier mise à jour.');
    }

    public function destroy(Fonction $fonction)
    {
        if (Structure::where('fonction_id', $fonction->id)->exists()) {
            return back()->with('error', 'Impossible de supprimer : une ou plusieurs structures utilisent cette fonction.');
        }
        if (DB::table('user_structure')->where('fonction_id', $fonction->id)->exists()) {
            return back()->with('error', 'Impossible de supprimer : des affectations utilisateurs référencent cette fonction.');
        }
        $code = $fonction->code;
        $fonction->delete();
        JournalAudit::log('fonction.suppression', 'fonctions', ['commentaire' => $code]);
        Log::channel('cosud')->info('Fonction métier supprimée', ['code' => $code, 'user_id' => auth()->id()]);

        return redirect()->route('parametres.fonctions.index')->with('success', 'Fonction métier supprimée.');
    }
}
