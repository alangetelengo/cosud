<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metadonnees_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('type_metadonnee_id')->constrained('type_metadonnees')->cascadeOnDelete();
            $table->string('cle', 100);
            $table->string('valeur', 500)->nullable();
            $table->decimal('valeur_numerique', 15, 4)->nullable();
            $table->timestamp('valeur_date')->nullable();
            $table->boolean('valeur_booleen')->nullable();
            $table->unsignedInteger('ordre_affichage')->default(0);
            $table->timestamps();

            $table->unique(['document_id', 'cle']);
            $table->index('document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metadonnees_documents');
    }
};
