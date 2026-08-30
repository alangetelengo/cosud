<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fournisseur_prestataires', function (Blueprint $table) {
            $table->json('scan_contrat_pieces')->nullable()->after('a_contrat');
            $table->json('scan_fiscal_pieces')->nullable()->after('a_dossier_fiscal');
        });
    }

    public function down(): void
    {
        Schema::table('fournisseur_prestataires', function (Blueprint $table) {
            $table->dropColumn(['scan_contrat_pieces', 'scan_fiscal_pieces']);
        });
    }
};
