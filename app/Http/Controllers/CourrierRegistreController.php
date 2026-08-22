<?php

namespace App\Http\Controllers;

use App\Models\Courrier;
use App\Models\SensCourrier;
use Illuminate\Database\Eloquent\Builder;
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
        $mois = $request->filled('mois') ? (int) $request->get('mois') : null;
        $trimestre = $request->filled('trimestre') ? (int) $request->get('trimestre') : null;

        if ($mois !== null && ($mois < 1 || $mois > 12)) {
            $mois = null;
        }
        if ($trimestre !== null && ($trimestre < 1 || $trimestre > 4)) {
            $trimestre = null;
        }

        $query = Courrier::query()
            ->visibleBy(auth()->user())
            ->where('sens_courrier_id', $sens->id)
            ->where('numero_registre_annee', $annee)
            ->with(['statutCourrier', 'typeCourrier', 'reponsesDepart'])
            ->orderBy('numero_registre');

        $this->appliquerFiltrePeriode($query, $sensCode, $mois, $trimestre);

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

        $structureRegistre = auth()->user()->structure;
        $libelleStructureRegistre = $structureRegistre?->nom
            ?: (auth()->user()->aAccesTotal() ? 'Direction Générale — ACSI' : 'Secrétariat — ACSI');

        if ($print) {
            $courriers = $query->get();

            return view('courriers.registres.print', [
                'courriers' => $courriers,
                'sens' => $sens,
                'sensCode' => $sensCode,
                'annee' => $annee,
                'mois' => $mois,
                'trimestre' => $trimestre,
                'libelleStructureRegistre' => $libelleStructureRegistre,
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
            'mois' => $mois,
            'trimestre' => $trimestre,
            'annees' => $annees,
            'libelleStructureRegistre' => $libelleStructureRegistre,
        ]);
    }

    /**
     * Filtre par mois et/ou trimestre sur la date métier du registre.
     * Arrivée : date_reception.
     * Départ : date_expedition, sinon date_courrier.
     * Si mois et trimestre sont fournis, le mois prime (plus précis).
     */
    protected function appliquerFiltrePeriode(Builder $query, string $sensCode, ?int $mois, ?int $trimestre): void
    {
        if ($mois === null && $trimestre === null) {
            return;
        }

        $moisCibles = [];
        if ($mois !== null) {
            $moisCibles = [$mois];
        } else {
            $debut = (($trimestre - 1) * 3) + 1;
            $moisCibles = range($debut, $debut + 2);
        }

        $query->where(function (Builder $outer) use ($sensCode, $moisCibles) {
            foreach ($moisCibles as $m) {
                $outer->orWhere(function (Builder $inner) use ($sensCode, $m) {
                    if ($sensCode === SensCourrier::DEPART) {
                        $inner->where(function (Builder $q) use ($m) {
                            $q->whereNotNull('date_expedition')
                                ->whereMonth('date_expedition', $m);
                        })->orWhere(function (Builder $q) use ($m) {
                            $q->whereNull('date_expedition')
                                ->whereNotNull('date_courrier')
                                ->whereMonth('date_courrier', $m);
                        });
                    } else {
                        $inner->whereMonth('date_reception', $m);
                    }
                });
            }
        });
    }
}
