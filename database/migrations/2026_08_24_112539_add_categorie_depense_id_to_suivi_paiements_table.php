<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suivi_paiements', function (Blueprint $table) {
            $table->foreignId('categorie_depense_id')
                ->nullable()
                ->after('type')
                ->constrained('categorie_depenses')
                ->nullOnDelete();
        });

        $map = [
            'fsp_facture' => 'facture',
            'fsp_mad' => 'paiement_divers',
            'fsp_paie' => 'paie',
            'fsp_commission' => 'commission',
            'fsp_ttf' => 'ttf',
        ];

        foreach ($map as $type => $code) {
            $id = DB::table('categorie_depenses')->where('code', $code)->value('id');
            if ($id) {
                DB::table('suivi_paiements')->where('type', $type)->update(['categorie_depense_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('suivi_paiements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('categorie_depense_id');
        });
    }
};
