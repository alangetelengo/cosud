<?php

namespace App\Http\Controllers;

use App\Models\TypeMetadonnee;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TypeMetadonneeController extends Controller
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
        $types = TypeMetadonnee::orderBy('libelle')->paginate(20);

        return view('parametres.types-metadonnees.index', compact('types'));
    }

    public function create()
    {
        return view('parametres.types-metadonnees.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:type_metadonnees,code'],
            'libelle' => ['required', 'string', 'max:255'],
            'type_valeur' => ['required', 'in:texte,numerique,date,booleen'],
            'description' => ['nullable', 'string'],
            'actif' => ['boolean'],
        ]);
        $validated['actif'] = $request->boolean('actif', true);
        TypeMetadonnee::create($validated);

        return redirect()->route('parametres.types-metadonnees.index')->with('success', 'Type de métadonnée créé.');
    }

    public function edit(TypeMetadonnee $type_metadonnee)
    {
        return view('parametres.types-metadonnees.edit', ['type' => $type_metadonnee]);
    }

    public function update(Request $request, TypeMetadonnee $type_metadonnee)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('type_metadonnees', 'code')->ignore($type_metadonnee->id)],
            'libelle' => ['required', 'string', 'max:255'],
            'type_valeur' => ['required', 'in:texte,numerique,date,booleen'],
            'description' => ['nullable', 'string'],
            'actif' => ['boolean'],
        ]);
        $validated['actif'] = $request->boolean('actif', true);
        $type_metadonnee->update($validated);

        return redirect()->route('parametres.types-metadonnees.index')->with('success', 'Type de métadonnée mis à jour.');
    }
}
