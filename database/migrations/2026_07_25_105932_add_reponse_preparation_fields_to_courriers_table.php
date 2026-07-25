<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Champs de préparation d'une réponse (circuit « courrier_general ») : le document
     * importé par la particulière, la destination proposée (structure ou agent si
     * confidentiel) et l'objet, en attente de validation par le DG avant la création
     * effective du courrier départ.
     */
    public function up(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->foreignId('document_reponse_id')->nullable()->after('dossier_id')->constrained('documents')->nullOnDelete();
            $table->boolean('reponse_confidentielle')->default(false)->after('document_reponse_id');
            $table->foreignId('reponse_structure_destinataire_id')->nullable()->after('reponse_confidentielle')->constrained('structures')->nullOnDelete();
            $table->foreignId('destinataire_agent_id')->nullable()->after('reponse_structure_destinataire_id')->constrained('users')->nullOnDelete();
            $table->string('reponse_objet', 500)->nullable()->after('destinataire_agent_id');
        });
    }

    public function down(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->dropForeign(['document_reponse_id']);
            $table->dropForeign(['reponse_structure_destinataire_id']);
            $table->dropForeign(['destinataire_agent_id']);
            $table->dropColumn([
                'document_reponse_id', 'reponse_confidentielle', 'reponse_structure_destinataire_id',
                'destinataire_agent_id', 'reponse_objet',
            ]);
        });
    }
};
