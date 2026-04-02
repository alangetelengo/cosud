<?php

namespace App\Http\Controllers;

use App\Models\Dossier;
use App\Models\DossierPartage;
use App\Models\Document;
use App\Models\Structure;
use App\Models\TypeDossier;
use App\Models\User;
use App\Services\MesDossiersRacineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DossierController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Dossier::class);
        $user = auth()->user();
        $filtre = $request->get('filtre', 'tous');
        $q = (string) ($request->get('q') ?? '');

        $dossiers = Dossier::whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->with(['children' => fn ($q2) => $q2->withCount('documents')])->withCount('documents')])
            ->withCount('documents')
            ->where('actif', true)
            ->orderBy('ordre')
            ->orderBy('nom')
            ->get();

        if (! $user->can('dossiers.view-confidentiel') && ! $user->aAccesTotal()) {
            $dossiers = $dossiers->filter(fn ($d) => ! $d->confidentiel);
        }

        $dossiers = $dossiers->filter(fn ($d) => $d->visiblePar($user))
            ->each(fn ($d) => $this->filtrerEnfantsVisibles($d, $user));

        $favoriIds = $user->dossierFavoris()->pluck('dossiers.id')->toArray();

        if ($filtre === 'mes') {
            $dossiers = $dossiers->filter(fn ($d) => $this->brancheContientLie($d, $user))
                ->each(fn ($d) => $this->filtrerEnfantsMesDossiers($d, $user));
        } elseif ($filtre === 'favoris') {
            $dossiersFavoris = Dossier::whereIn('id', $favoriIds)
                ->where('actif', true)
                ->withCount('documents')
                ->get()
                ->filter(fn ($d) => $d->visiblePar($user))
                ->sortBy('chemin_complet')
                ->values();
            return view('dossiers.index', compact('dossiers', 'dossiersFavoris', 'favoriIds', 'filtre', 'q'));
        } elseif ($filtre === 'recents') {
            $dossiersRecents = Dossier::whereHas('documents', fn ($dq) => $dq->where('created_at', '>=', now()->subDays(30)))
                ->where('actif', true)
                ->withCount('documents')
                ->get()
                ->filter(fn ($d) => $d->visiblePar($user))
                ->sortByDesc(fn ($d) => $d->documents()->max('created_at'))
                ->take(50)
                ->values();
            return view('dossiers.index', compact('dossiers', 'dossiersRecents', 'favoriIds', 'filtre', 'q'));
        }

        if ($q !== '') {
            $dossiers = $this->filtrerArbreRecherche($dossiers, $q);
        }

        $dossiersRecents = collect();
        $dossiersFavoris = collect();

        return view('dossiers.index', compact('dossiers', 'dossiersFavoris', 'dossiersRecents', 'favoriIds', 'filtre', 'q'));
    }

    public function create(Request $request, MesDossiersRacineService $mesDossiersRacine)
    {
        $this->authorize('create', Dossier::class);
        $user = auth()->user();
        $parentId = $request->integer('parent_id') ?: null;
        $parent = $parentId ? Dossier::find($parentId) : null;

        $racine = $mesDossiersRacine->find($user);
        $parents = $this->listeParentsPourStructure($user, $racine);
        if ($parent) {
            if (! $parent->visiblePar($user)) {
                abort(403, 'Dossier non accessible.');
            }
            if (! $this->parentAutorisePourCreation($user, $parent, $racine)) {
                abort(403, 'Vous ne pouvez pas créer de dossier sous ce dossier parent.');
            }
        }
        $typesDossier = TypeDossier::where('actif', true)->orderBy('libelle')->get();

        $peutCreerSousDossier = $user->aAccesTotal() || $user->can('dossiers.create-structure');
        $peutCreerRacine = $user->aAccesTotal() || $user->can('dossiers.create-racine-structure');
        $sansRacinePersonnelle = $racine === null && $user->can('dossiers.create-structure');
        $structuresRacine = collect();
        if ($peutCreerRacine) {
            $structuresRacine = $user->aAccesTotal()
                ? Structure::where('actif', true)->orderBy('nom')->get(['id', 'nom', 'code'])
                : Structure::where('actif', true)
                    ->whereIn('id', $this->structureIdsAutorisees($user))
                    ->orderBy('nom')
                    ->get(['id', 'nom', 'code']);
        }
        $peutCreerRacineOrg = $peutCreerRacine && $structuresRacine->isNotEmpty();
        $formulaireCreationDisponible = ($parents->isNotEmpty() && $peutCreerSousDossier)
            || $peutCreerRacineOrg
            || $sansRacinePersonnelle;
        $modeRacineSeulementVue = (! $peutCreerSousDossier || $parents->isEmpty())
            && $peutCreerRacineOrg;
        $modeEspacePersonnelSeul = $sansRacinePersonnelle
            && $parents->isEmpty()
            && ! $peutCreerRacineOrg;
        $modeRacineOuPersonnel = $modeRacineSeulementVue && $sansRacinePersonnelle;
        $racineParDefaut = $parents->isEmpty() && $peutCreerRacineOrg && ! $sansRacinePersonnelle;
        /** Depuis le plan sans parent dans l’URL : cocher « racine structure » par défaut pour éviter de créer sous un dossier par erreur. */
        $cocherRacineParDefaut = $parentId === null
            && $peutCreerRacine
            && $user->can('dossiers.create-racine-structure')
            && ! $sansRacinePersonnelle;

        return view('dossiers.create', compact(
            'parent',
            'parents',
            'typesDossier',
            'peutCreerRacine',
            'peutCreerSousDossier',
            'structuresRacine',
            'formulaireCreationDisponible',
            'modeRacineSeulementVue',
            'modeEspacePersonnelSeul',
            'modeRacineOuPersonnel',
            'sansRacinePersonnelle',
            'racineParDefaut',
            'cocherRacineParDefaut'
        ));
    }

    public function store(Request $request, MesDossiersRacineService $mesDossiersRacine)
    {
    
        $this->authorize('create', Dossier::class);
        $user = auth()->user();
        $racine = $mesDossiersRacine->find($user);
        $allowedStructureIds = $this->structureIdsAutorisees($user);

        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:dossiers,id'],
            'type_dossier_id' => ['nullable', 'integer', 'exists:type_dossiers,id'],
            'description' => ['nullable', 'string'],
            'creer_racine' => ['sometimes', 'boolean'],
            'creer_racine_personnelle' => ['sometimes', 'boolean'],
            'placement' => ['nullable', 'in:personnel,structure'],
            'structure_id' => ['nullable', 'integer', 'exists:structures,id'],
        ]);

        $veutRacinePersonnelle = $request->boolean('creer_racine_personnelle')
            || $request->input('placement') === 'personnel';

        if ($veutRacinePersonnelle) {
            if (! $user->can('dossiers.create-structure')) {
                abort(403, 'Vous n’avez pas la permission de créer votre espace personnel.');
            }
            if ($mesDossiersRacine->find($user) !== null) {
                return back()->withInput()->withErrors(['nom' => 'Vous avez déjà un espace personnel à la racine du plan.']);
            }
            $typeDossier = isset($data['type_dossier_id']) ? TypeDossier::find($data['type_dossier_id']) : null;
            $typeString = $typeDossier?->code;
            $confidentiel = $typeString === 'confidentiel';
            $dossier = $mesDossiersRacine->createPersonnelRoot(
                $user,
                $data['nom'],
                $data['description'] ?? null,
                $data['type_dossier_id'] ?? null,
                $typeString,
                $confidentiel
            );
            Log::channel('eged')->info('Racine personnelle créée par l’utilisateur', ['dossier_id' => $dossier->id, 'user_id' => $user->id]);

            return redirect()
                ->route('dossiers.show', $dossier)
                ->with('success', 'Votre espace personnel a été créé.');
        }

        $creerRacine = $request->boolean('creer_racine')
            || $request->input('placement') === 'structure';

        if ($creerRacine) {
            if (! $user->aAccesTotal() && ! $user->can('dossiers.create-racine-structure')) {
                abort(403, 'Vous n’avez pas la permission de créer un dossier racine.');
            }
            $structureId = (int) ($data['structure_id'] ?? 0);
            if ($structureId < 1) {
                return back()->withInput()->withErrors(['structure_id' => 'Sélectionnez la structure pour laquelle ce dossier servira de racine.']);
            }
            $structureAutorisee = $user->aAccesTotal()
                ? Structure::where('id', $structureId)->where('actif', true)->exists()
                : in_array($structureId, $allowedStructureIds, true);
            if (! $structureAutorisee) {
                abort(403, 'Vous ne pouvez créer un dossier racine que pour une structure de votre périmètre.');
            }
            $parentId = null;
            $proprietaireId = $this->proprietaireIdPourStructure($structureId, $user->id);
        } else {
            if (! $user->can('dossiers.create-structure')) {
                return back()->withInput()->withErrors(['parent_id' => 'Vous ne pouvez créer que des dossiers racine de structure (permission dédiée).']);
            }
            $parentId = $data['parent_id'] ?? null;
            $parent = $parentId ? Dossier::find($parentId) : null;

            if (! $parent) {
                $hint = $racine === null
                    ? ' Choisissez un dossier parent, créez une racine pour une structure, ou cochez la création de votre espace personnel.'
                    : '';

                return back()->withInput()->withErrors(['parent_id' => 'Choisissez un dossier parent ou activez la création d’un dossier racine de structure.'.$hint]);
            }

            if (! $parent->visiblePar($user)) {
                abort(403, 'Dossier parent non accessible.');
            }

            if (! $this->parentAutorisePourCreation($user, $parent, $racine)) {
                abort(403, 'Vous ne pouvez créer des dossiers que dans votre espace « Mes dossiers » ou dans les dossiers de votre structure.');
            }

            $sousArbrePersonnel = $racine !== null && Dossier::estSousRacineMesDossiers($parent, $racine);
            if ($sousArbrePersonnel) {
                $parentStructureId = $parent->structure_id ?? $parent->structure_id_depot;
                $structureId = $parentStructureId ?: $user->structure_id;
                $proprietaireId = $user->id;
            } else {
                $parentStructureId = $parent->structure_id ?? $parent->structure_id_depot;
                if (! $parentStructureId || ! in_array($parentStructureId, $allowedStructureIds, true)) {
                    abort(403, 'Vous ne pouvez créer des dossiers que dans votre structure.');
                }
                $structureId = $parentStructureId;
                $proprietaireId = $this->proprietaireIdPourStructure((int) $structureId, $user->id);
            }
            $ordre = (int) (Dossier::where('parent_id', $parentId)->max('ordre') ?? -1) + 1;
        }

        $typeDossier = isset($data['type_dossier_id']) ? TypeDossier::find($data['type_dossier_id']) : null;
        $typeString = $typeDossier?->code;
        $confidentiel = $typeString === 'confidentiel';

        $payload = [
            'parent_id' => $parentId,
            'est_racine_org' => $creerRacine,
            'type_dossier_id' => $data['type_dossier_id'] ?? null,
            'nom' => $data['nom'],
            'code' => $this->genererCodeUnique($data['nom'], $parentId),
            'type' => $typeString,
            'description' => $data['description'] ?? null,
            'confidentiel' => $confidentiel,
            'notify_sms' => false,
            'actif' => true,
            'structure_id' => $structureId,
            'createur_id' => $user->id,
            'proprietaire_id' => $proprietaireId,
        ];

        if ($creerRacine) {
            $dossier = DB::transaction(function () use ($payload, $structureId) {
                $payload['ordre'] = $this->ordrePourNouvelleRacineOrganisation($structureId);

                return Dossier::create($payload);
            });
        } else {
            $payload['ordre'] = $ordre;
            $dossier = Dossier::create($payload);
        }

        Log::channel('eged')->info('Dossier créé par utilisateur', ['dossier_id' => $dossier->id, 'user_id' => $user->id]);

        return redirect()
            ->route('dossiers.show', $dossier)
            ->with('success', 'Dossier créé avec succès.');
    }

    /**
     * Place une nouvelle racine d’organisation juste après les autres racines déjà rattachées à la même structure,
     * au lieu d’utiliser max(ordre) global (qui envoyait le dossier en bas du plan, ex. après « Mes dossiers »).
     */
    private function ordrePourNouvelleRacineOrganisation(int $structureId): int
    {
        $maxMemeStructure = Dossier::query()
            ->whereNull('parent_id')
            ->whereNull('racine_utilisateur_id')
            ->where('structure_id', $structureId)
            ->max('ordre');

        if ($maxMemeStructure === null) {
            return (int) (Dossier::query()->whereNull('parent_id')->max('ordre') ?? -1) + 1;
        }

        $newOrdre = (int) $maxMemeStructure + 1;

        Dossier::query()
            ->whereNull('parent_id')
            ->where('ordre', '>=', $newOrdre)
            ->increment('ordre');

        return $newOrdre;
    }

    private function structureIdsAutorisees(User $user): array
    {
        return array_filter(array_unique(array_merge(
            [$user->structure_id],
            $user->structureIdsGerees()
        )));
    }

    private function proprietaireIdPourStructure(int $structureId, int $fallbackUserId): int
    {
        $structure = Structure::find($structureId);

        return $structure?->titulaireValidationActuel()?->id ?? $structure?->responsable_id ?? $fallbackUserId;
    }

    private function listeParentsPourStructure(User $user, ?Dossier $racine): \Illuminate\Support\Collection
    {
        $allowedIds = $this->structureIdsAutorisees($user);

        return Dossier::where('actif', true)
            ->get()
            ->filter(function (Dossier $d) use ($user, $allowedIds, $racine) {
                if (! $d->visiblePar($user)) {
                    return false;
                }
                if ($racine !== null && Dossier::estSousRacineMesDossiers($d, $racine)) {
                    return true;
                }
                $sid = $d->structure_id ?? $d->structure_id_depot;

                return $sid && ! empty($allowedIds) && in_array($sid, $allowedIds, true);
            })
            ->map(function (Dossier $d) use ($racine) {
                $label = $d->chemin_complet;
                if ($racine !== null && (int) $d->id === (int) $racine->id) {
                    $label = '★ '.$label.' (votre espace personnel)';
                }

                return (object) ['value' => $d->id, 'label' => $label];
            })
            ->sortBy(function ($opt) use ($racine) {
                if ($racine !== null && (int) $opt->value === (int) $racine->id) {
                    return '0';
                }

                return $opt->label;
            })
            ->values();
    }

    /** @return list<int> */
    private function idsDescendantsDossier(int $dossierId): array
    {
        $ids = [];
        $queue = [$dossierId];
        while ($queue !== []) {
            $pid = array_shift($queue);
            foreach (Dossier::query()->where('parent_id', $pid)->pluck('id') as $cid) {
                $cid = (int) $cid;
                $ids[] = $cid;
                $queue[] = $cid;
            }
        }

        return $ids;
    }

    private function listeParentsPourEdition(User $user, ?Dossier $racine, Dossier $dossier): \Illuminate\Support\Collection
    {
        $interdits = array_merge([(int) $dossier->id], $this->idsDescendantsDossier((int) $dossier->id));

        return $this->listeParentsPourStructure($user, $racine)
            ->filter(fn ($opt) => ! in_array((int) $opt->value, $interdits, true))
            ->values();
    }

    private function listeParentsPourArbrePersonnel(Dossier $racine, Dossier $dossier): \Illuminate\Support\Collection
    {
        $interdits = array_merge([(int) $dossier->id], $this->idsDescendantsDossier((int) $dossier->id));

        return Dossier::query()
            ->where('actif', true)
            ->get()
            ->filter(fn (Dossier $d) => Dossier::estSousRacineMesDossiers($d, $racine))
            ->filter(fn (Dossier $d) => ! in_array((int) $d->id, $interdits, true))
            ->map(fn (Dossier $d) => (object) ['value' => $d->id, 'label' => $d->chemin_complet])
            ->sortBy(fn ($o) => $o->label)
            ->values();
    }

    private function parentAutorisePourCreation(User $user, Dossier $parent, ?Dossier $racine): bool
    {
        if ($racine !== null && Dossier::estSousRacineMesDossiers($parent, $racine)) {
            return true;
        }
        $allowedIds = $this->structureIdsAutorisees($user);
        if (empty($allowedIds)) {
            return false;
        }
        $sid = $parent->structure_id ?? $parent->structure_id_depot;

        return $sid && in_array($sid, $allowedIds, true);
    }

    private function genererCodeUnique(string $nom, ?int $parentId): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', trim($nom));
        $slug = strtoupper(substr($slug, 0, 20)) ?: 'DOSSIER';
        $slug = trim($slug, '-');
        $base = $slug;
        $i = 0;
        while (Dossier::where('code', $slug)->exists()) {
            $i++;
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    public function toggleFavori(Dossier $dossier)
    {
        $this->authorize('view', $dossier);
        $user = auth()->user();
        if ($user->dossierFavoris()->where('dossier_id', $dossier->id)->exists()) {
            $user->dossierFavoris()->detach($dossier->id);
            $message = 'Dossier retiré des favoris.';
        } else {
            $user->dossierFavoris()->attach($dossier->id);
            $message = 'Dossier ajouté aux favoris.';
        }
        $back = request('redirect', route('dossiers.index'));

        return redirect($back)->with('success', $message);
    }

    public function partages(Dossier $dossier)
    {
        $this->authorize('share', $dossier);
        $partages = $dossier->partages()->with('user')->get();
        $partageUserIds = $partages->pluck('user_id')->toArray();
        $utilisateurs = User::where('id', '!=', auth()->id())
            ->whereNotIn('id', $partageUserIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
        return view('dossiers.partages', compact('dossier', 'partages', 'utilisateurs'));
    }

    public function storePartage(Request $request, Dossier $dossier)
    {
        $this->authorize('share', $dossier);
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'droits_lecture' => ['sometimes', 'boolean'],
            'droits_ecriture' => ['sometimes', 'boolean'],
            'droits_suppression' => ['sometimes', 'boolean'],
            'date_expiration' => ['nullable', 'date', 'after:today'],
        ]);
        $exists = DossierPartage::where('dossier_id', $dossier->id)->where('user_id', $request->user_id)->exists();
        if ($exists) {
            return back()->with('error', 'Cet utilisateur a déjà un partage sur ce dossier.');
        }
        $lecture = $request->boolean('droits_lecture', true);
        $ecriture = $request->boolean('droits_ecriture', false);
        $suppression = $request->boolean('droits_suppression', false);
        if (! $lecture && ! $ecriture && ! $suppression) {
            return back()->withInput()->with('error', 'Au moins un droit doit être accordé.');
        }
        if (($ecriture || $suppression) && ! $lecture) {
            $lecture = true;
        }
        DossierPartage::create([
            'dossier_id' => $dossier->id,
            'user_id' => $request->user_id,
            'partage_par_id' => auth()->id(),
            'droits_lecture' => $lecture,
            'droits_ecriture' => $ecriture,
            'droits_suppression' => $suppression,
            'date_expiration' => $request->filled('date_expiration') ? $request->date('date_expiration') : null,
        ]);
        return redirect()->route('dossiers.partages', $dossier)->with('success', 'Partage ajouté.');
    }

    public function updatePartage(Request $request, Dossier $dossier, DossierPartage $partage)
    {
        $this->authorize('share', $dossier);
        if ((int) $partage->dossier_id !== (int) $dossier->id) {
            abort(404);
        }
        $request->validate([
            'droits_lecture' => ['sometimes', 'boolean'],
            'droits_ecriture' => ['sometimes', 'boolean'],
            'droits_suppression' => ['sometimes', 'boolean'],
            'date_expiration' => ['nullable', 'date', 'after:today'],
        ]);
        $lecture = $request->boolean('droits_lecture');
        $ecriture = $request->boolean('droits_ecriture');
        $suppression = $request->boolean('droits_suppression');
        if (! $lecture && ! $ecriture && ! $suppression) {
            return back()->with('error', 'Au moins un droit doit être accordé.');
        }
        if (($ecriture || $suppression) && ! $lecture) {
            $lecture = true;
        }
        $partage->update([
            'droits_lecture' => $lecture,
            'droits_ecriture' => $ecriture,
            'droits_suppression' => $suppression,
            'date_expiration' => $request->filled('date_expiration') ? $request->date('date_expiration') : null,
        ]);

        return redirect()->route('dossiers.partages', $dossier)->with('success', 'Partage mis à jour.');
    }

    public function destroyPartage(Dossier $dossier, int $partage)
    {
        $this->authorize('share', $dossier);
        $p = DossierPartage::where('dossier_id', $dossier->id)->where('id', $partage)->firstOrFail();
        $p->delete();
        return redirect()->route('dossiers.partages', $dossier)->with('success', 'Partage supprimé.');
    }

    private function brancheContientLie(Dossier $dossier, $user): bool
    {
        if ($dossier->estLieA($user)) {
            return true;
        }
        foreach ($dossier->children ?? [] as $child) {
            if ($this->brancheContientLie($child, $user)) {
                return true;
            }
        }

        return false;
    }

    private function filtrerEnfantsMesDossiers(Dossier $dossier, $user): void
    {
        if (! $dossier->relationLoaded('children')) {
            return;
        }
        $dossier->setRelation('children', $dossier->children->filter(fn ($c) => $this->brancheContientLie($c, $user))
            ->each(fn ($c) => $this->filtrerEnfantsMesDossiers($c, $user)));
    }

    private function filtrerArbreRecherche($dossiers, string $q): \Illuminate\Support\Collection
    {
        $q = strtolower(trim($q));
        if ($q === '') {
            return $dossiers;
        }
        return $dossiers->filter(function ($d) use ($q) {
            return $this->noeudMatchRecherche($d, $q);
        })->each(function ($d) use ($q) {
            if ($d->relationLoaded('children')) {
                $d->setRelation('children', $this->filtrerArbreRecherche($d->children, $q));
            }
        });
    }

    private function noeudMatchRecherche(Dossier $d, string $q): bool
    {
        if (str_contains(strtolower($d->nom), $q) || str_contains(strtolower($d->chemin_complet), $q)) {
            return true;
        }
        if ($d->children->isEmpty()) {
            return false;
        }
        foreach ($d->children as $child) {
            if ($this->noeudMatchRecherche($child, $q)) {
                return true;
            }
        }

        return false;
    }

    public function show(Dossier $dossier)
    {
        $this->authorize('view', $dossier);
        Log::channel('eged')->debug('Consultation dossier', ['dossier_id' => $dossier->id, 'user_id' => auth()->id()]);
        $dossier->load(['children', 'documents' => fn ($q) => $q->with(['typeDocument', 'user', 'createur', 'dossier.partages'])]);
        $user = auth()->user();
        $dossier->documents = $dossier->documents
            ->filter(fn ($doc) => ! $doc->en_corbeille && $doc->visiblePar($user));
        $dossier->setRelation('children', $dossier->children->filter(fn ($c) => $c->visiblePar($user))
            ->each(fn ($c) => $this->filtrerEnfantsVisibles($c, $user)));
        return view('dossiers.show', compact('dossier'));
    }

    public function edit(Dossier $dossier, MesDossiersRacineService $mesDossiersRacine)
    {
        $this->authorize('update', $dossier);
        $user = auth()->user();
        $racine = $mesDossiersRacine->find($user);
        $typesDossier = TypeDossier::where('actif', true)->orderBy('libelle')->get();

        $peutChangerParent = $dossier->racine_utilisateur_id === null;
        $peutChoisirRacineOrg = false;
        $parentOptions = collect();

        if ($peutChangerParent) {
            if ($dossier->estDansArbrePersonnelMesDossiers() && $racine) {
                $parentOptions = $this->listeParentsPourArbrePersonnel($racine, $dossier);
            } else {
                $peutChoisirRacineOrg = $user->aAccesTotal()
                    || $user->can('dossiers.create-racine-structure');
                $parentOptions = $this->listeParentsPourEdition($user, $racine, $dossier);
                if ($peutChoisirRacineOrg) {
                    $parentOptions = collect([(object) [
                        'value' => '',
                        'label' => '— Racine du plan (sans dossier parent) —',
                    ]])->concat($parentOptions);
                } elseif ($dossier->parent_id === null) {
                    $parentOptions = collect([(object) [
                        'value' => '',
                        'label' => '— Conserver la position à la racine —',
                    ]])->concat($parentOptions);
                }
            }
        }

        return view('dossiers.edit', compact(
            'dossier',
            'typesDossier',
            'parentOptions',
            'peutChangerParent',
            'peutChoisirRacineOrg'
        ));
    }

    public function update(Request $request, Dossier $dossier, MesDossiersRacineService $mesDossiersRacine)
    {
        $this->authorize('update', $dossier);
        $user = auth()->user();
        $racine = $mesDossiersRacine->find($user);

        if ($request->has('parent_id') && $request->input('parent_id') === '') {
            $request->merge(['parent_id' => null]);
        }

        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'type_dossier_id' => ['nullable', 'integer', 'exists:type_dossiers,id'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:dossiers,id'],
            'est_racine_org' => ['sometimes', 'boolean'],
        ]);

        $typeDossier = isset($data['type_dossier_id']) ? TypeDossier::find($data['type_dossier_id']) : null;
        $typeString = $typeDossier?->code;
        $confidentiel = $typeString === 'confidentiel';

        $wantedParent = $dossier->racine_utilisateur_id !== null
            ? $dossier->parent_id
            : ($request->has('parent_id') ? $data['parent_id'] : $dossier->parent_id);

        if ($dossier->racine_utilisateur_id === null && $wantedParent !== null) {
            if (in_array($wantedParent, $this->idsDescendantsDossier((int) $dossier->id), true)) {
                return back()->withInput()->withErrors(['parent_id' => 'Impossible de placer un dossier sous lui-même ou sous un de ses sous-dossiers.']);
            }
            if ($wantedParent === (int) $dossier->id) {
                return back()->withInput()->withErrors(['parent_id' => 'Emplacement invalide.']);
            }
            $parentModel = Dossier::query()->where('actif', true)->find($wantedParent);
            if (! $parentModel || ! $parentModel->visiblePar($user)) {
                return back()->withInput()->withErrors(['parent_id' => 'Dossier parent introuvable ou inaccessible.']);
            }
            if (! $this->parentAutorisePourCreation($user, $parentModel, $racine)) {
                return back()->withInput()->withErrors(['parent_id' => 'Vous ne pouvez pas placer le dossier sous cet emplacement.']);
            }
            if (! $dossier->estDansArbrePersonnelMesDossiers() && $parentModel->estDansArbrePersonnelMesDossiers()) {
                return back()->withInput()->withErrors(['parent_id' => 'Un dossier du plan organisationnel ne peut pas être placé sous « Mes dossiers ».']);
            }
            if ($racine && $dossier->estDansArbrePersonnelMesDossiers() && ! Dossier::estSousRacineMesDossiers($parentModel, $racine)) {
                return back()->withInput()->withErrors(['parent_id' => 'Le dossier parent doit rester dans votre espace « Mes dossiers ».']);
            }
        }

        if ($wantedParent === null && ! $dossier->estDansArbrePersonnelMesDossiers()) {
            $peutRacine = $user->aAccesTotal() || $user->can('dossiers.create-racine-structure');
            if ($dossier->parent_id !== null && ! $peutRacine) {
                return back()->withInput()->withErrors(['parent_id' => 'Vous n’avez pas la permission de placer un dossier à la racine du plan.']);
            }
        }

        if ($dossier->estDansArbrePersonnelMesDossiers() && ! $dossier->racine_utilisateur_id && $wantedParent === null) {
            return back()->withInput()->withErrors(['parent_id' => 'Choisissez un dossier parent dans votre espace personnel.']);
        }

        $parentChanged = ((int) ($dossier->parent_id ?? 0)) !== ((int) ($wantedParent ?? 0));
        $repositionne = $dossier->racine_utilisateur_id === null && $parentChanged;

        $estRacineOrg = (bool) $dossier->est_racine_org;
        $structureIdResolved = $dossier->structure_id;
        $proprietaireId = (int) $dossier->proprietaire_id;

        if ($repositionne) {
            if ($wantedParent === null && ! $dossier->estDansArbrePersonnelMesDossiers()) {
                $estRacineOrg = $dossier->parent_id === null
                    ? (bool) $dossier->est_racine_org
                    : ($request->boolean('est_racine_org') && ($user->aAccesTotal() || $user->can('dossiers.create-racine-structure')));
            } else {
                $estRacineOrg = false;
            }

            if ($wantedParent !== null) {
                $parentModel = Dossier::find($wantedParent);
                if ($racine && Dossier::estSousRacineMesDossiers($parentModel, $racine)) {
                    $sid = $parentModel->structure_id ?? $parentModel->structure_id_depot;
                    $structureIdResolved = $sid ?: $user->structure_id;
                    $proprietaireId = (int) $user->id;
                } else {
                    $sid = $parentModel->structure_id ?? $parentModel->structure_id_depot;
                    if ($sid) {
                        $structureIdResolved = (int) $sid;
                        $proprietaireId = $this->proprietaireIdPourStructure($structureIdResolved, $user->id);
                    }
                }
            } else {
                $sid = (int) ($dossier->structure_id ?: $dossier->structure_id_depot ?: $user->structure_id ?: 0);
                if ($sid > 0) {
                    $structureIdResolved = $sid;
                    $proprietaireId = $this->proprietaireIdPourStructure($sid, $user->id);
                }
            }
        }

        DB::transaction(function () use (
            $dossier,
            $repositionne,
            $wantedParent,
            $structureIdResolved,
            $proprietaireId,
            $estRacineOrg,
            $data,
            $typeString,
            $confidentiel,
            $user
        ) {
            $ordre = (int) $dossier->ordre;

            if ($repositionne && $wantedParent === null && ! $dossier->estDansArbrePersonnelMesDossiers()) {
                $sid = (int) ($dossier->structure_id ?: $dossier->structure_id_depot ?: $user->structure_id ?: 0);
                $ordre = $this->ordrePourNouvelleRacineOrganisation($sid);
            } elseif ($repositionne && $wantedParent !== null) {
                $ordre = (int) (Dossier::query()->where('parent_id', $wantedParent)->max('ordre') ?? -1) + 1;
            }

            $dossier->update([
                'parent_id' => $wantedParent,
                'ordre' => $ordre,
                'structure_id' => $structureIdResolved ?: $dossier->structure_id,
                'proprietaire_id' => $proprietaireId,
                'est_racine_org' => $estRacineOrg,
                'nom' => $data['nom'],
                'type_dossier_id' => $data['type_dossier_id'] ?? null,
                'type' => $typeString,
                'description' => $data['description'] ?? null,
                'confidentiel' => $confidentiel,
            ]);
        });

        Log::channel('eged')->info('Dossier modifié', [
            'dossier_id' => $dossier->id,
            'user_id' => auth()->id(),
            'parent_id' => $wantedParent,
        ]);

        return redirect()
            ->route('dossiers.show', $dossier)
            ->with('success', 'Dossier mis à jour.');
    }

    public function destroy(Dossier $dossier)
    {
        $this->authorize('delete', $dossier);
        if ($dossier->racine_utilisateur_id !== null && ! auth()->user()->aAccesTotal()) {
            return redirect()
                ->route('dossiers.show', $dossier)
                ->with('error', 'La racine « Mes dossiers » ne peut être supprimée que par un administrateur.');
        }
        if (Dossier::where('parent_id', $dossier->id)->exists()) {
            return redirect()
                ->route('dossiers.show', $dossier)
                ->with('error', 'Supprimez ou déplacez d’abord les sous-dossiers.');
        }
        if ($dossier->documents()->exists()) {
            return redirect()
                ->route('dossiers.show', $dossier)
                ->with('error', 'Retirez ou supprimez d’abord les documents de ce dossier.');
        }
        $parentId = $dossier->parent_id;
        $nom = $dossier->nom;
        $dossier->delete();
        Log::channel('eged')->info('Dossier supprimé', ['ancien_parent_id' => $parentId, 'nom' => $nom, 'user_id' => auth()->id()]);

        if ($parentId) {
            return redirect()
                ->route('dossiers.show', Dossier::findOrFail($parentId))
                ->with('success', 'Dossier supprimé.');
        }

        return redirect()
            ->route('dossiers.index')
            ->with('success', 'Dossier supprimé.');
    }

    private function filtrerEnfantsVisibles(Dossier $dossier, $user): void
    {
        if (! $dossier->relationLoaded('children')) {
            return;
        }
        $dossier->setRelation('children', $dossier->children->filter(fn ($c) => $c->visiblePar($user))
            ->each(fn ($c) => $this->filtrerEnfantsVisibles($c, $user)));
    }
}
