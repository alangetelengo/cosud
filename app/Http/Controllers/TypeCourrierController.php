<?php

namespace App\Http\Controllers;

use App\Models\CircuitCourrier;
use App\Models\JournalAudit;
use App\Models\TypeCourrier;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TypeCourrierController extends Controller
{
    public function __construct()
    {
        $this->middleware(function (Request $request, Closure $next) {
            if (! $request->user()?->hasRole('admin')) {
                abort(403, 'Accès réservé aux administrateurs.');
            }

            return $next($request);
        });
    }

    public function index(): View
    {
        $types = TypeCourrier::with('circuit')
            ->withCount('courriers')
            ->orderBy('libelle')
            ->paginate(15);

        return view('parametres.types-courriers.index', compact('types'));
    }

    public function create(): View
    {
        return view('parametres.types-courriers.create', [
            'circuits' => $this->circuitsDisponibles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validerType($request);
        $validated['actif'] = $request->boolean('actif', true);

        $type = TypeCourrier::create($validated);

        JournalAudit::log('type_courrier.creation', 'courriers', [
            'commentaire' => 'Type de courrier créé : '.$type->code,
        ]);
        Log::channel('eged')->info('Type de courrier créé', ['type_id' => $type->id, 'code' => $type->code, 'user_id' => auth()->id()]);

        return redirect()->route('parametres.types-courriers.index')->with('success', 'Type de courrier créé.');
    }

    public function edit(TypeCourrier $type_courrier): View
    {
        return view('parametres.types-courriers.edit', [
            'type' => $type_courrier,
            'circuits' => $this->circuitsDisponibles(),
        ]);
    }

    public function update(Request $request, TypeCourrier $type_courrier): RedirectResponse
    {
        $validated = $this->validerType($request, $type_courrier);
        $validated['actif'] = $request->boolean('actif', true);

        $type_courrier->update($validated);

        JournalAudit::log('type_courrier.modification', 'courriers', [
            'commentaire' => 'Type de courrier mis à jour : '.$type_courrier->code,
        ]);
        Log::channel('eged')->info('Type de courrier mis à jour', ['type_id' => $type_courrier->id, 'user_id' => auth()->id()]);

        return redirect()->route('parametres.types-courriers.index')->with('success', 'Type de courrier mis à jour.');
    }

    public function destroy(TypeCourrier $type_courrier): RedirectResponse
    {
        if ($type_courrier->courriers()->exists()) {
            return back()->with('error', 'Impossible de supprimer : des courriers utilisent ce type. Désactivez-le plutôt.');
        }

        JournalAudit::log('type_courrier.suppression', 'courriers', [
            'commentaire' => 'Type de courrier supprimé : '.$type_courrier->code,
        ]);
        Log::channel('eged')->info('Type de courrier supprimé', ['type_id' => $type_courrier->id, 'code' => $type_courrier->code, 'user_id' => auth()->id()]);
        $type_courrier->delete();

        return redirect()->route('parametres.types-courriers.index')->with('success', 'Type de courrier supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validerType(Request $request, ?TypeCourrier $type = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('type_courriers', 'code')->ignore($type?->id),
            ],
            'libelle' => ['required', 'string', 'max:150'],
            'circuit_courrier_id' => ['nullable', 'exists:circuit_courriers,id'],
            'actif' => ['nullable', 'boolean'],
        ]);
    }

    protected function circuitsDisponibles(): Collection
    {
        return CircuitCourrier::where('actif', true)->orderBy('libelle')->get();
    }
}
