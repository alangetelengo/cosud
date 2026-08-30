<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bases déjà migrées (MySQL) : courrier nullable + colonne origine.
 * Installs fraîches / SQLite : déjà couvert par la migration de création.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suivi_paiements')) {
            return;
        }

        if (! Schema::hasColumn('suivi_paiements', 'origine')) {
            Schema::table('suivi_paiements', function (Blueprint $table) {
                $table->string('origine', 32)->default('circuit_cheque')->after('type');
            });
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('suivi_paiements', function (Blueprint $table) {
            $table->dropForeign(['courrier_id']);
        });

        Schema::table('suivi_paiements', function (Blueprint $table) {
            $table->dropUnique(['courrier_id']);
        });

        DB::statement('ALTER TABLE suivi_paiements MODIFY courrier_id BIGINT UNSIGNED NULL');

        Schema::table('suivi_paiements', function (Blueprint $table) {
            $table->foreign('courrier_id')->references('id')->on('courriers')->nullOnDelete();
            $table->unique('courrier_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('suivi_paiements')) {
            return;
        }

        DB::table('suivi_paiements')->whereNull('courrier_id')->delete();

        if (Schema::hasColumn('suivi_paiements', 'origine')) {
            Schema::table('suivi_paiements', function (Blueprint $table) {
                $table->dropColumn('origine');
            });
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('suivi_paiements', function (Blueprint $table) {
            $table->dropForeign(['courrier_id']);
            $table->dropUnique(['courrier_id']);
        });

        DB::statement('ALTER TABLE suivi_paiements MODIFY courrier_id BIGINT UNSIGNED NOT NULL');

        Schema::table('suivi_paiements', function (Blueprint $table) {
            $table->foreign('courrier_id')->references('id')->on('courriers')->cascadeOnDelete();
            $table->unique('courrier_id');
        });
    }
};
