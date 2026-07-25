<?php

use App\Models\Document;
use App\Models\TypeDocument;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $ancien = TypeDocument::query()->where('code', 'COURRIER')->first();
        $entrant = TypeDocument::query()->where('code', 'COURRIER_IN')->first();

        if ($ancien && $entrant) {
            Document::query()
                ->where('type_document_id', $ancien->id)
                ->update(['type_document_id' => $entrant->id]);

            $ancien->update(['actif' => false]);
        }
    }

    public function down(): void
    {
        // Pas de retour automatique : réactiver COURRIER manuellement si besoin.
    }
};
