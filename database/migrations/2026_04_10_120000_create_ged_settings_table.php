<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ged_settings', function (Blueprint $table) {
            $table->id();
            $table->string('cle')->unique();
            $table->text('valeur');
            $table->timestamps();
        });

        DB::table('ged_settings')->insert([
            'cle' => 'lecture_dossier_lors_partage_document',
            'valeur' => json_encode(false),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ged_settings');
    }
};
