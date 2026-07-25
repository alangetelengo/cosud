<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('type_courriers', function (Blueprint $table) {
            $table->foreignId('circuit_courrier_id')
                ->nullable()
                ->after('actif')
                ->constrained('circuit_courriers')
                ->nullOnDelete();
        });

        Schema::table('courriers', function (Blueprint $table) {
            $table->foreignId('circuit_courrier_id')
                ->nullable()
                ->after('type_courrier_id')
                ->constrained('circuit_courriers')
                ->nullOnDelete();
            $table->foreignId('circuit_etape_actuelle_id')
                ->nullable()
                ->after('circuit_courrier_id')
                ->constrained('circuit_courrier_etapes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('circuit_etape_actuelle_id');
            $table->dropConstrainedForeignId('circuit_courrier_id');
        });

        Schema::table('type_courriers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('circuit_courrier_id');
        });
    }
};
