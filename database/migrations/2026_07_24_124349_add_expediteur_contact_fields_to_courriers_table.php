<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->string('expediteur_email', 255)->nullable()->after('expediteur_libelle');
            $table->string('expediteur_telephone', 40)->nullable()->after('expediteur_email');
        });
    }

    public function down(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->dropColumn(['expediteur_email', 'expediteur_telephone']);
        });
    }
};
