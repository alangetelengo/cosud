<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->foreignId('type_dossier_id')->nullable()->after('parent_id')->constrained('type_dossiers')->nullOnDelete();
            $table->foreignId('createur_id')->nullable()->after('ordre')->constrained('users')->nullOnDelete();
            $table->foreignId('proprietaire_id')->nullable()->after('createur_id')->constrained('users')->nullOnDelete();
            $table->string('niveau_confidentialite', 20)->nullable()->after('confidentiel'); // PUBLIC, CONFIDENTIEL, SECRET
            $table->unsignedInteger('capacite_max_documents')->nullable()->after('niveau_confidentialite');
            $table->unsignedBigInteger('taille_max_octets')->nullable()->after('capacite_max_documents');
            $table->string('couleur', 7)->nullable()->after('taille_max_octets');
            $table->string('icone', 50)->nullable()->after('couleur');
            $table->boolean('archivage_automatique')->default(false)->after('icone');
            $table->unsignedInteger('duree_conservation_annees')->nullable()->after('archivage_automatique');
            $table->string('statut', 20)->default('ouvert')->after('duree_conservation_annees'); // ouvert, fermer
        });

        Schema::table('dossiers', function (Blueprint $table) {
            $table->index('niveau_confidentialite');
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->dropIndex(['niveau_confidentialite']);
            $table->dropIndex(['statut']);
            $table->dropForeign(['type_dossier_id']);
            $table->dropForeign(['createur_id']);
            $table->dropForeign(['proprietaire_id']);
            $table->dropColumn([
                'type_dossier_id', 'createur_id', 'proprietaire_id',
                'niveau_confidentialite', 'capacite_max_documents', 'taille_max_octets',
                'couleur', 'icone', 'archivage_automatique', 'duree_conservation_annees', 'statut'
            ]);
        });
    }
};
