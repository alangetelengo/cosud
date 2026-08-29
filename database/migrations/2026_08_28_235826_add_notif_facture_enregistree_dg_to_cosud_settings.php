<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cosud_settings')) {
            return;
        }

        $cle = 'notif_facture_enregistree_dg';
        $exists = DB::table('cosud_settings')->where('cle', $cle)->exists();
        if ($exists) {
            return;
        }

        DB::table('cosud_settings')->insert([
            'cle' => $cle,
            'valeur' => json_encode(true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forget('cosud_setting_bool:'.$cle);
    }

    public function down(): void
    {
        if (! Schema::hasTable('cosud_settings')) {
            return;
        }

        $cle = 'notif_facture_enregistree_dg';
        DB::table('cosud_settings')->where('cle', $cle)->delete();
        Cache::forget('cosud_setting_bool:'.$cle);
    }
};
