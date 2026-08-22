<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suivi_paiements', function (Blueprint $table) {
            $table->date('date_decharge')->nullable()->after('date_suivi');
            $table->string('numero_piece')->nullable()->after('montant');
            $table->string('banque', 100)->nullable()->after('numero_piece');
            $table->string('beneficiaire_libelle')->nullable()->after('banque');
            $table->string('programmation')->nullable()->after('beneficiaire_libelle');
            $table->foreignId('controle_par_id')->nullable()->after('etabli_par_id')->constrained('users')->nullOnDelete();
            $table->timestamp('controle_at')->nullable()->after('controle_par_id');
        });
    }

    public function down(): void
    {
        Schema::table('suivi_paiements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('controle_par_id');
            $table->dropColumn([
                'date_decharge',
                'numero_piece',
                'banque',
                'beneficiaire_libelle',
                'programmation',
                'controle_at',
            ]);
        });
    }
};
