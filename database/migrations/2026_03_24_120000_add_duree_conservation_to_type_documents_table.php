<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Durée de conservation légale / archivistique (années après clôture ou dernier acte),
     * alignée sur les usages DUA en gestion documentaire (0 = conservation permanente).
     */
    public function up(): void
    {
        Schema::table('type_documents', function (Blueprint $table) {
            $table->unsignedSmallInteger('duree_conservation_annees')->nullable()->after('taille_max_ko');
        });
    }

    public function down(): void
    {
        Schema::table('type_documents', function (Blueprint $table) {
            $table->dropColumn('duree_conservation_annees');
        });
    }
};
