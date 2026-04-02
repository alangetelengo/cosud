<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('structures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('nom');
            $table->string('code', 50)->nullable()->unique();
            $table->string('type', 50)->nullable(); // service, departement, direction, etc.
            $table->text('adresse')->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('email')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::table('structures', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('structures')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('structures', fn (Blueprint $t) => $t->dropForeign(['parent_id']));
        Schema::dropIfExists('structures');
    }
};
