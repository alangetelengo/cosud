<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ged_settings') && ! Schema::hasTable('cosud_settings')) {
            Schema::rename('ged_settings', 'cosud_settings');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cosud_settings') && ! Schema::hasTable('ged_settings')) {
            Schema::rename('cosud_settings', 'ged_settings');
        }
    }
};
