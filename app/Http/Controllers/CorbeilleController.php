<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\HistoriqueDocument;
use App\Models\JournalAudit;
use Illuminate\Http\Request;

class CorbeilleController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Document::class);
        $query = Document::where('en_corbeille', true)
            ->visibleBy(auth()->user())
            ->with(['typeDocument', 'user', 'dossier'])
            ->latest('date_suppression');
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($qry) use ($q) {
                $qry->where('nom_original', 'like', "%{$q}%")
                    ->orWhere('titre', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%");
            });
        }
        $documents = $query->paginate(15)->withQueryString();
        return view('corbeille.index', compact('documents'));
    }

    public function restore(Document $document)
    {
        $this->authorize('restore', $document);
        if (! $document->en_corbeille) {
            return redirect()->route('corbeille.index')->with('error', 'Ce document n\'est pas en corbeille.');
        }
        $document->update([
            'en_corbeille' => false,
            'date_suppression' => null,
        ]);
        HistoriqueDocument::enregistrer($document, 'restauration', null, 'Document restauré depuis la corbeille');
        JournalAudit::log('document.restauration', 'corbeille', ['document_id' => $document->id]);
        return redirect()->route('corbeille.index')->with('success', 'Document restauré.');
    }
}
