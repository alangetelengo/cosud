<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_etapes', function (Blueprint $table) {
            $table->boolean('validation_hierarchique')->default(false)->after('est_derniere_etape');
        });

        Schema::table('structures', function (Blueprint $table) {
            $table->foreignId('responsable_id')->nullable()->after('actif')->constrained('users')->nullOnDelete();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('workflow_validateur_id')->nullable()->after('workflow_etape_actuelle_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_etapes', function (Blueprint $table) {
            $table->dropColumn('validation_hierarchique');
        });
        Schema::table('structures', function (Blueprint $table) {
            $table->dropForeign(['responsable_id']);
        });
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['workflow_validateur_id']);
        });
    }
};
