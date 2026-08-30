<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Évite la suppression en cascade des courriers quand un utilisateur est effacé.
 * createur_id devient nullable + nullOnDelete (filet de sécurité).
 * etabli_par / created_by moratoire : même logique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->dropForeign(['createur_id']);
        });

        Schema::table('courriers', function (Blueprint $table) {
            $table->unsignedBigInteger('createur_id')->nullable()->change();
            $table->foreign('createur_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('suivi_paiements', function (Blueprint $table) {
            $table->dropForeign(['etabli_par_id']);
        });

        Schema::table('suivi_paiements', function (Blueprint $table) {
            $table->unsignedBigInteger('etabli_par_id')->nullable()->change();
            $table->foreign('etabli_par_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('moratoires', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });

        Schema::table('moratoires', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->change();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->dropForeign(['createur_id']);
        });

        Schema::table('courriers', function (Blueprint $table) {
            $table->unsignedBigInteger('createur_id')->nullable(false)->change();
            $table->foreign('createur_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('suivi_paiements', function (Blueprint $table) {
            $table->dropForeign(['etabli_par_id']);
        });

        Schema::table('suivi_paiements', function (Blueprint $table) {
            $table->unsignedBigInteger('etabli_par_id')->nullable(false)->change();
            $table->foreign('etabli_par_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('moratoires', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });

        Schema::table('moratoires', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable(false)->change();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
