<?php

use App\Models\SensCourrier;
use App\Models\StatutCourrier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->boolean('est_confidentiel')->default(false)->after('objet');
            $table->string('orientation_mode', 40)->nullable()->after('est_confidentiel');
        });

        Schema::table('courrier_orientations', function (Blueprint $table) {
            $table->string('destinataire_type', 40)->nullable()->after('structure_id');
            $table->foreignId('destinataire_user_id')->nullable()->after('destinataire_type')->constrained('users')->nullOnDelete();
        });

        Schema::create('courrier_orientation_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courrier_id')->constrained('courriers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['courrier_id', 'user_id']);
        });

        $arriveeId = SensCourrier::query()->where('code', SensCourrier::ARRIVEE)->value('id');
        if ($arriveeId) {
            StatutCourrier::query()->updateOrCreate(
                ['sens_courrier_id' => $arriveeId, 'code' => 'attente_reponse_particuliere'],
                [
                    'libelle' => 'Attente réponse (particulière)',
                    'ordre' => 25,
                    'est_initial' => false,
                    'est_final' => false,
                    'actif' => true,
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('courrier_orientation_notifications');

        Schema::table('courrier_orientations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('destinataire_user_id');
            $table->dropColumn('destinataire_type');
        });

        Schema::table('courriers', function (Blueprint $table) {
            $table->dropColumn(['est_confidentiel', 'orientation_mode']);
        });

        $arriveeId = SensCourrier::query()->where('code', SensCourrier::ARRIVEE)->value('id');
        if ($arriveeId) {
            StatutCourrier::query()
                ->where('sens_courrier_id', $arriveeId)
                ->where('code', 'attente_reponse_particuliere')
                ->delete();
        }
    }
};
