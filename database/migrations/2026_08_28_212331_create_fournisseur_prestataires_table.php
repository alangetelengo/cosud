<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fournisseur_prestataires', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('nom_normalise')->unique();
            $table->string('type', 40)->default('fournisseur');
            $table->string('email')->nullable();
            $table->string('telephone', 40)->nullable();
            $table->string('type_contrat')->nullable();
            $table->boolean('a_contrat')->default(false);
            $table->boolean('a_dossier_fiscal')->default(false);
            $table->text('observation')->nullable();
            $table->foreignId('dossier_id')->nullable()->constrained('dossiers')->nullOnDelete();
            $table->boolean('actif')->default(true);
            $table->foreignId('createur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['actif', 'nom']);
        });

        Schema::table('courriers', function (Blueprint $table) {
            $table->foreignId('fournisseur_prestataire_id')
                ->nullable()
                ->after('expediteur_libelle')
                ->constrained('fournisseur_prestataires')
                ->nullOnDelete();
        });

        Schema::table('suivi_paiements', function (Blueprint $table) {
            $table->foreignId('fournisseur_prestataire_id')
                ->nullable()
                ->after('fournisseur_libelle')
                ->constrained('fournisseur_prestataires')
                ->nullOnDelete();
        });

        Schema::table('moratoires', function (Blueprint $table) {
            $table->foreignId('fournisseur_prestataire_id')
                ->nullable()
                ->after('fournisseur_normalise')
                ->constrained('fournisseur_prestataires')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('moratoires', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fournisseur_prestataire_id');
        });

        Schema::table('suivi_paiements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fournisseur_prestataire_id');
        });

        Schema::table('courriers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fournisseur_prestataire_id');
        });

        Schema::dropIfExists('fournisseur_prestataires');
    }
};
