<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('structure_id')->nullable()->after('telephone')->constrained()->nullOnDelete();
        });

        Schema::create('user_structure', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->string('role', 50)->nullable();
            $table->timestamp('date_affectation')->useCurrent();
            $table->timestamp('date_fin')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'structure_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_structure');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['structure_id']);
        });
    }
};
