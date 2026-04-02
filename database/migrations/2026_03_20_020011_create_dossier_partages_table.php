<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dossier_partages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partage_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('droits_lecture')->default(true);
            $table->boolean('droits_ecriture')->default(false);
            $table->boolean('droits_suppression')->default(false);
            $table->timestamp('date_expiration')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->unique(['dossier_id', 'user_id']);
            $table->index(['user_id', 'date_expiration']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dossier_partages');
    }
};
