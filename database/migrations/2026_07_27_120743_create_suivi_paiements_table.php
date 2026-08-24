<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suivi_paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courrier_id')->nullable()->unique()->constrained('courriers')->nullOnDelete();
            $table->string('type', 32);
            $table->string('origine', 32)->default('circuit_cheque');
            $table->unsignedInteger('numero_ligne');
            $table->unsignedSmallInteger('numero_annee');
            $table->date('date_suivi');
            $table->string('intitule');
            $table->decimal('montant', 15, 2);
            $table->string('fournisseur_libelle')->nullable();
            $table->string('service_demandeur_libelle')->nullable();
            $table->string('demandeur_libelle')->nullable();
            $table->foreignId('responsable_dossier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('instruction_dg')->nullable();
            $table->text('observation')->nullable();
            $table->foreignId('etabli_par_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['type', 'numero_annee', 'numero_ligne']);
            $table->index(['type', 'date_suivi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suivi_paiements');
    }
};
