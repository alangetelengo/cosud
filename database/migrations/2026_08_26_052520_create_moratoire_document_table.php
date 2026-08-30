<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moratoire_document', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moratoire_id')->constrained('moratoires')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->boolean('est_principal')->default(false);
            $table->timestamps();

            $table->unique(['moratoire_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moratoire_document');
    }
};
