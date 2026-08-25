<?php

return [
    /**
     * Valeurs par défaut si la clé est absente en base (ex. avant migration).
     * lecture_dossier_lors_partage_document : si true, un partage / envoi document
     * peut accorder automatiquement la lecture du dossier parent (hors « une seule pièce »).
     * Défaut applicatif : false.
     */
    'defaults' => [
        'lecture_dossier_lors_partage_document' => false,
    ],

    /**
     * Parapheur départ : pièces éligibles pour constitution d'un courrier sortant.
     */
    'parapheur_depart' => [
        'types_document' => [
            'LETTRE',
            'COMPTE_RENDU',
            'NOTE_INTERNE',
            'RAPPORT',
            'DEVIS',
            'COURRIER_OUT',
        ],
        'statuts_document' => [
            'brouillon',
            'en_attente',
            'valide',
        ],
        'statuts_courrier_depart_actifs' => [
            'brouillon',
            'transmis_directeur',
            'rejete_directeur',
            'signe',
        ],
        /** Sous-dossier personnel (sous « Mes dossiers ») où sont rangées les pièces déposées. */
        'dossier_nom' => 'Courriers départ',
    ],

    /**
     * Circuits courriers : délai avant alerte « non traité » (heures).
     * Le DG est notifié pour interpeller le responsable de l’étape en cours.
     */
    'circuit_retard_heures' => (int) env('COSUD_CIRCUIT_RETARD_HEURES', 48),

    /**
     * Intervalle minimum entre deux alertes de retard pour le même courrier (heures).
     */
    'circuit_retard_rappel_heures' => (int) env('COSUD_CIRCUIT_RETARD_RAPPEL_HEURES', 24),

    /**
     * Envoi e-mail des notifications courrier (en plus de la cloche in-app).
     * Désactivé par défaut : un SMTP invalide ne doit pas bloquer l’enregistrement.
     */
    'courrier_notifications_mail' => filter_var(env('COSUD_COURRIER_NOTIFICATIONS_MAIL', false), FILTER_VALIDATE_BOOLEAN),

    /**
     * Registre courriers (livret flip-book) : nombre de lignes par feuillet.
     */
    'registre_lignes_par_feuillet' => (int) env('COSUD_REGISTRE_LIGNES_PAR_FEUILLET', 10),

    /**
     * SMS aux expéditeurs externes (validation / clôture / recouvrement).
     * Même API que SIFEC : wirepick | infobip.
     */
    'sms' => [
        'provider' => env('COSUD_SMS_PROVIDER', 'wirepick'),
        'sender_id' => env('COSUD_SMS_SENDER_ID', 'ACSI-SMS'),
        'wirepick' => [
            'client' => env('COSUD_SMS_WIREPICK_CLIENT'),
            'password' => env('COSUD_SMS_WIREPICK_PASSWORD'),
            'endpoint' => env('COSUD_SMS_WIREPICK_ENDPOINT', 'https://api.wirepick.com/httpsms/send'),
            'http_method' => env('COSUD_SMS_WIREPICK_HTTP_METHOD', 'get'),
        ],
        'infobip' => [
            'api_key' => env('COSUD_SMS_INFOBIP_API_KEY'),
            'send_url' => env('COSUD_SMS_INFOBIP_SEND_URL', 'https://mpn66j.api.infobip.com/sms/2/text/advanced'),
        ],
    ],
];
