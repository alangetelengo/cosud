<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courrier_lectures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courrier_id')->constrained('courriers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('lu_at');
            $table->timestamps();

            $table->unique(['courrier_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courrier_lectures');
    }
};
