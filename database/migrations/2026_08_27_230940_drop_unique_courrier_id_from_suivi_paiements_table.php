<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Autorise plusieurs lignes de suivi pour un même courrier
 * (ex. échéances de moratoire rattachées au même dossier).
 *
 * Sur MySQL, l’index unique sur courrier_id sert aussi à la FK :
 * il faut donc drop FK → drop unique → index non unique → recreate FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suivi_paiements') || ! Schema::hasColumn('suivi_paiements', 'courrier_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        $indexes = collect(Schema::getIndexes('suivi_paiements'));
        $uniqueCourrier = $indexes->first(function (array $index): bool {
            return ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['courrier_id'];
        });

        if (! $uniqueCourrier) {
            return;
        }

        if ($driver === 'sqlite') {
            Schema::table('suivi_paiements', function (Blueprint $table) use ($uniqueCourrier): void {
                $table->dropUnique($uniqueCourrier['name']);
            });

            return;
        }

        Schema::table('suivi_paiements', function (Blueprint $table): void {
            $table->dropForeign(['courrier_id']);
        });

        Schema::table('suivi_paiements', function (Blueprint $table) use ($uniqueCourrier): void {
            $table->dropUnique($uniqueCourrier['name']);
        });

        $indexesApres = collect(Schema::getIndexes('suivi_paiements'));
        $hasCourrierIndex = $indexesApres->contains(function (array $index): bool {
            return ($index['columns'] ?? []) === ['courrier_id'];
        });

        Schema::table('suivi_paiements', function (Blueprint $table) use ($hasCourrierIndex): void {
            if (! $hasCourrierIndex) {
                $table->index('courrier_id');
            }

            $table->foreign('courrier_id')
                ->references('id')
                ->on('courriers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('suivi_paiements') || ! Schema::hasColumn('suivi_paiements', 'courrier_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        $indexes = collect(Schema::getIndexes('suivi_paiements'))->pluck('name');

        if ($driver === 'sqlite') {
            Schema::table('suivi_paiements', function (Blueprint $table) use ($indexes): void {
                if ($indexes->contains('suivi_paiements_courrier_id_index')) {
                    $table->dropIndex('suivi_paiements_courrier_id_index');
                }

                $table->unique('courrier_id');
            });

            return;
        }

        Schema::table('suivi_paiements', function (Blueprint $table): void {
            $table->dropForeign(['courrier_id']);
        });

        Schema::table('suivi_paiements', function (Blueprint $table) use ($indexes): void {
            if ($indexes->contains('suivi_paiements_courrier_id_index')) {
                $table->dropIndex('suivi_paiements_courrier_id_index');
            }

            $table->unique('courrier_id');
            $table->foreign('courrier_id')
                ->references('id')
                ->on('courriers')
                ->nullOnDelete();
        });
    }
};
