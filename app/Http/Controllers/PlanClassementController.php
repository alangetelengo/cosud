<?php

namespace App\Http\Controllers;

use App\Models\Dossier;
use App\Models\JournalAudit;
use App\Models\Structure;
use App\Models\TypeDossier;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PlanClassementController extends Controller
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
        $racines = $this->buildTree();

        Log::channel('eged')->debug('Admin plan de classement', ['user_id' => auth()->id()]);

        return view('parametres.plan-classement.index', compact('racines'));
    }

    /** @return \Illuminate\Support\Collection<int, Dossier> */
    private function buildTree(): Collection
    {
        $all = Dossier::query()->withCount(['children', 'documents'])->get();
        foreach ($all as $d) {
            $ch = $all
                ->filter(fn (Dossier $c) => $c->parent_id !== null && (int) $c->parent_id === (int) $d->id)
                ->sortBy(fn (Dossier $c) => sprintf('%05d-%s', $c->ordre, $c->nom))
                ->values();
            $d->setRelation('treeChildren', $ch);
        }

        return $all
            ->filter(fn (Dossier $d) => $d->parent_id === null)
            ->sortBy(fn (Dossier $d) => sprintf('%05d-%s', $d->ordre, $d->nom))
            ->values();
    }

    public function create(Request $request)
    {
        $parentId = $request->integer('parent_id') ?: null;
        $parent = $parentId ? Dossier::find($parentId) : null;
        $parents = $this->listeParentsPourSelect();

        $typesDossier = TypeDossier::where('actif', true)->orderBy('libelle')->get();
        $structures = Structure::where('actif', true)->orderBy('nom')->get();

        return view('parametres.plan-classement.create', compact('parent', 'parents', 'typesDossier', 'structures'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->assertParentValide($data['parent_id'] ?? null, null);

        $codeRaw = isset($data['code']) ? trim((string) $data['code']) : '';
        $code = $codeRaw !== '' ? $codeRaw : $this->genererCodeUnique($data['nom'], $data['parent_id'] ?? null);
        $typeDossier = isset($data['type_dossier_id']) ? TypeDossier::find($data['type_dossier_id']) : null;
        $typeString = $typeDossier?->code;

        $confidentiel = $request->boolean('confidentiel');
        if ($typeString === 'confidentiel') {
            $confidentiel = true;
        }

        Dossier::create([
            'parent_id' => $data['parent_id'] ?? null,
            'type_dossier_id' => $data['type_dossier_id'] ?? null,
            'nom' => $data['nom'],
            'code' => $code,
            'type' => $typeString,
            'description' => $data['description'] ?? null,
            'confidentiel' => $confidentiel,
            'notify_sms' => $request->boolean('notify_sms'),
            'actif' => $request->boolean('actif', true),
            'ordre' => $data['ordre'] ?? 0,
            'structure_id' => $data['structure_id'] ?? null,
            'createur_id' => auth()->id(),
            'proprietaire_id' => auth()->id(),
        ]);

        return redirect()
            ->route('parametres.plan-classement.index')
            ->with('success', 'Dossier ajouté au plan de classement.');
    }

    public function edit(Dossier $dossier)
    {
        $parents = $this->listeParentsPourSelect($dossier->id);
        $typesDossier = TypeDossier::where('actif', true)->orderBy('libelle')->get();
        $structures = Structure::where('actif', true)->orderBy('nom')->get();

        return view('parametres.plan-classement.edit', compact('dossier', 'parents', 'typesDossier', 'structures'));
    }

    public function update(Request $request, Dossier $dossier)
    {
        $data = $this->validated($request, $dossier->id);
        $this->assertParentValide($data['parent_id'] ?? null, $dossier->id);

        $codeRaw = isset($data['code']) ? trim((string) $data['code']) : '';
        $code = $codeRaw !== '' ? $codeRaw : ($dossier->code ?: $this->genererCodeUnique($data['nom'], $data['parent_id'] ?? null));
        $typeDossier = isset($data['type_dossier_id']) ? TypeDossier::find($data['type_dossier_id']) : null;
        $typeString = $typeDossier?->code;

        $confidentiel = $request->boolean('confidentiel');
        if ($typeString === 'confidentiel') {
            $confidentiel = true;
        }

        $dossier->update([
            'parent_id' => $data['parent_id'] ?? null,
            'type_dossier_id' => $data['type_dossier_id'] ?? null,
            'nom' => $data['nom'],
            'code' => $code,
            'type' => $typeString,
            'description' => $data['description'] ?? null,
            'confidentiel' => $confidentiel,
            'notify_sms' => $request->boolean('notify_sms'),
            'actif' => $request->boolean('actif', true),
            'ordre' => $data['ordre'] ?? 0,
            'structure_id' => $data['structure_id'] ?? null,
        ]);
        JournalAudit::log('dossier.modification', 'plan_classement', ['dossier_id' => $dossier->id]);

        return redirect()
            ->route('parametres.plan-classement.index')
            ->with('success', 'Dossier mis à jour.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $dossierId = null): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:dossiers,id'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('dossiers', 'code')->ignore($dossierId)],
            'type_dossier_id' => ['nullable', 'integer', 'exists:type_dossiers,id'],
            'structure_id' => ['nullable', 'integer', 'exists:structures,id'],
            'description' => ['nullable', 'string'],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);
    }

    private function assertParentValide(?int $parentId, ?int $excludeDossierId): void
    {
        if ($parentId === null) {
            return;
        }
        if ($excludeDossierId !== null && (int) $parentId === (int) $excludeDossierId) {
            abort(422, 'Un dossier ne peut pas être son propre parent.');
        }
        if ($excludeDossierId !== null) {
            $interdits = $this->descendantIds($excludeDossierId)->push($excludeDossierId);
            if ($interdits->contains($parentId)) {
                abort(422, 'Le dossier parent ne peut pas être ce dossier ni l’un de ses descendants.');
            }
        }
    }

    private function descendantIds(int $id): Collection
    {
        $ids = collect();
        foreach (Dossier::where('parent_id', $id)->pluck('id') as $cid) {
            $ids->push((int) $cid);
            $ids = $ids->merge($this->descendantIds((int) $cid));
        }

        return $ids;
    }

    /**
     * Liste plate pour &lt;select&gt; avec indentation du chemin.
     *
     * @return \Illuminate\Support\Collection<int, object{value:int, label:string}>
     */
    private function listeParentsPourSelect(?int $exclureId = null): Collection
    {
        $interdits = $exclureId ? $this->descendantIds($exclureId)->push($exclureId) : collect();

        return Dossier::query()
            ->orderBy('nom')
            ->get()
            ->filter(fn (Dossier $d) => ! $interdits->contains($d->id))
            ->map(fn (Dossier $d) => (object) [
                'value' => $d->id,
                'label' => $d->chemin_complet,
            ])
            ->sortBy('label')
            ->values();
    }

    private function genererCodeUnique(string $nom, ?int $parentId): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', trim($nom));
        $slug = strtoupper(substr((string) $slug, 0, 20));
        $slug = trim($slug, '-') ?: 'DOSSIER';
        $base = $slug;
        $i = 0;
        while (Dossier::where('code', $slug)->exists()) {
            $i++;
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
