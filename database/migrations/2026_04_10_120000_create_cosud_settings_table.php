<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bases déjà migrées avec ged_settings : ne pas recréer une table vide ;
        // la migration de rename (2026_08_25) s’occupe du passage ged → cosud.
        if (Schema::hasTable('cosud_settings') || Schema::hasTable('ged_settings')) {
            return;
        }

        Schema::create('cosud_settings', function (Blueprint $table) {
            $table->id();
            $table->string('cle')->unique();
            $table->text('valeur');
            $table->timestamps();
        });

        DB::table('cosud_settings')->insert([
            'cle' => 'lecture_dossier_lors_partage_document',
            'valeur' => json_encode(false),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cosud_settings');
    }
};
