<?php

namespace App\Http\Controllers;

use App\Models\CategorieDepense;
use App\Models\JournalAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategorieDepenseController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! auth()->user()?->hasRole('admin') && ! auth()->user()?->can('parametres.categories-depense.view')) {
                abort(403, 'Accès réservé à la gestion des catégories de dépense.');
            }

            return $next($request);
        });
    }

    public function index(): View
    {
        $categories = CategorieDepense::query()->orderBy('ordre')->orderBy('libelle')->paginate(20);

        return view('parametres.categories-depense.index', compact('categories'));
    }

    public function create(): View
    {
        return view('parametres.categories-depense.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:categorie_depenses,code'],
            'libelle' => ['required', 'string', 'max:255'],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'actif' => ['boolean'],
        ]);
        $validated['actif'] = $request->boolean('actif', true);
        $validated['ordre'] = (int) ($validated['ordre'] ?? 100);
        $validated['est_systeme'] = false;

        $cat = CategorieDepense::query()->create($validated);
        Log::channel('cosud')->info('Catégorie dépense créée', ['id' => $cat->id, 'code' => $cat->code, 'user_id' => auth()->id()]);

        return redirect()->route('parametres.categories-depense.index')->with('success', 'Catégorie créée.');
    }

    public function edit(CategorieDepense $categorie_depense): View
    {
        return view('parametres.categories-depense.edit', ['categorie' => $categorie_depense]);
    }

    public function update(Request $request, CategorieDepense $categorie_depense): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('categorie_depenses', 'code')->ignore($categorie_depense->id)],
            'libelle' => ['required', 'string', 'max:255'],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'actif' => ['boolean'],
        ]);

        if ($categorie_depense->est_systeme && $validated['code'] !== $categorie_depense->code) {
            return back()->withInput()->with('error', 'Le code d’une catégorie système ne peut pas être modifié.');
        }

        $validated['actif'] = $request->boolean('actif', true);
        $validated['ordre'] = (int) ($validated['ordre'] ?? $categorie_depense->ordre);

        $categorie_depense->update($validated);
        JournalAudit::log('categorie_depense.modification', 'categorie_depenses', ['commentaire' => $categorie_depense->code]);

        return redirect()->route('parametres.categories-depense.index')->with('success', 'Catégorie mise à jour.');
    }

    public function destroy(CategorieDepense $categorie_depense): RedirectResponse
    {
        if ($categorie_depense->est_systeme) {
            return back()->with('error', 'Impossible de supprimer une catégorie système (facture / paiement divers).');
        }

        if ($categorie_depense->suiviPaiements()->exists()) {
            return back()->with('error', 'Des dépenses utilisent cette catégorie — désactivez-la plutôt que de la supprimer.');
        }

        $categorie_depense->delete();

        return redirect()->route('parametres.categories-depense.index')->with('success', 'Catégorie supprimée.');
    }
}
