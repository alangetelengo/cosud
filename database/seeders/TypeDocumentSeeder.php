<?php

namespace Database\Seeders;

use App\Models\TypeDocument;
use Illuminate\Database\Seeder;

class TypeDocumentSeeder extends Seeder
{
    /**
     * duree_conservation_annees : DUA indicative (années), 0 = permanent — à ajuster selon référentiel interne / légal.
     */
    public function run(): void
    {
        $types = [
            // Administration
            ['code' => 'CONTRAT_TRAVAIL', 'libelle' => 'Contrat de travail', 'extension_defaut' => 'pdf', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 30],
            ['code' => 'CV', 'libelle' => 'CV', 'extension_defaut' => 'pdf', 'taille_max_ko' => 5120, 'duree_conservation_annees' => 5],
            ['code' => 'FICHE_PAIE', 'libelle' => 'Fiche de paie', 'extension_defaut' => 'pdf', 'taille_max_ko' => 5120, 'duree_conservation_annees' => 5],
            ['code' => 'STATUT', 'libelle' => 'Statut', 'extension_defaut' => 'pdf', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 0],
            ['code' => 'CONTRAT_JURIDIQUE', 'libelle' => 'Contrat juridique', 'extension_defaut' => 'pdf', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 10],
            ['code' => 'PROCES_VERBAL', 'libelle' => 'Procès-verbal', 'extension_defaut' => 'pdf', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 0, 'niveau_validation_final' => 'directeur'],
            ['code' => 'RAPPORT', 'libelle' => 'Rapport', 'extension_defaut' => 'pdf', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 5, 'niveau_validation_final' => 'directeur'],
            ['code' => 'COURRIER_IN', 'libelle' => 'Courrier entrant', 'extension_defaut' => 'pdf', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 3, 'niveau_validation_final' => 'chef_service'],
            ['code' => 'COURRIER_OUT', 'libelle' => 'Courrier sortant', 'extension_defaut' => 'pdf', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 3, 'niveau_validation_final' => 'chef_service'],
            ['code' => 'LETTRE', 'libelle' => 'Lettre', 'extension_defaut' => 'docx', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 5, 'niveau_validation_final' => 'chef_service'],
            ['code' => 'COMPTE_RENDU', 'libelle' => 'Compte-rendu de réunion', 'extension_defaut' => 'docx', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 5, 'niveau_validation_final' => 'directeur'],
            ['code' => 'NOTE_INTERNE', 'libelle' => 'Note interne', 'extension_defaut' => 'docx', 'taille_max_ko' => 5120, 'duree_conservation_annees' => 3, 'niveau_validation_final' => 'chef_service'],
            ['code' => 'DEVIS', 'libelle' => 'Devis', 'extension_defaut' => 'pdf', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 5, 'niveau_validation_final' => 'chef_service'],
            ['code' => 'DECISION', 'libelle' => 'Décision', 'extension_defaut' => 'pdf', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 10, 'niveau_validation_final' => 'dg'],

            // Finance
            ['code' => 'FACTURE', 'libelle' => 'Facture', 'extension_defaut' => 'pdf', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 10],
            ['code' => 'RELEVE_BANCAIRE', 'libelle' => 'Relevé bancaire', 'extension_defaut' => 'pdf', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 10],
            ['code' => 'PREVISION_BUDGETAIRE', 'libelle' => 'Prévision budgétaire', 'extension_defaut' => 'xlsx', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 10],
            ['code' => 'RAPPORT_FINANCIER', 'libelle' => 'Rapport financier', 'extension_defaut' => 'pdf', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 10, 'niveau_validation_final' => 'directeur'],

            // Projets
            ['code' => 'CAHIER_CHARGES', 'libelle' => 'Cahier des charges', 'extension_defaut' => 'pdf', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 10],
            ['code' => 'PLANNING', 'libelle' => 'Planning', 'extension_defaut' => 'xlsx', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 5],
            ['code' => 'DOC_TECHNIQUE', 'libelle' => 'Document technique', 'extension_defaut' => 'pdf', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 10],
            ['code' => 'LIVRABLE', 'libelle' => 'Livrable', 'extension_defaut' => 'pdf', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 10],

            // Génériques
            ['code' => 'IMAGE', 'libelle' => 'Image / Scan', 'extension_defaut' => 'jpg', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 5],
            ['code' => 'PDF', 'libelle' => 'PDF', 'extension_defaut' => 'pdf', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 10],
            ['code' => 'DOCUMENT_WORD', 'libelle' => 'Document Word', 'extension_defaut' => 'docx', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 10],
            ['code' => 'TABLEUR', 'libelle' => 'Tableur', 'extension_defaut' => 'xlsx', 'taille_max_ko' => 10240, 'duree_conservation_annees' => 10],
            ['code' => 'AUTRE', 'libelle' => 'Autre document', 'extension_defaut' => 'pdf', 'taille_max_ko' => 10240, 'duree_conservation_annees' => null],
        ];

        $codes = array_column($types, 'code');
        foreach ($types as $type) {
            TypeDocument::updateOrCreate(
                ['code' => $type['code']],
                array_merge($type, [
                    'actif' => true,
                    'validation_obligatoire' => true,
                    'niveau_validation_final' => $type['niveau_validation_final'] ?? 'dg',
                ])
            );
        }

        // Désactiver les anciens types non présents dans la nouvelle liste
        TypeDocument::whereNotIn('code', $codes)->update(['actif' => false]);
    }
}
