<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_etapes', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('code', 50)->unique();
            $table->unsignedTinyInteger('ordre')->default(1);
            $table->foreignId('type_document_id')->nullable()->constrained('type_documents')->nullOnDelete();
            $table->string('role_requis', 50)->nullable(); // Spatie role (admin, responsable, etc.)
            $table->foreignId('validateur_id')->nullable()->constrained('users')->nullOnDelete(); // Utilisateur spécifique
            $table->unsignedBigInteger('workflow_etape_suivante_id')->nullable();
            $table->boolean('est_derniere_etape')->default(false); // Si true, valider = document validé
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::table('workflow_etapes', function (Blueprint $table) {
            $table->foreign('workflow_etape_suivante_id')->references('id')->on('workflow_etapes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_etapes', fn (Blueprint $t) => $t->dropForeign(['workflow_etape_suivante_id']));
        Schema::dropIfExists('workflow_etapes');
    }
};
