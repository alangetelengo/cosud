<?php

namespace App\Http\Controllers;

use App\Models\JournalAudit;
use App\Models\TypeDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TypeDocumentController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', TypeDocument::class);
        $types = TypeDocument::orderBy('libelle')->paginate(15);

        return view('types-documents.index', compact('types'));
    }

    public function create()
    {
        $this->authorize('create', TypeDocument::class);

        return view('types-documents.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', TypeDocument::class);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:type_documents,code'],
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'extension_defaut' => ['nullable', 'string', 'max:20'],
            'duree_conservation_annees' => ['nullable', 'integer', 'min:0', 'max:500'],
            'taille_max_ko' => ['nullable', 'integer', 'min:1', 'max:102400'],
            'actif' => ['boolean'],
            'validation_obligatoire' => ['boolean'],
            'niveau_validation_final' => ['nullable', Rule::in(['chef_service', 'directeur', 'dg'])],
        ]);
        $validated['actif'] = $request->boolean('actif', true);
        $validated['validation_obligatoire'] = $request->boolean('validation_obligatoire', true);
        $validated['niveau_validation_final'] = $request->input('niveau_validation_final', 'dg') ?: 'dg';
        $type = TypeDocument::create($validated);
        JournalAudit::log('type_document.creation', 'types_documents', ['commentaire' => $type->code]);
        Log::channel('cosud')->info('Type de document créé', ['type_id' => $type->id, 'code' => $type->code, 'user_id' => auth()->id()]);

        return redirect()->route('types-documents.index')->with('success', 'Type de document créé.');
    }

    public function edit(TypeDocument $types_document)
    {
        $this->authorize('update', $types_document);

        return view('types-documents.edit', ['type' => $types_document]);
    }

    public function update(Request $request, TypeDocument $types_document)
    {
        $this->authorize('update', $types_document);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('type_documents', 'code')->ignore($types_document->id)],
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'extension_defaut' => ['nullable', 'string', 'max:20'],
            'duree_conservation_annees' => ['nullable', 'integer', 'min:0', 'max:500'],
            'taille_max_ko' => ['nullable', 'integer', 'min:1', 'max:102400'],
            'actif' => ['boolean'],
            'validation_obligatoire' => ['boolean'],
            'niveau_validation_final' => ['nullable', Rule::in(['chef_service', 'directeur', 'dg'])],
        ]);
        $validated['actif'] = $request->boolean('actif', true);
        $validated['validation_obligatoire'] = $request->boolean('validation_obligatoire', true);
        $validated['niveau_validation_final'] = $request->input('niveau_validation_final', 'dg') ?: 'dg';
        $types_document->update($validated);
        JournalAudit::log('type_document.modification', 'types_documents', ['commentaire' => $types_document->code]);
        Log::channel('cosud')->info('Type de document mis à jour', ['type_id' => $types_document->id, 'user_id' => auth()->id()]);

        return redirect()->route('types-documents.index')->with('success', 'Type de document mis à jour.');
    }

    public function destroy(TypeDocument $types_document)
    {
        $this->authorize('delete', $types_document);
        if ($types_document->documents()->exists()) {
            Log::channel('cosud')->warning('Suppression type refusée : documents associés', ['type_id' => $types_document->id]);

            return back()->with('error', 'Impossible de supprimer : des documents utilisent ce type.');
        }
        JournalAudit::log('type_document.suppression', 'types_documents', ['commentaire' => $types_document->code]);
        Log::channel('cosud')->info('Type de document supprimé', ['type_id' => $types_document->id, 'code' => $types_document->code, 'user_id' => auth()->id()]);
        $types_document->delete();

        return redirect()->route('types-documents.index')->with('success', 'Type de document supprimé.');
    }
}
