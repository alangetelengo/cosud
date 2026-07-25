<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circuit_courrier_etapes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circuit_courrier_id')->constrained('circuit_courriers')->cascadeOnDelete();
            $table->unsignedSmallInteger('ordre')->default(1);
            $table->string('code', 80);
            $table->string('nom', 200);
            $table->string('acteur_type', 30)->default('role');
            $table->string('acteur_valeur', 100)->nullable();
            $table->string('action', 40)->default('traiter');
            $table->string('mouvement', 30)->default('aucun');
            $table->json('notifie_roles')->nullable();
            $table->text('instructions_aide')->nullable();
            $table->boolean('est_finale')->default(false);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['circuit_courrier_id', 'code'], 'circuit_etape_code_unique');
            $table->index(['circuit_courrier_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circuit_courrier_etapes');
    }
};
