<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->boolean('est_regularisation')->default(false)->after('montant_facture');
            $table->string('regularisation_paiement', 20)->nullable()->after('est_regularisation');
            $table->index(['est_regularisation', 'type_courrier_id']);
        });
    }

    public function down(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->dropIndex(['est_regularisation', 'type_courrier_id']);
            $table->dropColumn(['est_regularisation', 'regularisation_paiement']);
        });
    }
};
