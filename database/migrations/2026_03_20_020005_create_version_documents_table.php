<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('version_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->string('chemin');
            $table->string('nom_fichier', 255)->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('taille_octets')->default(0);
            $table->string('empreinte', 64)->nullable();
            $table->text('commentaire')->nullable();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('est_actuel')->default(true);
            $table->timestamps();

            $table->unique(['document_id', 'numero']);
            $table->index(['document_id', 'est_actuel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('version_documents');
    }
};
