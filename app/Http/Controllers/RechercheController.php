<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Dossier;
use App\Models\TypeDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RechercheController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->get('q', '');
        $documents = collect();
        $dossiers = collect();

        if (strlen($q) >= 2) {
            Log::channel('eged')->debug('Recherche', ['q' => $q, 'user_id' => auth()->id()]);
            $documents = Document::horsCorbeille()
                ->visibleBy(auth()->user())
                ->with(['typeDocument', 'user', 'dossier' => fn ($d) => $d->with(['parent' => fn ($p) => $p->with('parent')])])
                ->where(function ($query) use ($q) {
                    $query->where('nom_original', 'like', "%{$q}%")
                        ->orWhere('titre', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%");
                })
                ->latest()
                ->paginate(15)
                ->withQueryString();

            $dossiers = Dossier::where('actif', true)
                ->visibleBy(auth()->user())
                ->where('nom', 'like', "%{$q}%")
                ->with(['parent' => fn ($p) => $p->with('parent')])
                ->orderBy('nom')
                ->limit(10)
                ->get();
        }

        return view('recherche.index', compact('q', 'documents', 'dossiers'));
    }
}
