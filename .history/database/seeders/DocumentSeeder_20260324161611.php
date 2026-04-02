<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\Dossier;
use App\Models\StatutDocument;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DocumentSeeder extends Seeder
{
    public function run(): void
    {
        if (Document::count() > 0) {
            return;
        }

        $user = User::whereNotNull('structure_id')->first() ?? User::first();
        $statutBrouillon = StatutDocument::where('code', 'brouillon')->first();
        $typePdf = TypeDocument::where('code', 'PDF')->first();
        $dossierRapports = Dossier::where('nom', 'Rapports')
            ->whereHas('parent', fn ($q) => $q->where('nom', 'Direction'))
            ->first();
        $dossierFactures = Dossier::where('nom', 'Factures clients')
            ->whereHas('parent', fn ($q) => $q->where('nom', 'Comptabilité'))
            ->first();

        if (! $user || ! $typePdf) {
            return;
        }

        $docs = [
            [
                'titre' => 'Rapport d\'activité 2024',
                'nom' => 'rapport-activite-2024.txt',
                'dossier' => $dossierRapports,
            ],
            [
                'titre' => 'Procédure de classement',
                'nom' => 'procedure-classement.txt',
                'dossier' => null,
            ],
            [
                'titre' => 'Exemple facture client',
                'nom' => 'facture-exemple.txt',
                'dossier' => $dossierFactures,
            ],
        ];

        $type = $typePdf;
        foreach ($docs as $i => $doc) {
            $path = 'documents/' . date('Y/m') . '/' . $doc['nom'];
            Storage::disk('public')->put($path, "Document de démonstration GED\n\n" . $doc['titre'] . "\n\nGénéré automatiquement par le seeder.");

            Document::create([
                'type_document_id' => $type->id,
                'dossier_id' => $doc['dossier']?->id,
                'user_id' => $user->id,
                'createur_id' => $user->id,
                'proprietaire_id' => $user->id,
                'statut_document_id' => $statutBrouillon?->id,
                'nom_original' => $doc['nom'],
                'chemin' => $path,
                'extension' => 'txt',
                'taille_octets' => Storage::disk('public')->size($path),
                'titre' => $doc['titre'],
                'description' => 'Document de démonstration',
                'statut' => 'brouillon',
            ]);
        }
    }
}
