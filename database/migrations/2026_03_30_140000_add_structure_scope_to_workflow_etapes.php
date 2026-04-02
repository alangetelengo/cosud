<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_etapes', function (Blueprint $table) {
            $table->foreignId('structure_scope_id')
                ->nullable()
                ->after('projet_dossier_id')
                ->constrained('structures')
                ->nullOnDelete();
            $table->index(['structure_scope_id', 'ordre'], 'workflow_etapes_structure_ordre_idx');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_etapes', function (Blueprint $table) {
            $table->dropIndex('workflow_etapes_structure_ordre_idx');
            $table->dropForeign(['structure_scope_id']);
            $table->dropColumn('structure_scope_id');
        });
    }
};

