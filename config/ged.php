<?php

return [
    /**
     * Valeurs par défaut si la clé est absente en base (ex. avant migration).
     * lecture_dossier_lors_partage_document : si true (défaut), un partage / envoi document
     * peut accorder automatiquement la lecture du dossier parent (hors « une seule pièce »).
     */
    'defaults' => [
        'lecture_dossier_lors_partage_document' => true,
    ],
];
