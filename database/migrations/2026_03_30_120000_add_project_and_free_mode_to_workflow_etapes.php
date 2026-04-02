<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_etapes', function (Blueprint $table) {
            $table->foreignId('projet_dossier_id')
                ->nullable()
                ->after('type_document_id')
                ->constrained('dossiers')
                ->nullOnDelete();
            $table->boolean('destinataire_libre')
                ->default(false)
                ->after('validation_hierarchique');
            $table->index(['projet_dossier_id', 'ordre'], 'workflow_etapes_projet_ordre_idx');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_etapes', function (Blueprint $table) {
            $table->dropIndex('workflow_etapes_projet_ordre_idx');
            $table->dropForeign(['projet_dossier_id']);
            $table->dropColumn(['projet_dossier_id', 'destinataire_libre']);
        });
    }
};

