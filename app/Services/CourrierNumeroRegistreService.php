<?php

namespace App\Services;

use App\Models\Courrier;
use App\Models\SensCourrier;
use App\Models\Structure;
use Illuminate\Support\Facades\DB;

class CourrierNumeroRegistreService
{
    public function prochainNumero(int $sensCourrierId, ?int $annee = null): array
    {
        $annee = $annee ?? (int) now()->format('Y');

        return DB::transaction(function () use ($sensCourrierId, $annee) {
            $dernier = Courrier::query()
                ->where('sens_courrier_id', $sensCourrierId)
                ->where('numero_registre_annee', $annee)
                ->lockForUpdate()
                ->orderByDesc('numero_registre')
                ->value('numero_registre');

            $numero = ((int) $dernier) + 1;

            return ['numero_registre' => $numero, 'numero_registre_annee' => $annee];
        });
    }

    public function genererReferenceDepart(?Structure $structure = null): string
    {
        $structure = $structure ?? auth()->user()?->structure;
        $codeStructure = $structure?->code ?? 'DSTF';
        $annee = now()->format('Y');
        $seq = Courrier::query()
            ->whereHas('sensCourrier', fn ($q) => $q->where('code', SensCourrier::DEPART))
            ->whereYear('created_at', $annee)
            ->count() + 1;

        return sprintf('%03d/DG/%s/%s', $seq, $codeStructure, $annee);
    }
}
