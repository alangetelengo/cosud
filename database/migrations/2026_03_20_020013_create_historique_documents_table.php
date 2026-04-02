<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historique_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('version_document_id')->nullable()->constrained('version_documents')->nullOnDelete();
            $table->string('operation', 50); // depot, modification, validation, archivage, suppression, etc.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('commentaire')->nullable();
            $table->json('details')->nullable();
            $table->string('adresse_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['document_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historique_documents');
    }
};
