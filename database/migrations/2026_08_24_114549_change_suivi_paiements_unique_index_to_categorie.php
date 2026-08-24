<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = collect(Schema::getIndexes('suivi_paiements'))->pluck('name');

        Schema::table('suivi_paiements', function (Blueprint $table) use ($indexes): void {
            if ($indexes->contains('suivi_paiements_type_numero_annee_numero_ligne_unique')) {
                $table->dropUnique(['type', 'numero_annee', 'numero_ligne']);
            }

            if (! $indexes->contains('suivi_paiements_cat_annee_ligne_unique')) {
                $table->unique(
                    ['categorie_depense_id', 'numero_annee', 'numero_ligne'],
                    'suivi_paiements_cat_annee_ligne_unique'
                );
            }
        });
    }

    public function down(): void
    {
        $indexes = collect(Schema::getIndexes('suivi_paiements'))->pluck('name');

        Schema::table('suivi_paiements', function (Blueprint $table) use ($indexes): void {
            if ($indexes->contains('suivi_paiements_cat_annee_ligne_unique')) {
                $table->dropUnique('suivi_paiements_cat_annee_ligne_unique');
            }

            if (! $indexes->contains('suivi_paiements_type_numero_annee_numero_ligne_unique')) {
                $table->unique(['type', 'numero_annee', 'numero_ligne']);
            }
        });
    }
};
