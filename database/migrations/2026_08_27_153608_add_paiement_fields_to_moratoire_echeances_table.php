<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moratoire_echeances', function (Blueprint $table) {
            $table->string('mode_paiement', 20)->nullable()->after('solde');
            $table->string('periode_mois', 20)->nullable()->after('date_paiement');
            $table->unsignedSmallInteger('periode_annee')->nullable()->after('periode_mois');
        });
    }

    public function down(): void
    {
        Schema::table('moratoire_echeances', function (Blueprint $table) {
            $table->dropColumn(['mode_paiement', 'periode_mois', 'periode_annee']);
        });
    }
};
