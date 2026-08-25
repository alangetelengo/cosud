<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCosudAccesRequest;
use App\Models\CosudSetting;
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
        Log::channel('cosud')->debug('Consultation paramètres', ['user_id' => auth()->id()]);
        $structures = Structure::where('actif', true)
            ->with('fonction')
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('nom')
            ->get();

        return view('parametres.index', compact('structures'));
    }

    public function cosudAcces()
    {
        Log::channel('cosud')->debug('Consultation paramètres accès COSUD', ['user_id' => auth()->id()]);
        $lectureDossierLorsPartageDocument = CosudSetting::lectureDossierLorsPartageDocument();

        return view('parametres.cosud-acces', compact('lectureDossierLorsPartageDocument'));
    }

    public function updateCosudAcces(UpdateCosudAccesRequest $request)
    {
        $avant = CosudSetting::lectureDossierLorsPartageDocument();
        $apres = $request->boolean('lecture_dossier_lors_partage_document');
        CosudSetting::setBool(CosudSetting::LECTURE_DOSSIER_LORS_PARTAGE_DOCUMENT, $apres);

        JournalAudit::log('parametres.cosud-acces.update', 'parametres', [
            'donnees_avant' => json_encode(['lecture_dossier_lors_partage_document' => $avant]),
            'donnees_apres' => json_encode(['lecture_dossier_lors_partage_document' => $apres]),
        ]);
        Log::channel('cosud')->info('Paramètres accès COSUD mis à jour', [
            'user_id' => auth()->id(),
            'lecture_dossier_lors_partage_document' => $apres,
        ]);

        return redirect()
            ->route('parametres.cosud-acces')
            ->with('success', 'Les paramètres d’accès ont été enregistrés.');
    }
}
