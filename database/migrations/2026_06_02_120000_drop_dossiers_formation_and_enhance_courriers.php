<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('dossiers_formation');

        Schema::table('courriers', function (Blueprint $table) {
            $table->string('origine', 20)->default('externe')->after('sens_courrier_id');
            $table->foreignId('courrier_parent_id')->nullable()->after('dossier_id')->constrained('courriers')->nullOnDelete();
            $table->foreignId('courrier_depart_source_id')->nullable()->after('courrier_parent_id')->constrained('courriers')->nullOnDelete();
            $table->foreignId('courrier_arrivee_lie_id')->nullable()->after('courrier_depart_source_id')->constrained('courriers')->nullOnDelete();
            $table->foreignId('directeur_en_attente_id')->nullable()->after('signataire_id')->constrained('users')->nullOnDelete();
            $table->text('motif_rejet')->nullable()->after('instructions_dg');
            $table->foreignId('rejete_par_id')->nullable()->after('motif_rejet')->constrained('users')->nullOnDelete();
            $table->timestamp('date_rejet')->nullable()->after('rejete_par_id');
        });

        Schema::create('courrier_ventilation_destinataires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courrier_id')->constrained('courriers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();

            $table->unique(['courrier_id', 'user_id', 'document_id'], 'courrier_vent_user_doc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courrier_ventilation_destinataires');

        Schema::table('courriers', function (Blueprint $table) {
            $table->dropForeign(['courrier_parent_id']);
            $table->dropForeign(['courrier_depart_source_id']);
            $table->dropForeign(['courrier_arrivee_lie_id']);
            $table->dropForeign(['directeur_en_attente_id']);
            $table->dropForeign(['rejete_par_id']);
            $table->dropColumn([
                'origine', 'courrier_parent_id', 'courrier_depart_source_id', 'courrier_arrivee_lie_id',
                'directeur_en_attente_id', 'motif_rejet', 'rejete_par_id', 'date_rejet',
            ]);
        });
    }
};
