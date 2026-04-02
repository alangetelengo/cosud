<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('type_document_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_document_id')->constrained('type_documents')->cascadeOnDelete();
            $table->string('extension', 20);
            $table->timestamps();

            $table->unique(['type_document_id', 'extension']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('type_document_extensions');
    }
};
