<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table
                ->foreignId('workflow_destinataire_id')
                ->nullable()
                ->after('workflow_validateur_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['workflow_destinataire_id']);
            $table->dropColumn('workflow_destinataire_id');
        });
    }
};
