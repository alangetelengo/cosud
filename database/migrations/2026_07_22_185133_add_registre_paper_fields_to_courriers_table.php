<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->unsignedSmallInteger('nombre_pieces')->nullable()->after('objet');
            $table->string('numero_archives', 100)->nullable()->after('nombre_pieces');
            $table->text('observations')->nullable()->after('numero_archives');
        });
    }

    public function down(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->dropColumn(['nombre_pieces', 'numero_archives', 'observations']);
        });
    }
};
