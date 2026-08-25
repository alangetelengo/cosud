<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = $this->settingsTable();
        if ($table === null) {
            return;
        }

        DB::table($table)
            ->where('cle', 'lecture_dossier_lors_partage_document')
            ->update([
                'valeur' => json_encode(false),
                'updated_at' => now(),
            ]);

        Cache::forget('cosud_setting_bool:lecture_dossier_lors_partage_document');
    }

    public function down(): void
    {
        $table = $this->settingsTable();
        if ($table === null) {
            return;
        }

        DB::table($table)
            ->where('cle', 'lecture_dossier_lors_partage_document')
            ->update([
                'valeur' => json_encode(true),
                'updated_at' => now(),
            ]);

        Cache::forget('cosud_setting_bool:lecture_dossier_lors_partage_document');
    }

    private function settingsTable(): ?string
    {
        if (Schema::hasTable('cosud_settings')) {
            return 'cosud_settings';
        }

        if (Schema::hasTable('ged_settings')) {
            return 'ged_settings';
        }

        return null;
    }
};
