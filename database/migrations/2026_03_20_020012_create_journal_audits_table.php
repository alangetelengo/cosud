<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_audits', function (Blueprint $table) {
            $table->id();
            $table->string('action', 100);
            $table->string('module', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dossier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('adresse_ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->text('donnees_avant')->nullable();
            $table->text('donnees_apres')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['document_id', 'created_at']);
            $table->index(['dossier_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_audits');
    }
};
