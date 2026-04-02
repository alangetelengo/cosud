<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dossier_partages', function (Blueprint $table) {
            $table->boolean('propager_aux_sous_dossiers')
                ->default(false)
                ->after('droits_suppression');
            $table->index('propager_aux_sous_dossiers', 'dossier_partages_propager_idx');
        });
    }

    public function down(): void
    {
        Schema::table('dossier_partages', function (Blueprint $table) {
            $table->dropIndex('dossier_partages_propager_idx');
            $table->dropColumn('propager_aux_sous_dossiers');
        });
    }
};

