<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ged_settings')
            ->where('cle', 'lecture_dossier_lors_partage_document')
            ->update([
                'valeur' => json_encode(true),
                'updated_at' => now(),
            ]);

        Cache::forget('ged_setting_bool:lecture_dossier_lors_partage_document');
    }

    public function down(): void
    {
        DB::table('ged_settings')
            ->where('cle', 'lecture_dossier_lors_partage_document')
            ->update([
                'valeur' => json_encode(false),
                'updated_at' => now(),
            ]);

        Cache::forget('ged_setting_bool:lecture_dossier_lors_partage_document');
    }
};
