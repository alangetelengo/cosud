<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateGedAccesRequest;
use App\Models\GedSetting;
use App\Models\JournalAudit;
use App\Models\Structure;
use Illuminate\Support\Facades\Log;

class ParametresController extends Controller
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
        Log::channel('eged')->debug('Consultation paramètres', ['user_id' => auth()->id()]);
        $structures = Structure::where('actif', true)
            ->with('fonction')
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('nom')
            ->get();

        return view('parametres.index', compact('structures'));
    }

    public function gedAcces()
    {
        Log::channel('eged')->debug('Consultation paramètres accès GED', ['user_id' => auth()->id()]);
        $lectureDossierLorsPartageDocument = GedSetting::lectureDossierLorsPartageDocument();

        return view('parametres.ged-acces', compact('lectureDossierLorsPartageDocument'));
    }

    public function updateGedAcces(UpdateGedAccesRequest $request)
    {
        $avant = GedSetting::lectureDossierLorsPartageDocument();
        $apres = $request->boolean('lecture_dossier_lors_partage_document');
        GedSetting::setBool(GedSetting::LECTURE_DOSSIER_LORS_PARTAGE_DOCUMENT, $apres);

        JournalAudit::log('parametres.ged-acces.update', 'parametres', [
            'donnees_avant' => json_encode(['lecture_dossier_lors_partage_document' => $avant]),
            'donnees_apres' => json_encode(['lecture_dossier_lors_partage_document' => $apres]),
        ]);
        Log::channel('eged')->info('Paramètres accès GED mis à jour', [
            'user_id' => auth()->id(),
            'lecture_dossier_lors_partage_document' => $apres,
        ]);

        return redirect()
            ->route('parametres.ged-acces')
            ->with('success', 'Les paramètres d’accès ont été enregistrés.');
    }
}
