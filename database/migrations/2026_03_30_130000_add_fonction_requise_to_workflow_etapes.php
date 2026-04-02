<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_etapes', function (Blueprint $table) {
            $table->foreignId('fonction_requise_id')
                ->nullable()
                ->after('role_requis')
                ->constrained('fonctions')
                ->nullOnDelete();
            $table->index('fonction_requise_id', 'workflow_etapes_fonction_idx');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_etapes', function (Blueprint $table) {
            $table->dropIndex('workflow_etapes_fonction_idx');
            $table->dropForeign(['fonction_requise_id']);
            $table->dropColumn('fonction_requise_id');
        });
    }
};

