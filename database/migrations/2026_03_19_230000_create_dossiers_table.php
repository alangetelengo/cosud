<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dossiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('nom');
            $table->string('code', 50)->nullable()->unique();
            $table->string('type', 50)->nullable(); // administratif, finance, projet, client, operation, archive, confidentiel
            $table->text('description')->nullable();
            $table->boolean('confidentiel')->default(false);
            $table->boolean('actif')->default(true);
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();

            $table->index(['parent_id', 'actif']);
            $table->index('type');
        });

        Schema::table('dossiers', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('dossiers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dossiers', fn (Blueprint $t) => $t->dropForeign(['parent_id']));
        Schema::dropIfExists('dossiers');
    }
};
