<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_etape_id')->nullable()->constrained('workflow_etapes')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 20); // approbation, rejet
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'created_at']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('workflow_etape_actuelle_id')->nullable()->after('statut_document_id')->constrained('workflow_etapes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['workflow_etape_actuelle_id']);
        });
        Schema::dropIfExists('document_validations');
    }
};
