<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->foreignId('service_demandeur_structure_id')
                ->nullable()
                ->after('structure_destinataire_id')
                ->constrained('structures')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_demandeur_structure_id');
        });
    }
};
