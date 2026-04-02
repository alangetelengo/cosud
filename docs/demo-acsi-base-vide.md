# Démo ACSI sur base vide (principe final)

## 1) Préparer la base de démo

Depuis `C:\laragon\www\GED`, exécuter :

```powershell
php artisan migrate:fresh --seed --no-interaction
```

Effet attendu :
- toutes les tables sont recréées ;
- les données seedées (organigramme, fonctions, utilisateurs, types, workflow global, plan de classement) sont réinjectées ;
- aucun circuit projet/service legacy n’est actif.

## 2) Vérifier le prérequis des vraies données agents

Le fichier suivant doit exister :

- `database/seeders/data/acsi_agents_full.json`

Notes utiles :
- les comptes sont générés en `matricule@acsi.cg` ;
- mot de passe par défaut : `password`.

## 3) Règles métier à rappeler pendant la démo

- Le partage de dossier est géré uniquement par : **propriétaire**, **créateur**, ou **administrateur**.
- Un document est visible si l’utilisateur :
  - est propriétaire/créateur du document, ou
  - a accès au dossier qui contient le document (partage valide).
- Le workflow par défaut est **global + hiérarchique**.
- Le parcours de validation s’arrête selon le **niveau final du type de document** :
  - `chef_service`
  - `directeur`
  - `dg`

## 4) Scénario pratique de démonstration

### A. Création et partage du dossier

- Se connecter avec un utilisateur autorisé à créer le dossier cible.
- Aller dans **Plan de classement > Nouveau dossier**.
- Créer le dossier (ex. dossier de travail/projet du service).
- Ouvrir **Partager le dossier** puis accorder les droits nécessaires aux agents concernés.
- Optionnel :
  - appliquer aux sous-dossiers existants ;
  - activer l’héritage automatique pour les sous-dossiers futurs.

### B. Dépôt puis envoi en validation

- Un agent dépose un document dans le dossier partagé.
- Cliquer **Envoyer en validation** et choisir le destinataire hiérarchique direct (ex. chef de service).
- Vérifier que le document passe ensuite au niveau supérieur selon la chaîne hiérarchique active.

### C. Vérifier la fin de chaîne par type de document

- Tester un type dont `niveau_validation_final = chef_service` : arrêt au chef de service.
- Tester un type dont `niveau_validation_final = directeur` : arrêt au directeur.
- Tester un type dont `niveau_validation_final = dg` : montée jusqu’au DG.

## 5) Contrôles rapides post-seed (optionnel)

Exécuter :

```powershell
php artisan tinker --execute="echo 'acsi_service_active='.\\App\\Models\\WorkflowEtape::where('code','like','acsi_service_%')->where('actif',true)->count().PHP_EOL;"
php artisan tinker --execute="echo 'chef_projet_suffix_active='.\\App\\Models\\WorkflowEtape::where('code','like','%_chef_projet')->where('actif',true)->count().PHP_EOL; echo 'chef_pool_suffix_active='.\\App\\Models\\WorkflowEtape::where('code','like','%_chef_pool')->where('actif',true)->count().PHP_EOL; echo 'chef_service_suffix_active='.\\App\\Models\\WorkflowEtape::where('code','like','%_chef_service')->where('actif',true)->count().PHP_EOL;"
php artisan tinker --execute="echo 'global_hierarchique_active='.\\App\\Models\\WorkflowEtape::where('code','validation_responsable')->where('validation_hierarchique',true)->where('actif',true)->count().PHP_EOL;"
```

Résultat attendu :
- `acsi_service_active = 0`
- `chef_projet_suffix_active = 0`
- `chef_pool_suffix_active = 0`
- `chef_service_suffix_active = 0`
- `global_hierarchique_active = 1`

## 6) Messages clés à dire en présentation

- La GED applique un principe simple : **accès par dossier partagé** + **validation hiérarchique globale**.
- Les circuits projet/service spécifiques ne sont plus imposés au seed.
- La profondeur de validation dépend du **type de document**, pas d’un circuit local.
- Le système couvre les cas métier : visa court (chef de service), visa intermédiaire (directeur), validation complète (DG).

