# Changelog

Toutes les évolutions notables de COSUD sont documentées ici.
Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et [Versionnement sémantique](https://semver.org/lang/fr/).

## [1.0.0] - 2026-08-26

### Ajouté

- Gestion des courriers (registres arrivée / départ, circuit de traitement, parapheur)
- GED : dossiers, documents, versions, partages et corbeille
- Suivi des dépenses (saisie DG, classement prestataire / bénéficiaire, export CSV)
- Suivi factures fournisseurs et bordereau de transmission
- Moratoires et factures de régularisation
- Notifications in-app, e-mail et SMS (Wirepick / Infobip)
- WhatsApp multi-driver (log, Meta, Infobip)
- Authentification Breeze, 2FA, rôles et permissions Spatie
- Paramétrage (structures, circuits, types courriers, plan de classement, etc.)
- Chargement uniforme des formulaires (spinner submit)

### Notes

- Première version destinée à la mise en production.
- Version affichée : footer, page de connexion, `php artisan cosud:version`.
