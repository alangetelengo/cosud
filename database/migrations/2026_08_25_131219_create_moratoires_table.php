<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moratoires', function (Blueprint $table) {
            $table->id();
            $table->string('fournisseur_libelle');
            $table->string('fournisseur_normalise', 255);
            $table->decimal('montant_dette_initial', 15, 2);
            $table->decimal('montant_echeance_defaut', 15, 2);
            $table->string('statut', 32)->default('actif');
            $table->string('lieu', 120)->nullable()->default('Brazzaville');
            $table->date('date_document')->nullable();
            $table->string('signataire_libelle')->nullable();
            $table->text('observation')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('fournisseur_normalise');
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moratoires');
    }
};
