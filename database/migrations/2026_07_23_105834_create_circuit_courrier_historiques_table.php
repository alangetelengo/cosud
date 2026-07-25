<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circuit_courrier_historiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courrier_id')->constrained('courriers')->cascadeOnDelete();
            $table->foreignId('circuit_courrier_etape_id')->nullable()->constrained('circuit_courrier_etapes')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('evenement', 40);
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->index(['courrier_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circuit_courrier_historiques');
    }
};
