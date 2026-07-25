<?php

use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $depart = SensCourrier::where('code', 'depart')->first();
        if (! $depart) {
            return;
        }

        StatutCourrier::updateOrCreate(
            ['sens_courrier_id' => $depart->id, 'code' => 'annule'],
            [
                'libelle' => 'Annulé',
                'ordre' => 8,
                'est_initial' => false,
                'est_final' => true,
                'actif' => true,
            ]
        );
    }

    public function down(): void
    {
        $depart = SensCourrier::where('code', 'depart')->first();
        if (! $depart) {
            return;
        }

        StatutCourrier::query()
            ->where('sens_courrier_id', $depart->id)
            ->where('code', 'annule')
            ->delete();
    }
};
