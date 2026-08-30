<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->string('regularisation_mode_paiement', 20)->nullable()->after('regularisation_paiement');
            $table->date('regularisation_date_programmation')->nullable()->after('regularisation_mode_paiement');
            $table->string('regularisation_numero_piece', 150)->nullable()->after('regularisation_date_programmation');
            $table->string('regularisation_banque', 100)->nullable()->after('regularisation_numero_piece');
        });
    }

    public function down(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->dropColumn([
                'regularisation_mode_paiement',
                'regularisation_date_programmation',
                'regularisation_numero_piece',
                'regularisation_banque',
            ]);
        });
    }
};
