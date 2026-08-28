<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->decimal('regularisation_montant_mensuel', 15, 2)->nullable()->after('regularisation_banque');
            $table->unsignedSmallInteger('regularisation_nb_mois_impayes')->nullable()->after('regularisation_montant_mensuel');
        });
    }

    public function down(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->dropColumn([
                'regularisation_montant_mensuel',
                'regularisation_nb_mois_impayes',
            ]);
        });
    }
};
