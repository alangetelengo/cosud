<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('code', 100)->nullable()->after('id');
            $table->string('reference', 100)->nullable()->after('description');
            $table->text('mots_cles')->nullable()->after('reference');
            $table->string('empreinte', 64)->nullable()->after('extension'); // hash SHA-256
            $table->string('mime_type', 150)->nullable()->after('empreinte');
            $table->boolean('en_corbeille')->default(false)->after('statut');
            $table->timestamp('date_suppression')->nullable()->after('en_corbeille');
            $table->foreignId('createur_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('modificateur_id')->nullable()->after('createur_id')->constrained('users')->nullOnDelete();
            $table->foreignId('proprietaire_id')->nullable()->after('modificateur_id')->constrained('users')->nullOnDelete();
            $table->foreignId('statut_document_id')->nullable()->after('proprietaire_id')->constrained('statut_documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['statut_document_id']);
            $table->dropForeign(['proprietaire_id']);
            $table->dropForeign(['modificateur_id']);
            $table->dropForeign(['createur_id']);
        });
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'code', 'reference', 'mots_cles', 'empreinte', 'mime_type',
                'en_corbeille', 'date_suppression', 'createur_id', 'modificateur_id',
                'proprietaire_id', 'statut_document_id'
            ]);
        });
    }
};
