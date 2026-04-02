<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentValidation;
use App\Models\Dossier;
use App\Models\HistoriqueDocument;
use App\Models\JournalAudit;
use App\Models\Structure;
use App\Models\StatutDocument;
use App\Models\TypeDocument;
use App\Models\User;
use App\Models\VersionDocument;
use App\Models\WorkflowEtape;
use App\Notifications\DocumentDeposeNotification;
use App\Notifications\DocumentValidationDemandeNotification;
use App\Notifications\DocumentValidationResultNotification;
use App\Services\MetadonneeExtracteur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Document::class);
        $query = Document::horsCorbeille()
            ->visibleBy(auth()->user())
            ->with(['typeDocument', 'user', 'statutDocument', 'workflowEtapeActuelle', 'validations', 'dossier' => fn ($q) => $q->with(['parent' => fn ($q2) => $q2->with('parent')])])
            ->latest();
        if ($request->filled('type')) {
            $query->where('type_document_id', $request->type);
        }
        if ($request->filled('dossier')) {
            $query->where('dossier_id', $request->dossier);
        }
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($qry) use ($q) {
                $qry->where('nom_original', 'like', "%{$q}%")
                    ->orWhere('titre', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%")
                    ->orWhere('mots_cles', 'like', "%{$q}%");
            });
        }
        $documents = $query->paginate(15)->withQueryString();
        $types = TypeDocument::where('actif', true)->orderBy('libelle')->get();
        [$dossiersPerso, $dossiersPlan] = $this->dossiersVisiblesGroupesPersoPuisPlan(auth()->user());
        $dossiers = $dossiersPerso->concat($dossiersPlan);
        $utilisateursPourEnvoi = User::where('actif', true)
            ->where('id', '!=', auth()->id())
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $typeIdsPage = $documents->getCollection()->pluck('type_document_id')->unique()->filter()->map(fn ($id) => (int) $id)->values()->all();
        $typesById = $typeIdsPage === []
            ? collect()
            : TypeDocument::whereIn('id', $typeIdsPage)->get()->keyBy('id');
        $workflowContextByTypeId = [];
        foreach ($typeIdsPage as $tid) {
            $libelle = $typesById->get($tid)?->libelle ?? null;
            $workflowContextByTypeId[$tid] = WorkflowEtape::contexteEnvoiPourType($tid, $libelle);
        }
        $workflowContextSansType = WorkflowEtape::contexteEnvoiPourType(null, null);

        return view('documents.index', compact('documents', 'types', 'dossiers', 'utilisateursPourEnvoi', 'workflowContextByTypeId', 'workflowContextSansType'));
    }

    public function create(Request $request)
    {
       
        $this->authorize('create', Document::class);
        $types = TypeDocument::where('actif', true)->orderBy('libelle')->get();
        [$dossiersPersoDepot, $dossiersPlanDepot] = $this->dossiersDepotGroupesPersoPuisPlan(auth()->user());

        return view('documents.create', compact('types', 'dossiersPersoDepot', 'dossiersPlanDepot'));
    }

    public function store(Request $request)
    {

        $this->authorize('create', Document::class);
        $request->validate([
            'type_document_id' => ['required', 'exists:type_documents,id'],
            'dossier_id' => ['nullable', 'exists:dossiers,id'],
            'fichier' => ['required', 'file', 'max:10240'], // 10 Mo
            'titre' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
            'mots_cles' => ['nullable', 'string'],
            'confidentiel' => ['nullable', 'boolean'],
        ]);

        if ($request->dossier_id) {
            $dossier = Dossier::find($request->dossier_id);
            if (! $dossier || ! $dossier->peuxDeposer(auth()->user())) {
                return back()->withInput()->with('error', 'Vous n\'avez pas le droit de déposer dans ce dossier.');
            }
        }

        $file = $request->file('fichier');
        $typeDoc = TypeDocument::findOrFail($request->type_document_id);

        if ($file->getSize() > $typeDoc->taille_max_bytes) {
            Log::channel('eged')->warning('Document rejeté : fichier trop volumineux', ['user_id' => auth()->id(), 'nom' => $file->getClientOriginalName()]);

            return back()->withInput()->with('error', 'Fichier trop volumineux (max '.$typeDoc->taille_max_ko.' Ko).');
        }

        $path = $file->store('documents/'.date('Y/m'), 'public');
        $ext = strtolower($file->getClientOriginalExtension());
        $empreinte = hash_file('sha256', $file->getRealPath());
        $statutBrouillon = StatutDocument::where('code', 'brouillon')->first();

        $document = Document::create([
            'type_document_id' => $request->type_document_id,
            'dossier_id' => $request->dossier_id ?: null,
            'user_id' => auth()->id(),
            'createur_id' => auth()->id(),
            'proprietaire_id' => auth()->id(),
            'statut_document_id' => $statutBrouillon?->id,
            'nom_original' => $file->getClientOriginalName(),
            'chemin' => $path,
            'extension' => $ext,
            'taille_octets' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'empreinte' => $empreinte,
            'titre' => $request->titre,
            'description' => $request->description,
            'reference' => $request->reference,
            'mots_cles' => $request->mots_cles,
            'confidentiel' => (bool) $request->confidentiel,
            'statut' => 'brouillon',
        ]);

        VersionDocument::create([
            'document_id' => $document->id,
            'numero' => 1,
            'chemin' => $path,
            'nom_fichier' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'taille_octets' => $file->getSize(),
            'empreinte' => $empreinte,
            'auteur_id' => auth()->id(),
            'est_actuel' => true,
        ]);

        HistoriqueDocument::enregistrer($document, 'depot', null, 'Version initiale');
        JournalAudit::log('document.depot', 'documents', ['document_id' => $document->id]);

        try {
            app(MetadonneeExtracteur::class)->extrairePourDocument($document);
        } catch (\Throwable $e) {
            Log::channel('eged')->warning('Extraction métadonnées échouée', ['document_id' => $document->id, 'error' => $e->getMessage()]);
        }

        $auteurId = auth()->id();
        $notifiables = collect();

        // Acteurs métier uniquement : propriétaire du dossier, partages (admin exclu)
        // Propriétaire du dossier (si différent de l'auteur)
        if ($document->dossier_id) {
            $dossier = Dossier::with(['proprietaire', 'partages.user'])->find($document->dossier_id);
            if ($dossier) {
                if ($dossier->proprietaire_id && $dossier->proprietaire_id !== $auteurId) {
                    $notifiables->push($dossier->proprietaire);
                }
                foreach ($dossier->partages as $p) {
                    if ($p->droits_lecture && ! $p->isExpire() && $p->user_id !== $auteurId) {
                        $notifiables->push($p->user);
                    }
                }
            }
        }

        $notifiables = $notifiables->unique('id')->filter(fn ($u) => $u->id !== $auteurId);
        if ($notifiables->isNotEmpty()) {
            Notification::send($notifiables, new DocumentDeposeNotification($document, auth()->user()));
        }

        Log::channel('eged')->info('Document déposé', ['document_id' => $document->id, 'user_id' => auth()->id(), 'nom' => $document->nom_original]);

        $redirect = $request->dossier_id
            ? redirect()->route('dossiers.show', $request->dossier_id)
            : redirect()->route('documents.index');

        return $redirect->with('success', 'Document enregistré.');
    }

    public function fiche(Document $document)
    {
        $this->authorize('view', $document);
        $document->load(['typeDocument', 'dossier.parent.parent', 'user', 'statutDocument', 'metadonnees.typeMetadonnee', 'versions', 'historiques.user']);

        return view('documents.fiche', compact('document'));
    }

    public function edit(Document $document)
    {
        $this->authorize('update', $document);
        $types = TypeDocument::where('actif', true)->orderBy('libelle')->get();
        [$dossiersPersoDepot, $dossiersPlanDepot] = $this->dossiersDepotGroupesPersoPuisPlan(auth()->user());
        $document->load('metadonnees.typeMetadonnee');

        return view('documents.edit', compact('document', 'types', 'dossiersPersoDepot', 'dossiersPlanDepot'));
    }

    public function extraireMetadonnees(Document $document)
    {
        $this->authorize('update', $document);
        $count = app(MetadonneeExtracteur::class)->extrairePourDocument($document);

        return back()->with('success', $count > 0 ? "{$count} métadonnée(s) extraite(s)." : 'Aucune métadonnée extraite (format non supporté ou PDF sans métadonnées).');
    }

    public function nouvelleVersion(Document $document)
    {
        $this->authorize('update', $document);
        $document->load('typeDocument', 'versions');

        return view('documents.nouvelle-version', compact('document'));
    }

    public function storeNouvelleVersion(Request $request, Document $document)
    {
        $this->authorize('update', $document);
        $typeDoc = $document->typeDocument;
        $tailleMaxKo = $typeDoc ? (int) $typeDoc->taille_max_ko : 10240;
        $tailleMaxKo = $tailleMaxKo > 0 ? min(10240, $tailleMaxKo) : 10240;

        $request->validate([
            'fichier' => ['required', 'file', 'max:'.$tailleMaxKo],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ]);

        $file = $request->file('fichier');
        if ($typeDoc && $file->getSize() > $typeDoc->taille_max_bytes) {
            return back()->withInput()->with('error', 'Fichier trop volumineux (max '.$typeDoc->taille_max_ko.' Ko pour ce type).');
        }

        $path = $file->store('documents/'.date('Y/m'), 'public');
        $ext = strtolower($file->getClientOriginalExtension());
        $empreinte = hash_file('sha256', $file->getRealPath());
        $prochainNumero = $document->versions()->max('numero') + 1;

        $document->versions()->update(['est_actuel' => false]);

        $nouvelleVersion = VersionDocument::create([
            'document_id' => $document->id,
            'numero' => $prochainNumero,
            'chemin' => $path,
            'nom_fichier' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'taille_octets' => $file->getSize(),
            'empreinte' => $empreinte,
            'commentaire' => $request->commentaire,
            'auteur_id' => auth()->id(),
            'est_actuel' => true,
        ]);

        $statutCodeAvant = strtolower($document->statutDocument?->code ?? $document->statut ?? '');
        $repasserEnBrouillon = in_array($statutCodeAvant, ['valide', 'validé', 'en_attente']);

        $donneesUpdate = [
            'chemin' => $path,
            'nom_original' => $file->getClientOriginalName(),
            'extension' => $ext,
            'taille_octets' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'empreinte' => $empreinte,
            'modificateur_id' => auth()->id(),
        ];

        if ($repasserEnBrouillon) {
            $statutBrouillon = StatutDocument::where('code', 'brouillon')->first();
            $donneesUpdate['statut_document_id'] = $statutBrouillon?->id;
            $donneesUpdate['statut'] = 'brouillon';
            $donneesUpdate['workflow_etape_actuelle_id'] = null;
            $donneesUpdate['workflow_validateur_id'] = null;
            $donneesUpdate['workflow_validation_chain'] = null;
            $donneesUpdate['workflow_etape_index'] = 0;
        }

        $document->update($donneesUpdate);

        $commentaireHistorique = 'Version '.$prochainNumero.($request->commentaire ? ' : '.$request->commentaire : '');
        if ($repasserEnBrouillon) {
            $commentaireHistorique .= ' — Document repassé en Déposé pour re-validation';
        }
        HistoriqueDocument::enregistrer($document, 'nouvelle_version', $nouvelleVersion->id, $commentaireHistorique);
        JournalAudit::log('document.nouvelle_version', 'documents', ['document_id' => $document->id, 'version' => $prochainNumero]);

        try {
            app(MetadonneeExtracteur::class)->extrairePourDocument($document);
        } catch (\Throwable $e) {
            Log::channel('eged')->warning('Extraction métadonnées échouée', ['document_id' => $document->id, 'error' => $e->getMessage()]);
        }

        Log::channel('eged')->info('Nouvelle version déposée', ['document_id' => $document->id, 'version' => $prochainNumero, 'user_id' => auth()->id()]);

        $messageSuccess = 'Nouvelle version (v'.$prochainNumero.') enregistrée.';
        if ($repasserEnBrouillon) {
            $messageSuccess .= ' Le document est repassé en Déposé — envoyez-le en validation pour le faire re-valider.';
        }

        return redirect()->route('documents.edit', $document)->with('success', $messageSuccess);
    }

    public function update(Request $request, Document $document)
    {
        $this->authorize('update', $document);
        $request->validate([
            'type_document_id' => ['required', 'exists:type_documents,id'],
            'dossier_id' => ['nullable', 'exists:dossiers,id'],
            'titre' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
            'mots_cles' => ['nullable', 'string'],
            'confidentiel' => ['nullable', 'boolean'],
        ]);

        if ($request->dossier_id) {
            $dossier = Dossier::find($request->dossier_id);
            if (! $dossier || ! $dossier->peuxDeposer(auth()->user())) {
                return back()->withInput()->with('error', 'Vous n\'avez pas le droit de déposer dans ce dossier.');
            }
        }

        $donneesAvant = $document->only(['titre', 'description', 'reference', 'mots_cles', 'dossier_id', 'type_document_id', 'confidentiel']);
        $document->update([
            'type_document_id' => $request->type_document_id,
            'dossier_id' => $request->dossier_id ?: null,
            'titre' => $request->titre,
            'description' => $request->description,
            'reference' => $request->reference,
            'mots_cles' => $request->mots_cles,
            'confidentiel' => (bool) $request->confidentiel,
            'modificateur_id' => auth()->id(),
        ]);

        HistoriqueDocument::enregistrer($document, 'modification', null, null, ['avant' => $donneesAvant]);
        JournalAudit::log('document.modification', 'documents', [
            'document_id' => $document->id,
            'donnees_avant' => json_encode($donneesAvant),
        ]);

        Log::channel('eged')->info('Document mis à jour', ['document_id' => $document->id, 'user_id' => auth()->id()]);

        return redirect()->route('documents.index')->with('success', 'Document mis à jour.');
    }

    public function show(Document $document)
    {
        $this->authorize('view', $document);

        return response()->file(Storage::disk('public')->path($document->chemin), [
            'Content-Type' => Storage::disk('public')->mimeType($document->chemin),
            'Content-Disposition' => 'inline; filename="'.$document->nom_original.'"',
        ]);
    }

    public function download(Document $document)
    {
        $this->authorize('view', $document);

        return Storage::disk('public')->download(
            $document->chemin,
            $document->nom_original
        );
    }

    public function valider(Request $request, Document $document)
    {
        $this->authorize('update', $document);
        $statutValide = StatutDocument::where('code', 'valide')->first();
        if (! $statutValide) {
            return back()->with('error', 'Statut « Validé » non configuré.');
        }
        $statutCode = strtolower($document->statutDocument?->code ?? $document->statut ?? '');
        if (! in_array($statutCode, ['brouillon'])) {
            return back()->with('error', 'Seul un document au statut Déposé peut être validé (validation directe).');
        }
        $document->update([
            'statut_document_id' => $statutValide->id,
            'statut' => 'valide',
            'workflow_etape_actuelle_id' => null,
            'modificateur_id' => auth()->id(),
        ]);
        HistoriqueDocument::enregistrer($document, 'validation', null, 'Document validé (direct)');
        JournalAudit::log('document.validation', 'documents', ['document_id' => $document->id]);
        Log::channel('eged')->info('Document validé', ['document_id' => $document->id, 'user_id' => auth()->id()]);

        return redirect()->back()->with('success', 'Document validé.');
    }

    public function envoyerEnValidation(Request $request, Document $document)
    {
        $this->authorize('envoyerValidation', $document);
        $request->validate([
            'destinataire_id' => ['required', 'exists:users,id'],
        ], [
            'destinataire_id.required' => 'Veuillez choisir un destinataire pour l’envoi en validation.',
        ]);

        $statutCode = strtolower($document->statutDocument?->code ?? $document->statut ?? '');
        if (! in_array($statutCode, ['brouillon', 'rejete'])) {
            return back()->with('error', 'Seul un document déposé ou rejeté peut être envoyé en validation.');
        }
        $etape = WorkflowEtape::premiereEtapePour($document->type_document_id);
        if (! $etape) {
            return back()->with('error', 'Aucune étape de workflow configurée pour ce type de document ni en global.');
        }
        // Log pour corréler le workflow résolu (type prioritaire, sinon global) et l'erreur éventuelle côté UI.
        Log::channel('eged')->info('Workflow résolu avant envoi en validation', [
            'document_id' => (int) $document->id,
            'type_document_id' => (int) ($document->type_document_id ?? 0),
            'workflow_etape_id' => (int) ($etape->id ?? 0),
            'workflow_etape_nom' => (string) ($etape->nom ?? ''),
            'workflow_etape_code' => (string) ($etape->code ?? ''),
            'validation_hierarchique' => (bool) ($etape->validation_hierarchique ?? false),
            'validateur_id' => $etape->validateur_id,
            'role_requis' => $etape->role_requis,
            'user_id' => (int) auth()->id(),
        ]);
        $statutEnAttente = StatutDocument::where('code', 'en_attente')->first();
        if (! $statutEnAttente) {
            return back()->with('error', 'Statut « En attente » non configuré.');
        }

        $destinataire = User::where('actif', true)->whereKey($request->destinataire_id)->first();
        if (! $destinataire) {
            return back()->with('error', 'Destinataire invalide ou compte inactif.');
        }
        if ((int) $destinataire->id === (int) auth()->id()) {
            return back()->with('error', 'Vous ne pouvez pas vous choisir comme destinataire.');
        }

        $workflowValidateurId = null;
        $workflowValidationChain = null;

        if ($etape->validation_hierarchique) {
            $chain = $this->chaineValidateursHierarchique($document);
            if (empty($chain)) {
                return back()->with('error', 'Aucune chaîne de validation hiérarchique trouvée (fonction / titulaire ou structure non configuré).');
            }
            $chain = $this->chaineSansValidateurCourantEnTete($chain, auth()->id());
            if (empty($chain)) {
                return back()->with('error', 'Vous êtes le seul titulaire prévu dans la chaîne pour votre structure. Aucun valideur de niveau supérieur n’est configuré — vérifiez les fonctions sur la structure parente (ex. DG) et les affectations utilisateurs, ou utilisez la validation directe si votre rôle le permet.');
            }
            $destId = (int) $destinataire->id;
            $index = null;
            foreach ($chain as $i => $uid) {
                if ((int) $uid === $destId) {
                    $index = $i;
                    break;
                }
            }
            if ($index === null) {
                return back()->with('error', 'Le destinataire choisi ne figure pas dans la chaîne hiérarchique prévue pour ce document.');
            }
            $chain = array_values(array_slice($chain, $index));
            $workflowValidationChain = $chain;
            $workflowValidateurId = $chain[0];
        } elseif ($etape->validateur_id) {
            if ((int) $destinataire->id !== (int) $etape->validateur_id) {
                return back()->with('error', 'Le destinataire doit être le validateur désigné pour cette étape.');
            }
            $workflowValidateurId = $destinataire->id;
        } elseif ($etape->role_requis) {
            if (! $destinataire->hasRole($etape->role_requis)) {
                return back()->with('error', 'Le destinataire choisi n’a pas le rôle requis pour cette étape.');
            }
            $workflowValidateurId = $destinataire->id;
        } else {
            // Log détaillé pour diagnostiquer les problèmes de configuration workflow
            Log::channel('eged')->error('Workflow etape incomplète (aucun mode de validation) lors de l’envoi', [
                'document_id' => (int) $document->id,
                'type_document_id' => (int) ($document->type_document_id ?? 0),
                'workflow_etape_id' => (int) ($etape->id ?? 0),
                'workflow_etape_nom' => (string) ($etape->nom ?? ''),
                'workflow_etape_code' => (string) ($etape->code ?? ''),
                'validation_hierarchique' => (bool) ($etape->validation_hierarchique ?? false),
                'validateur_id' => $etape->validateur_id,
                'role_requis' => $etape->role_requis,
                'destinataire_id' => (int) $destinataire->id,
                'admin_user_id' => (int) auth()->id(),
            ]);

            $typeLibelle = $document->typeDocument?->libelle ?? 'ce type de document';
            $stepNom = $etape->nom ?? 'l’étape';
            $stepCode = $etape->code ? ' (code: '.$etape->code.')' : '';
            $stepModeAttendu = 'hiérarchique, validateur fixe ou rôle requis';

            return back()->with('error',
                'Impossible d’envoyer en validation : l’étape « '.$stepNom.' »'.$stepCode.' du workflow pour le type « '.$typeLibelle.' » est mal configurée. Aucun mode de validation n’est défini (attendu : '.$stepModeAttendu.'). '.
                'Allez dans Paramètres → Workflow, cherchez cette étape, puis renseignez un mode de validation.'
            );
        }

        $document->update([
            'statut_document_id' => $statutEnAttente->id,
            'statut' => 'en_attente',
            'workflow_etape_actuelle_id' => $etape->id,
            'workflow_validateur_id' => $workflowValidateurId,
            'workflow_validation_chain' => $workflowValidationChain,
            'workflow_etape_index' => 0,
            'modificateur_id' => auth()->id(),
        ]);
        HistoriqueDocument::enregistrer(
            $document,
            'workflow_envoi',
            null,
            'Envoyé en validation : '.$etape->nom.' — destinataire : '.$destinataire->name
        );
        JournalAudit::log('document.workflow_envoi', 'documents', [
            'document_id' => $document->id,
            'etape_id' => $etape->id,
            'destinataire_id' => $destinataire->id,
        ]);

        $destinataire->notify(new DocumentValidationDemandeNotification($document->fresh(), auth()->user(), $etape));
        Log::channel('eged')->info('Document envoyé en validation', [
            'document_id' => $document->id,
            'user_id' => auth()->id(),
            'etape' => $etape->nom,
            'destinataire_id' => $destinataire->id,
        ]);

        return redirect()->back()->with('success', 'Document envoyé en validation à '.$destinataire->name.'.');
    }

    /**
     * Calcule la chaîne complète des validateurs hiérarchiques : du responsable de la structure
     * du créateur jusqu'au DG. Chaque niveau doit viser avant de passer au suivant.
     * Ex: agent DAF → [Directeur DAF, DG]
     * Ex: agent Service sous Direction → [Chef service, Directeur, DG]
     */
    private function chaineValidateursHierarchique(Document $document): array
    {
        // Règle métier :
        // — la chaîne hiérarchique doit être calculée à partir du "service propriétaire du dossier"
        //   (dossier.structure_id_depot), pas seulement à partir de la structure du déposant.
        $structure = null;
        $sourceStructure = 'deposant';
        $dossier = $document->dossier ?? $document->dossier()->first();
        if ($dossier) {
            $structureIdDepot = (int) ($dossier->structure_id_depot ?? 0);
            if ($structureIdDepot > 0) {
                $structure = Structure::find($structureIdDepot);
                $sourceStructure = 'dossier_depot';
            }
        }

        // Fallback : si le document n'a pas de dossier (ou dossier sans structure), on retombe sur la structure du déposant.
        if (! $structure) {
            $createur = $document->createur ?? $document->user;
            if (! $createur) {
                return [];
            }

            $structure = $createur->structurePourValidationHierarchique();
        }

        if (! $structure) {
            return [];
        }

        Log::channel('eged')->info('Chaine hiérarchique calculée pour l’envoi en validation', [
            'document_id' => (int) $document->id,
            'dossier_id' => $document->dossier_id ? (int) $document->dossier_id : null,
            'source_structure' => $sourceStructure,
            'start_structure_id' => (int) $structure->id,
            'start_structure_code' => (string) ($structure->code ?? ''),
        ]);

        $chain = [];
        $current = $structure;
        $previousTitulaireId = null;
        while ($current) {
            $titulaire = $current->titulaireValidationActuel();
            if (! $titulaire) {
                return [];
            }
            $tid = (int) $titulaire->id;
            if ($previousTitulaireId !== $tid) {
                $chain[] = $tid;
            }
            $previousTitulaireId = $tid;
            $current = $current->parent;
        }

        return $chain;
    }

    /**
     * Retire du début de la chaîne tout validateur qui est l’utilisateur courant (déposant),
     * jusqu’au premier ID différent. Évite les blocages lorsque le responsable de structure
     * envoie lui-même son document en validation.
     *
     * @param  array<int>  $chain
     * @return array<int>
     */
    private function chaineSansValidateurCourantEnTete(array $chain, int $userId): array
    {
        $chain = array_values($chain);
        while ($chain !== [] && (int) $chain[0] === (int) $userId) {
            array_shift($chain);
        }

        return array_values($chain);
    }

    public function approuver(Request $request, Document $document)
    {
        $this->authorize('approuver', $document);
        $etape = $document->workflowEtapeActuelle;
        if (! $etape || ! $etape->peutValider(auth()->user(), $document)) {
            return back()->with('error', 'Vous n\'êtes pas autorisé à valider ce document à cette étape.');
        }
        $statutCode = strtolower($document->statutDocument?->code ?? $document->statut ?? '');
        if (! in_array($statutCode, ['en_attente'])) {
            return back()->with('error', 'Ce document n\'est pas en attente de validation.');
        }

        DocumentValidation::create([
            'document_id' => $document->id,
            'workflow_etape_id' => $etape->id,
            'user_id' => auth()->id(),
            'action' => DocumentValidation::ACTION_APPROBATION,
            'commentaire' => $request->commentaire,
        ]);

        // Workflow hiérarchique multi-étapes : chaîne de validation
        if ($etape->validation_hierarchique && is_array($document->workflow_validation_chain) && ! empty($document->workflow_validation_chain)) {
            $chain = $document->workflow_validation_chain;
            $index = (int) ($document->workflow_etape_index ?? 0);
            $prochaineIndex = $index + 1;

            if ($prochaineIndex >= count($chain)) {
                $statutValide = StatutDocument::where('code', 'valide')->first();
                $document->update([
                    'statut_document_id' => $statutValide?->id,
                    'statut' => 'valide',
                    'workflow_etape_actuelle_id' => null,
                    'workflow_validateur_id' => null,
                    'workflow_validation_chain' => null,
                    'workflow_etape_index' => 0,
                    'modificateur_id' => auth()->id(),
                ]);
                HistoriqueDocument::enregistrer($document, 'workflow_approbation', null, 'Validé par '.auth()->user()->name.' (dernier visa)');
                JournalAudit::log('document.workflow_approbation', 'documents', ['document_id' => $document->id]);

                $createur = $document->createur ?? $document->user;
                if ($createur && $createur->id !== auth()->id()) {
                    $createur->notify(new DocumentValidationResultNotification($document, auth()->user(), true, $request->commentaire));
                }
                Log::channel('eged')->info('Document validé (workflow hiérarchique complet)', ['document_id' => $document->id, 'user_id' => auth()->id()]);

                return redirect()->back()->with('success', 'Document validé.');
            }

            $prochainValidateurId = $chain[$prochaineIndex];
            $document->update([
                'workflow_validateur_id' => $prochainValidateurId,
                'workflow_etape_index' => $prochaineIndex,
                'modificateur_id' => auth()->id(),
            ]);
            HistoriqueDocument::enregistrer($document, 'workflow_approbation', null, 'Visa de '.auth()->user()->name.' — passage à l\'étape suivante');

            $prochainValidateur = User::find($prochainValidateurId);
            if ($prochainValidateur) {
                $prochainValidateur->notify(new DocumentValidationDemandeNotification($document->fresh(), auth()->user(), $etape));
            }
            Log::channel('eged')->info('Document : étape validée, passage au validateur suivant', ['document_id' => $document->id, 'next_user_id' => $prochainValidateurId]);

            return redirect()->back()->with('success', 'Visa enregistré. Document transmis à l\'étape suivante.');
        }

        // Workflow classique (étapes configurées)
        if ($etape->est_derniere_etape) {
            $statutValide = StatutDocument::where('code', 'valide')->first();
            $document->update([
                'statut_document_id' => $statutValide?->id,
                'statut' => 'valide',
                'workflow_etape_actuelle_id' => null,
                'workflow_validateur_id' => null,
                'workflow_validation_chain' => null,
                'workflow_etape_index' => 0,
                'modificateur_id' => auth()->id(),
            ]);
            HistoriqueDocument::enregistrer($document, 'workflow_approbation', null, 'Approuvé par '.auth()->user()->name);
            JournalAudit::log('document.workflow_approbation', 'documents', ['document_id' => $document->id]);

            $createur = $document->createur ?? $document->user;
            if ($createur && $createur->id !== auth()->id()) {
                $createur->notify(new DocumentValidationResultNotification($document, auth()->user(), true, $request->commentaire));
            }
            Log::channel('eged')->info('Document approuvé (workflow)', ['document_id' => $document->id, 'user_id' => auth()->id()]);

            return redirect()->back()->with('success', 'Document validé.');
        }

        $etapeSuivante = $etape->etapeSuivante;
        if (! $etapeSuivante) {
            return back()->with('error', 'Étape suivante non configurée.');
        }
        $document->update([
            'workflow_etape_actuelle_id' => $etapeSuivante->id,
            'workflow_validateur_id' => null,
            'workflow_validation_chain' => null,
            'workflow_etape_index' => 0,
            'modificateur_id' => auth()->id(),
        ]);
        HistoriqueDocument::enregistrer($document, 'workflow_approbation', null, 'Approuvé, passage à : '.$etapeSuivante->nom);

        $validateurs = collect();
        if ($etapeSuivante->validateur_id) {
            $validateurs->push($etapeSuivante->validateur);
        } elseif ($etapeSuivante->role_requis) {
            $validateurs = User::role($etapeSuivante->role_requis)->get();
        }
        $validateurs = $validateurs->filter(fn ($u) => $u && $u->id !== auth()->id())->unique('id');
        if ($validateurs->isNotEmpty()) {
            Notification::send($validateurs, new DocumentValidationDemandeNotification($document, auth()->user(), $etapeSuivante));
        }

        return redirect()->back()->with('success', 'Document approuvé. Étape suivante : '.$etapeSuivante->nom);
    }

    public function rejeter(Request $request, Document $document)
    {
        $this->authorize('rejeter', $document);
        $request->validate([
            'commentaire' => ['required', 'string', 'max:1000'],
        ], [
            'commentaire.required' => 'Le motif du rejet est obligatoire.',
        ]);
        $etape = $document->workflowEtapeActuelle;
        if (! $etape || ! $etape->peutValider(auth()->user(), $document)) {
            return back()->with('error', 'Vous n\'êtes pas autorisé à rejeter ce document.');
        }
        $statutCode = strtolower($document->statutDocument?->code ?? $document->statut ?? '');
        if (! in_array($statutCode, ['en_attente'])) {
            return back()->with('error', 'Ce document n\'est pas en attente de validation.');
        }

        DocumentValidation::create([
            'document_id' => $document->id,
            'workflow_etape_id' => $etape->id,
            'user_id' => auth()->id(),
            'action' => DocumentValidation::ACTION_REJET,
            'commentaire' => $request->commentaire,
        ]);

        $statutBrouillon = StatutDocument::where('code', 'brouillon')->first();
        $statutRejete = StatutDocument::where('code', 'rejete')->first();
        $document->update([
            'statut_document_id' => ($statutRejete ?? $statutBrouillon)?->id,
            'statut' => $statutRejete ? 'rejete' : 'brouillon',
            'workflow_etape_actuelle_id' => null,
            'workflow_validateur_id' => null,
            'workflow_validation_chain' => null,
            'workflow_etape_index' => 0,
            'modificateur_id' => auth()->id(),
        ]);
        HistoriqueDocument::enregistrer($document, 'workflow_rejet', null, 'Rejeté par '.auth()->user()->name.($request->commentaire ? ' : '.$request->commentaire : ''));
        JournalAudit::log('document.workflow_rejet', 'documents', ['document_id' => $document->id]);

        $createur = $document->createur;
        if ($createur && $createur->id !== auth()->id()) {
            $createur->notify(new DocumentValidationResultNotification($document, auth()->user(), false, $request->commentaire));
        }
        Log::channel('eged')->info('Document rejeté (workflow)', ['document_id' => $document->id, 'user_id' => auth()->id()]);

        return redirect()->back()->with('success', 'Document rejeté.');
    }

    public function archiver(Request $request, Document $document)
    {
        $this->authorize('update', $document);
        $statutArchive = StatutDocument::where('code', 'archive')->first();
        if (! $statutArchive) {
            return back()->with('error', 'Statut « Archivé » non configuré.');
        }
        $statutCode = strtolower($document->statutDocument?->code ?? $document->statut ?? '');
        if (! in_array($statutCode, ['valide', 'validé'])) {
            return back()->with('error', 'Seul un document validé peut être archivé.');
        }
        $document->update([
            'statut_document_id' => $statutArchive->id,
            'statut' => 'archive',
            'modificateur_id' => auth()->id(),
        ]);
        HistoriqueDocument::enregistrer($document, 'archivage', null, 'Document archivé');
        JournalAudit::log('document.archivage', 'documents', ['document_id' => $document->id]);
        Log::channel('eged')->info('Document archivé', ['document_id' => $document->id, 'user_id' => auth()->id()]);

        return $request->headers->get('X-Requested-With') === 'XMLHttpRequest'
            ? response()->json(['success' => true, 'message' => 'Document archivé.'])
            : redirect()->back()->with('success', 'Document archivé.');
    }

    public function destroy(Document $document)
    {
        $this->authorize('delete', $document);
        if ($document->en_corbeille) {
            $docId = $document->id;
            Storage::disk('public')->delete($document->chemin);
            foreach ($document->versions as $v) {
                if ($v->chemin !== $document->chemin) {
                    Storage::disk('public')->delete($v->chemin);
                }
            }
            $document->delete();
            Log::channel('eged')->info('Document supprimé définitivement', ['document_id' => $docId, 'user_id' => auth()->id()]);
            JournalAudit::log('document.suppression_definitive', 'documents', ['document_id' => $docId]);
            $msg = 'Document supprimé définitivement.';
        } else {
            $document->update([
                'en_corbeille' => true,
                'date_suppression' => now(),
            ]);
            HistoriqueDocument::enregistrer($document, 'corbeille', null, 'Document déplacé en corbeille');
            JournalAudit::log('document.corbeille', 'documents', ['document_id' => $document->id]);
            Log::channel('eged')->info('Document en corbeille', ['document_id' => $document->id, 'user_id' => auth()->id()]);
            $msg = 'Document déplacé en corbeille.';
        }

        return redirect()->route('documents.index')->with('success', $msg);
    }

    /**
     * Dossiers visibles (filtre liste) : arbre personnel en premier, puis plan structure, tri par chemin dans chaque groupe.
     *
     * @return array{0: \Illuminate\Support\Collection<int, Dossier>, 1: \Illuminate\Support\Collection<int, Dossier>}
     */
    private function dossiersVisiblesGroupesPersoPuisPlan(User $user): array
    {
        $idsPerso = Dossier::idsPourArbrePersonnel($user->id);
        $dossiers = Dossier::query()
            ->where('actif', true)
            ->visibleBy($user)
            ->with(['parent' => fn ($q) => $q->with('parent')])
            ->get();

        return $this->partitionnerDossiersPersoPuisPlan($dossiers, $idsPerso);
    }

    /**
     * Dossiers où l’utilisateur peut déposer : même ordre (personnel puis plan).
     *
     * @return array{0: \Illuminate\Support\Collection<int, Dossier>, 1: \Illuminate\Support\Collection<int, Dossier>}
     */
    private function dossiersDepotGroupesPersoPuisPlan(User $user): array
    {
        $idsPerso = Dossier::idsPourArbrePersonnel($user->id);
        $dossiers = Dossier::query()
            ->where('actif', true)
            ->depositableBy($user)
            ->with(['parent' => fn ($q) => $q->with('parent')])
            ->get();

        return $this->partitionnerDossiersPersoPuisPlan($dossiers, $idsPerso);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Dossier>  $dossiers
     * @param  list<int>  $idsPerso
     * @return array{0: \Illuminate\Support\Collection<int, Dossier>, 1: \Illuminate\Support\Collection<int, Dossier>}
     */
    private function partitionnerDossiersPersoPuisPlan($dossiers, array $idsPerso): array
    {
        $perso = $dossiers->filter(fn (Dossier $d) => in_array((int) $d->id, $idsPerso, true))
            ->sortBy(fn (Dossier $d) => mb_strtolower($d->chemin_complet))
            ->values();
        $plan = $dossiers->filter(fn (Dossier $d) => ! in_array((int) $d->id, $idsPerso, true))
            ->sortBy(fn (Dossier $d) => mb_strtolower($d->chemin_complet))
            ->values();

        return [$perso, $plan];
    }
}
