<?php

namespace App\Http\Controllers;

use App\Models\Courrier;
use App\Models\SensCourrier;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourrierRegistreController extends Controller
{
    public function arrivee(Request $request): View
    {
        return $this->registre($request, SensCourrier::ARRIVEE, false);
    }

    public function depart(Request $request): View
    {
        return $this->registre($request, SensCourrier::DEPART, false);
    }

    public function printArrivee(Request $request): View
    {
        return $this->registre($request, SensCourrier::ARRIVEE, true);
    }

    public function printDepart(Request $request): View
    {
        return $this->registre($request, SensCourrier::DEPART, true);
    }

    protected function registre(Request $request, string $sensCode, bool $print): View
    {
        $this->authorize('viewAny', Courrier::class);

        $sens = SensCourrier::where('code', $sensCode)->firstOrFail();
        $annee = (int) $request->get('annee', now()->year);

        $query = Courrier::query()
            ->visibleBy(auth()->user())
            ->where('sens_courrier_id', $sens->id)
            ->where('numero_registre_annee', $annee)
            ->with(['statutCourrier', 'typeCourrier', 'reponsesDepart'])
            ->orderBy('numero_registre');

        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function ($sub) use ($q) {
                $sub->where('objet', 'like', "%{$q}%")
                    ->orWhere('expediteur_libelle', 'like', "%{$q}%")
                    ->orWhere('destinataire_libelle', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%")
                    ->orWhere('numero_archives', 'like', "%{$q}%")
                    ->orWhere('observations', 'like', "%{$q}%");
            });
        }

        $annees = Courrier::query()
            ->visibleBy(auth()->user())
            ->where('sens_courrier_id', $sens->id)
            ->select('numero_registre_annee')
            ->distinct()
            ->orderByDesc('numero_registre_annee')
            ->pluck('numero_registre_annee');

        if ($annees->isEmpty()) {
            $annees = collect([$annee]);
        }

        if ($print) {
            $courriers = $query->get();

            return view('courriers.registres.print', [
                'courriers' => $courriers,
                'sens' => $sens,
                'sensCode' => $sensCode,
                'annee' => $annee,
            ]);
        }

        $courriers = $query->get();
        $lignesParFeuillet = max(1, (int) config('ged.registre_lignes_par_feuillet', 10));
        $feuillets = $courriers->chunk($lignesParFeuillet)->values();

        return view('courriers.registres.show', [
            'courriers' => $courriers,
            'feuillets' => $feuillets,
            'lignesParFeuillet' => $lignesParFeuillet,
            'sens' => $sens,
            'sensCode' => $sensCode,
            'annee' => $annee,
            'annees' => $annees,
        ]);
    }
}
