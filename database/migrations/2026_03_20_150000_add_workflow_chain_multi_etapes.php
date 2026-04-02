<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->json('workflow_validation_chain')->nullable()->after('workflow_validateur_id');
            $table->unsignedTinyInteger('workflow_etape_index')->default(0)->after('workflow_validation_chain');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['workflow_validation_chain', 'workflow_etape_index']);
        });
    }
};
