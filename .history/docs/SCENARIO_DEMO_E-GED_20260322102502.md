
# Scénario de démonstration GED
## Cas pratique – Mise en valeur des fonctionnalités phares

Ce document décrit un scénario de démonstration clair, structuré en séquences chronologiques, pour présenter GED à la hiérarchie.

---

## Prérequis pour la démo

- **Durée estimée** : 15 à 20 minutes
- **Comptes de test** (ex. via seeder) :
  - `agent.daf@acsi.cg` / `password` (Agent DAF – créateur)
  - `chef.finances@acsi.cg` / `password` (Chef Service Finances – validateur)
  - `dir.af@acsi.cg` / `password` (Directeur DAF – validateur hiérarchique)
  - `dg@acsi.cg` / `password` (DG – vue globale)
  - `admin@acsi.cg` / `password` (Admin – paramétrage)
- **Fichiers prêts** : 1 PDF de test (ex. rapport ou facture)
- **Navigateur** : Firefox ou Chrome, fenêtre en plein écran

---

## SÉQUENCE 1 – Connexion et tableau de bord (2 min)

### Étapes
1. Aller sur la page de connexion.
2. Se connecter avec `agent.daf@acsi.cg` / `password`.
3. Montrer le **tableau de bord utilisateur** :
   - Nombre de documents liés à l’utilisateur
   - Derniers documents
   - Dossiers récents / favoris

### Points à mettre en avant
> « Chaque utilisateur a une vue personnalisée selon son rôle. Ici, l’agent voit ses documents et dossiers. Plus tard, on verra la vue du DG, qui est globale. »

---

## SÉQUENCE 2 – Dossiers et arborescence (2 min)

### Étapes
1. Cliquer sur **Dossiers** dans le menu.
2. Parcourir l’arborescence : Finance > Comptabilité > Factures clients.
3. Ouvrir le dossier **Factures clients**.
4. Montrer la liste des documents (ou la page vide si aucun).
5. (Optionnel) Marquer un dossier en **favori** pour un accès rapide.

### Points à mettre en avant
> « Le plan de classement reflète l’organisation. Chaque dossier est rattaché à une structure (ex. DAF). Le propriétaire est le responsable de la structure, donc un acteur métier, pas l’admin. »

---

## SÉQUENCE 3 – Dépôt d’un document (3 min) ⭐ PHARE

### Étapes
1. Cliquer sur **Documents** > **Déposer un document**.
2. Glisser-déposer un fichier PDF dans la zone de dépôt (ou clic pour sélectionner).
3. Vérifier que le type de document est suggéré (ex. PDF).
4. Remplir ou modifier les métadonnées :
   - Titre (pré-rempli si extraction PDF)
   - Référence (ex. FAC-2025-001)
   - Mots-clés
5. Choisir le dossier **Finance > Comptabilité > Factures clients**.
6. Cliquer sur **Déposer le document**.
7. Montrer le message de succès et la redirection.

### Points à mettre en avant
> « L’extraction automatique des métadonnées PDF (auteur, titre, date) limite la saisie manuelle. Le dépôt est simple : glisser-déposer, compléter si besoin, choisir le dossier. Le propriétaire du dossier et les personnes en partage reçoivent une notification par email. »

---

## SÉQUENCE 4 – Recherche (1 min 30)

### Étapes
1. Aller dans **Recherche**.
2. Saisir un mot du titre ou de la référence (ex. « FAC » ou « 2025 »).
3. Montrer les résultats avec les filtres (type, statut, dossier).

### Points à mettre en avant
> « La recherche permet de retrouver rapidement un document par titre, référence, mots-clés ou description. Les filtres affinent les résultats. »

---

## SÉQUENCE 5 – Envoi en validation (3 min) ⭐ PHARE

### Étapes
1. Aller dans **Documents**.
2. Repérer le document déposé (statut **Brouillon**).
3. Cliquer sur **Modifier** (ou l’action équivalente).
4. Cliquer sur **Envoyer en validation** (ou le bouton prévu).
5. Expliquer brièvement : « Le document part dans la chaîne hiérarchique : Chef Service Finances → Directeur DAF → DG. »
6. Confirmer l’envoi.
7. Vérifier que le statut passe à **En attente**.

### Points à mettre en avant
> « Le workflow est configuré par type de document. On peut utiliser une validation directe ou une chaîne hiérarchique basée sur la structure du créateur. Le validateur reçoit une notification. »

---

## SÉQUENCE 6 – Validation (côté validateur) (2 min 30) ⭐ PHARE

### Étapes
1. Se déconnecter (ou ouvrir une fenêtre privée).
2. Se connecter avec `chef.finances@acsi.cg` / `password`.
3. Aller dans **Notifications** : montrer la notification « Document en attente de validation ».
4. Cliquer sur la notification pour accéder au document.
5. Montrer les détails du document.
6. Choisir **Approuver** ou **Rejeter** :
   - Si **Approuver** : le document passe à l’étape suivante (Directeur DAF) ou est validé si c’était la dernière étape.
   - Si **Rejeter** : saisir un motif, valider. Le créateur est notifié.

### Points à mettre en avant
> « Chaque validateur voit les documents qui l’attendent. L’approbation fait avancer le workflow. Le rejet avec motif permet au créateur de corriger et de renvoyer. »

---

## SÉQUENCE 7 – Vue DG et tableau de bord global (2 min)

### Étapes
1. Se connecter avec `dg@acsi.cg` / `password`.
2. Montrer le **tableau de bord DG** :
   - Nombre total de documents, dossiers, utilisateurs
   - Répartition par statut (brouillon, en attente, validés, rejetés, archivés)
   - Graphique ou liste par structure
   - Documents récents
   - Documents en attente de validation

### Points à mettre en avant
> « Le DG a une vue consolidée de l’activité. Il peut suivre les documents en attente et les dépôts récents par structure. »

---

## SÉQUENCE 8 – Partage d’un dossier (1 min 30)

### Étapes
1. Aller dans **Dossiers**.
2. Ouvrir un dossier dont on est propriétaire (ou utiliser un compte adapté).
3. Cliquer sur **Partager** (ou l’icône partage).
4. Ajouter un utilisateur avec des droits (ex. lecture + écriture).
5. Montrer la liste des partages et les droits.

### Points à mettre en avant
> « Le partage permet d’ouvrir l’accès à un dossier à d’autres utilisateurs, avec des droits précis. On peut définir une date d’expiration. »

---

## SÉQUENCE 9 – Administration (vue rapide) (2 min)

### Étapes
1. Se connecter avec `admin@acsi.cg` / `password`.
2. Aller dans **Paramètres**.
3. Parcourir rapidement :
   - **Structures** : organigramme et responsables
   - **Rôles** : Admin, DG, Utilisateur et permissions
   - **Plan de classement** : arborescence des dossiers
   - **Workflow** : étapes de validation par type de document
   - **Journal d’audit** : exemples d’actions enregistrées

### Points à mettre en avant
> « L’admin configure l’organisation : structures, rôles, plan de classement et workflow. Le journal d’audit trace les actions sensibles. L’admin n’est pas notifié des dépôts ; seuls les acteurs métier le sont. »

---

## SÉQUENCE 10 – Corbeille (30 s)

### Étapes
1. Supprimer un document (test) en le mettant en corbeille.
2. Aller dans **Corbeille**.
3. Montrer la liste des documents supprimés.
4. Restaurer le document.

### Points à mettre en avant
> « La corbeille permet de récupérer un document supprimé par erreur avant suppression définitive. »

---

## Récapitulatif des fonctionnalités démontrées

| Séquence | Fonctionnalité | Priorité |
|----------|----------------|----------|
| 1 | Tableau de bord personnalisé | ★★ |
| 2 | Arborescence des dossiers | ★★ |
| 3 | **Dépôt de document (glisser-déposer, métadonnées)** | ★★★ |
| 4 | Recherche | ★★ |
| 5 | **Envoi en validation** | ★★★ |
| 6 | **Validation hiérarchique** | ★★★ |
| 7 | **Vue DG / tableau de bord global** | ★★★ |
| 8 | Partage de dossiers | ★★ |
| 9 | Administration et audit | ★★ |
| 10 | Corbeille | ★ |

---

## Conseils pour une démo réussie

1. **Préparer les données** : lancer `php artisan db:seed` si besoin pour avoir des structures, utilisateurs et dossiers.
2. **Préparer un PDF** : un fichier réel (rapport, note) avec métadonnées pour montrer l’extraction.
3. **Tester le parcours** : répéter une fois le scénario complet avant la présentation.
4. **Adapter au temps** : si le temps est limité, garder les séquences 1, 3, 5, 6 et 7.
5. **Anticiper les questions** : sécurité, sauvegardes, capacité, évolutions possibles.

---

*Document généré pour la démonstration GED – ACSI*
