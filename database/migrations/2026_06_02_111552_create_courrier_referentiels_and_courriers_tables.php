<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('courriers')) {
            return;
        }

        if (! Schema::hasTable('sens_courriers')) {
            Schema::create('sens_courriers', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->unique();
                $table->string('libelle', 100);
                $table->boolean('actif')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('type_courriers')) {
            Schema::create('type_courriers', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('libelle', 150);
                $table->boolean('actif')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('priorite_courriers')) {
            Schema::create('priorite_courriers', function (Blueprint $table) {
                $table->id();
                $table->string('code', 30)->unique();
                $table->string('libelle', 80);
                $table->unsignedSmallInteger('ordre')->default(0);
                $table->boolean('actif')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('statut_courriers')) {
            Schema::create('statut_courriers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sens_courrier_id')->constrained('sens_courriers')->cascadeOnDelete();
                $table->string('code', 50);
                $table->string('libelle', 120);
                $table->unsignedSmallInteger('ordre')->default(0);
                $table->boolean('est_initial')->default(false);
                $table->boolean('est_final')->default(false);
                $table->boolean('actif')->default(true);
                $table->timestamps();

                $table->unique(['sens_courrier_id', 'code'], 'statut_courrier_sens_code');
            });
        }

        if (! Schema::hasTable('parapheurs')) {
            Schema::create('parapheurs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sens_courrier_id')->constrained('sens_courriers')->cascadeOnDelete();
                $table->string('code', 50);
                $table->string('libelle', 150);
                $table->boolean('actif')->default(true);
                $table->timestamps();

                $table->unique(['sens_courrier_id', 'code'], 'parapheur_sens_code');
            });
        }

        Schema::create('courriers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sens_courrier_id')->constrained('sens_courriers');
            $table->foreignId('type_courrier_id')->nullable()->constrained('type_courriers')->nullOnDelete();
            $table->foreignId('statut_courrier_id')->constrained('statut_courriers');
            $table->foreignId('priorite_courrier_id')->nullable()->constrained('priorite_courriers')->nullOnDelete();
            $table->foreignId('parapheur_id')->nullable()->constrained('parapheurs')->nullOnDelete();
            $table->unsignedInteger('numero_registre');
            $table->unsignedSmallInteger('numero_registre_annee');
            $table->string('reference', 120)->nullable();
            $table->date('date_reception')->nullable();
            $table->date('date_courrier')->nullable();
            $table->string('numero_fulgurant', 100)->nullable();
            $table->string('expediteur_libelle', 255)->nullable();
            $table->string('destinataire_libelle', 255)->nullable();
            $table->boolean('est_expediteur_externe')->default(true);
            $table->foreignId('structure_expediteur_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->foreignId('structure_destinataire_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->string('objet', 500);
            $table->text('instructions_dg')->nullable();
            $table->timestamp('date_orientation')->nullable();
            $table->timestamp('date_expedition')->nullable();
            $table->foreignId('createur_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('signataire_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->foreignId('dossier_id')->nullable()->constrained('dossiers')->nullOnDelete();
            $table->timestamps();

            $table->unique(['sens_courrier_id', 'numero_registre_annee', 'numero_registre'], 'courriers_registre_unique');
            $table->index(['statut_courrier_id', 'created_at']);
        });

        if (! Schema::hasTable('courrier_document')) {
            Schema::create('courrier_document', function (Blueprint $table) {
                $table->id();
                $table->foreignId('courrier_id')->constrained('courriers')->cascadeOnDelete();
                $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
                $table->boolean('est_principal')->default(false);
                $table->timestamps();

                $table->unique(['courrier_id', 'document_id']);
            });
        }

        if (! Schema::hasTable('courrier_orientations')) {
            Schema::create('courrier_orientations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('courrier_id')->constrained('courriers')->cascadeOnDelete();
                $table->foreignId('structure_id')->constrained('structures')->cascadeOnDelete();
                $table->text('instructions')->nullable();
                $table->foreignId('oriente_par_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('courrier_transmissions')) {
            Schema::create('courrier_transmissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('courrier_id')->constrained('courriers')->cascadeOnDelete();
                $table->foreignId('de_structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->foreignId('vers_structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->foreignId('de_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('vers_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('date_transmission');
                $table->boolean('accuse_reception')->default(false);
                $table->string('accuse_chemin', 500)->nullable();
                $table->text('commentaire')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dossiers_formation')) {
            Schema::create('dossiers_formation', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('auditeur_nom', 200);
                $table->string('auditeur_email', 200)->nullable();
                $table->string('formation_libelle', 300);
                $table->string('statut', 50)->default('inscrit');
                $table->boolean('paiement_a_jour')->default(false);
                $table->text('notes')->nullable();
                $table->foreignId('createur_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
                $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dossiers_formation');
        Schema::dropIfExists('courrier_transmissions');
        Schema::dropIfExists('courrier_orientations');
        Schema::dropIfExists('courrier_document');
        Schema::dropIfExists('courriers');
        Schema::dropIfExists('parapheurs');
        Schema::dropIfExists('statut_courriers');
        Schema::dropIfExists('priorite_courriers');
        Schema::dropIfExists('type_courriers');
        Schema::dropIfExists('sens_courriers');
    }
};
