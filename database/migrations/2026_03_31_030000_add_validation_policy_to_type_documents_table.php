<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('type_documents', function (Blueprint $table) {
            $table->boolean('validation_obligatoire')
                ->default(true)
                ->after('actif');
            $table->string('niveau_validation_final', 20)
                ->default('dg')
                ->after('validation_obligatoire');
        });

        // Valeurs métier initiales (ajustables via l'écran des types).
        DB::table('type_documents')->where('code', 'RAPPORT')->update([
            'niveau_validation_final' => 'directeur',
        ]);
        DB::table('type_documents')->where('code', 'RAPPORT_FINANCIER')->update([
            'niveau_validation_final' => 'directeur',
        ]);
        DB::table('type_documents')->where('code', 'COURRIER')->update([
            'niveau_validation_final' => 'chef_service',
        ]);
        DB::table('type_documents')->where('code', 'DECISION')->update([
            'niveau_validation_final' => 'dg',
        ]);
    }

    public function down(): void
    {
        Schema::table('type_documents', function (Blueprint $table) {
            $table->dropColumn(['validation_obligatoire', 'niveau_validation_final']);
        });
    }
};

