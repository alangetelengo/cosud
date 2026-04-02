# Plan de présentation GED
## Système de Gestion Électronique des Documents – ACSI

Document préparé pour la présentation à la hiérarchie – Ingénieur concepteur

---

## SLIDE 1 – Page de titre
**GED**  
*Gestion Électronique des Documents*

Propulsé par l’ACSI  
Présenté par : [Votre nom] – Ingénieur SI  
Date : [Date de présentation]

---

## SLIDE 2 – Contexte et objectif

### Problématique
- Multiplication des documents (papier et numérique)
- Difficultés de classement et de recherche
- Risque de perte ou de duplication
- Validation hiérarchique peu tracée
- Partage d’informations non structuré

### Objectif d’GED
Centraliser, sécuriser et organiser les documents professionnels avec :
- Un dépôt simple et structuré
- Une recherche rapide
- Une chaîne de validation traçable
- Des droits d’accès maîtrisés

---

## SLIDE 3 – Vue d’ensemble du système

### GED en quelques points
| Caractéristique | Description |
|-----------------|-------------|
| **Type** | Application web GED (SaaS) |
| **Technologie** | Laravel, PHP, Tailwind CSS |
| **Accès** | Navigateur web, authentification sécurisée |
| **Données** | Hébergées en interne, contrôle total |
| **Évolutivité** | Architecture modulaire |

### Bénéfices principaux
- ✅ Réduction du papier
- ✅ Traçabilité des actions
- ✅ Accès contrôlé par rôles
- ✅ Workflow de validation hiérarchique
- ✅ Notifications automatiques (email, in-app, SMS pour dossiers sensibles)

---

## SLIDE 4 – Architecture fonctionnelle

### Modules principaux

```
┌─────────────────────────────────────────────────────────────┐
│                    GED – MODULES                           │
├─────────────────────────────────────────────────────────────┤
│  📊 Tableau de bord   │  Vue adaptée : DG / Structure / User │
│  📁 Documents         │  Dépôt, consultation, workflow       │
│  📂 Dossiers          │  Arborescence, partages, favoris     │
│  🔍 Recherche         │  Recherche full-text (titre, ref…)   │
│  🗑️ Corbeille         │  Restauration documents supprimés    │
│  🔔 Notifications     │  Alertes dépôt, validation, rejet    │
├─────────────────────────────────────────────────────────────┤
│  ADMINISTRATION (réservé aux admins)                         │
│  👤 Utilisateurs  │  🏢 Structures  │  🔐 Rôles/Permissions  │
│  🗂️ Plan classement │  📑 Types      │  🔄 Workflow  │  📜 Audit │
└─────────────────────────────────────────────────────────────┘
```

---

## SLIDE 5 – Tableaux de bord adaptés

### Trois vues selon le profil

| Profil | Vue | Contenu |
|--------|-----|---------|
| **DG / Admin** | Vue globale | Stats org., documents par structure, en attente, récents |
| **Responsable de structure** | Vue structure | Docs de sa direction, en attente, récents |
| **Utilisateur** | Vue personnelle | Mes documents, favoris, derniers dossiers |

### Indicateurs clés
- Nombre de documents (brouillon, en attente, validés, rejetés, archivés)
- Répartition par structure
- Derniers dépôts
- Documents en attente de validation

---

## SLIDE 6 – Gestion des documents (1/2)

### Cycle de vie d’un document

```
DÉPÔT → BROUILLON → ENVOI VALIDATION → EN ATTENTE → VALIDÉ / REJETÉ → ARCHIVÉ
```

### Dépôt de document
- Glisser-déposer ou sélection de fichier
- Formats : PDF, Word, Excel, images
- Métadonnées : titre, référence, mots-clés, description
- **Extraction automatique** des métadonnées PDF (auteur, titre, date, etc.)
- Suggestion du type de document selon l’extension
- Classement dans un dossier (optionnel)

### Gestion des versions
- Historique des versions
- Traçabilité des modifications

---

## SLIDE 7 – Gestion des documents (2/2)

### Workflow de validation
- Validation directe (admin/DG) ou workflow par étapes
- **Validation hiérarchique** : remonte de la structure du créateur jusqu’au DG
- Approuver ou rejeter avec motif obligatoire
- Notifications aux validateurs et au créateur

### Statuts
- **Brouillon** : en cours de saisie
- **En attente** : en validation
- **Validé** : approuvé
- **Rejeté** : refusé (motif enregistré)
- **Archivé** : conservé après validation

---

## SLIDE 8 – Dossiers et plan de classement

### Arborescence des dossiers
- Plan de classement paramétrable par l’admin
- Hiérarchie illimitée (ex. : Finance > Comptabilité > Factures clients)
- Liaison dossiers ↔ structures (Direction responsable)
- Dossiers confidentiels et alertes SMS
- **Création de sous-dossiers par l'utilisateur** : avec la permission `dossiers.create-structure`, un utilisateur peut créer des dossiers uniquement dans les dossiers de sa structure (ex. : ingénieur DDSAIT → Projets → Projet A)

### Partages
- Partage par utilisateur avec droits (lecture, écriture, suppression)
- Date d’expiration optionnelle
- Propriétaire = responsable de la structure (acteur métier)

### Favoris
- Accès rapide aux dossiers les plus utilisés

---

## SLIDE 9 – Sécurité et traçabilité

### Gestion des accès
- Rôles : Admin, DG, Utilisateur
- Permissions granulaires (documents, dossiers, types, utilisateurs…)
- Structure hiérarchique (chaque direction a son périmètre)
- **`dossiers.create-structure`** : permet à un utilisateur de créer des sous-dossiers dans les dossiers de sa structure uniquement
- Dossiers confidentiels : permission spéciale

### Journal d’audit
- Enregistrement des actions sensibles : dépôt, modification, validation, archivage, suppression
- Identification de l’acteur et de l’heure

### Intégrité
- Empreinte SHA-256 des fichiers
- Pas de modification silencieuse des documents

---

## SLIDE 10 – Notifications

### Canaux
| Canal | Usage |
|-------|-------|
| **Email** | Dépôt, validation, rejet |
| **In-app** | Notification dans l’interface |
| **SMS** | Dossiers importants/confidentiels (optionnel, Vonage) |

### Acteurs notifiés (acteurs métier uniquement)
- Propriétaire du dossier
- Utilisateurs en partage lecture
- Validateurs lors de l’envoi en validation
- Créateur lors d’un approuvé/rejet

---

## SLIDE 11 – Administration et paramétrage

### Paramètres (réservés aux admins)
- **Structures** : organigramme, responsables
- **Rôles et permissions** : matrice des droits (incl. `dossiers.create-structure` pour la création de dossiers par structure)
- **Plan de classement** : arborescence des dossiers
- **Types de documents** : PDF, Word, Excel, etc. (extensions, taille max)
- **Types de dossiers** : Administration, Finance, Projet, etc.
- **Types de métadonnées** : champs indexables
- **Workflow** : étapes de validation par type de document
- **Journal d’audit** : consultation des actions

---

## SLIDE 12 – Technologies et déploiement

### Stack technique
- **Backend** : Laravel (PHP)
- **Frontend** : Blade, Tailwind CSS, Alpine.js
- **Base de données** : MySQL / MariaDB
- **Stockage** : Système de fichiers (local ou S3-compatible)

### Déploiement
- Hébergement sur serveur interne
- SSL recommandé pour la production
- Sauvegardes régulières des données et des fichiers

---

## SLIDE 13 – Prochaines étapes (optionnel)

- Formation des utilisateurs clés
- Migration des documents existants (si applicable)
- Réglage des workflows selon les processus métier
- Mise en production progressive par direction

---

## SLIDE 14 – Conclusion

**GED** est une solution GED interne, alignée sur l’organisation de l’ACSI :
- Gestion des documents centralisée
- Validation hiérarchique traçable
- Sécurité et audit intégrés
- Interface moderne et responsive

**Contact** : [Votre email]

---

*Document généré pour la présentation GED – ACSI*
