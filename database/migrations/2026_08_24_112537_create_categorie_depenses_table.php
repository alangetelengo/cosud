<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorie_depenses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle');
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->boolean('est_systeme')->default(false);
            $table->timestamps();
        });

        $now = now();
        DB::table('categorie_depenses')->insert([
            ['code' => 'facture', 'libelle' => 'Fiche de suivi paiement facture', 'ordre' => 10, 'actif' => true, 'est_systeme' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'paiement_divers', 'libelle' => 'Fiche de suivi paiement divers', 'ordre' => 20, 'actif' => true, 'est_systeme' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'paie', 'libelle' => 'Paie', 'ordre' => 30, 'actif' => true, 'est_systeme' => false, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'commission', 'libelle' => 'Commission', 'ordre' => 40, 'actif' => true, 'est_systeme' => false, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'ttf', 'libelle' => 'TTF', 'ordre' => 50, 'actif' => true, 'est_systeme' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('categorie_depenses');
    }
};
