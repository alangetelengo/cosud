<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->timestamp('circuit_etape_depuis')->nullable()->after('circuit_etape_actuelle_id');
            $table->timestamp('dernier_alerte_retard_at')->nullable()->after('circuit_etape_depuis');
        });
    }

    public function down(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->dropColumn(['circuit_etape_depuis', 'dernier_alerte_retard_at']);
        });
    }
};
