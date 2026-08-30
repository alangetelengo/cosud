<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fournisseur_prestataires', function (Blueprint $table) {
            $table->string('telephone_2', 40)->nullable()->after('telephone');
            $table->boolean('notifier_telephone')->default(true)->after('telephone_2');
            $table->boolean('notifier_telephone_2')->default(true)->after('notifier_telephone');
        });

        Schema::table('courriers', function (Blueprint $table) {
            $table->string('expediteur_telephone_2', 40)->nullable()->after('expediteur_telephone');
            $table->boolean('expediteur_notifier_telephone')->default(true)->after('expediteur_telephone_2');
            $table->boolean('expediteur_notifier_telephone_2')->default(true)->after('expediteur_notifier_telephone');
        });
    }

    public function down(): void
    {
        Schema::table('fournisseur_prestataires', function (Blueprint $table) {
            $table->dropColumn(['telephone_2', 'notifier_telephone', 'notifier_telephone_2']);
        });

        Schema::table('courriers', function (Blueprint $table) {
            $table->dropColumn([
                'expediteur_telephone_2',
                'expediteur_notifier_telephone',
                'expediteur_notifier_telephone_2',
            ]);
        });
    }
};
