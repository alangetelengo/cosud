<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dossier_corbeilles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supprime_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('date_suppression')->useCurrent();
            $table->string('raison_suppression', 500)->nullable();
            $table->timestamp('date_expiration')->nullable();
            $table->timestamps();

            $table->index(['date_suppression', 'date_expiration']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dossier_corbeilles');
    }
};
