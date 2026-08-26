<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moratoire_echeances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moratoire_id')->constrained('moratoires')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->decimal('montant_dette', 15, 2);
            $table->decimal('montant_echeance', 15, 2);
            $table->decimal('solde', 15, 2);
            $table->string('numero_cheque', 150)->nullable();
            $table->string('banque', 100)->nullable();
            $table->text('observation')->nullable();
            $table->date('date_paiement')->nullable();
            $table->foreignId('suivi_paiement_id')->nullable()->constrained('suivi_paiements')->nullOnDelete();
            $table->timestamps();

            $table->unique(['moratoire_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moratoire_echeances');
    }
};
