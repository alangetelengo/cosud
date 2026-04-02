<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fonctions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle');
            $table->string('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::table('structures', function (Blueprint $table) {
            $table->foreignId('fonction_id')->nullable()->after('responsable_id')->constrained('fonctions')->nullOnDelete();
            $table->string('role_technique', 100)->nullable()->after('fonction_id');
        });

        Schema::table('user_structure', function (Blueprint $table) {
            $table->foreignId('fonction_id')->nullable()->after('structure_id')->constrained('fonctions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_structure', function (Blueprint $table) {
            $table->dropForeign(['fonction_id']);
            $table->dropColumn('fonction_id');
        });

        Schema::table('structures', function (Blueprint $table) {
            $table->dropForeign(['fonction_id']);
            $table->dropColumn(['fonction_id', 'role_technique']);
        });

        Schema::dropIfExists('fonctions');
    }
};
