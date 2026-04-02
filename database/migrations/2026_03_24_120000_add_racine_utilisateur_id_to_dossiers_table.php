<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->foreignId('racine_utilisateur_id')
                ->nullable()
                ->after('structure_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('dossiers', function (Blueprint $table) {
            $table->unique('racine_utilisateur_id');
        });
    }

    public function down(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->dropUnique(['racine_utilisateur_id']);
            $table->dropForeign(['racine_utilisateur_id']);
            $table->dropColumn('racine_utilisateur_id');
        });
    }
};
