<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nom_original');
            $table->string('chemin'); // Chemin de stockage
            $table->string('extension', 20);
            $table->unsignedBigInteger('taille_octets')->default(0);
            $table->string('titre')->nullable();
            $table->text('description')->nullable();
            $table->string('statut', 30)->default('brouillon'); // brouillon, valide, archive
            $table->timestamps();

            $table->index(['type_document_id', 'statut']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
