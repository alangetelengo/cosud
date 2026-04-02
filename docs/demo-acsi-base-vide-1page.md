# Démo ACSI (1 page)

## 1) Initialisation rapide

Depuis `C:\laragon\www\GED` :

```powershell
php artisan migrate:fresh --seed --no-interaction
```

Objectif :
- base propre ;
- organigramme + utilisateurs ACSI chargés ;
- workflow global hiérarchique actif.

## 2) Principes métier à annoncer

- Partage dossier : uniquement **propriétaire**, **créateur** ou **administrateur**.
- Visibilité document : par accès au dossier (ou propriétaire/créateur du document).
- Validation : workflow **global hiérarchique**.
- Fin de validation pilotée par le type de document (`niveau_validation_final`) :
  - `chef_service`
  - `directeur`
  - `dg`

## 3) Scénario démo (5 minutes)

1. **Créer un dossier** de travail.
2. **Partager le dossier** aux agents concernés (lecture/écriture selon besoin).
3. **Déposer un document** dans ce dossier.
4. Cliquer **Envoyer en validation** vers le supérieur direct.
5. Montrer la progression hiérarchique, puis l’arrêt selon le type :
   - type A : arrêt chef de service ;
   - type B : arrêt directeur ;
   - type C : arrêt DG.

## 4) Vérification technique express

```powershell
php artisan tinker --execute="echo 'global_hierarchique_active='.\\App\\Models\\WorkflowEtape::where('code','validation_responsable')->where('validation_hierarchique',true)->where('actif',true)->count().PHP_EOL;"
php artisan tinker --execute="echo 'acsi_service_active='.\\App\\Models\\WorkflowEtape::where('code','like','acsi_service_%')->where('actif',true)->count().PHP_EOL;"
```

Résultat attendu :
- `global_hierarchique_active = 1`
- `acsi_service_active = 0`

## 5) Message de conclusion

La GED applique un cadre simple et robuste :
- accès contrôlé par dossier ;
- validation hiérarchique globale ;
- profondeur de validation configurable par type de document.
