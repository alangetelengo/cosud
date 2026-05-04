<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\CorbeilleController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DossierController;
use App\Http\Controllers\FonctionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ParametresController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PlanClassementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RechercheController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\StructureController;
use App\Http\Controllers\TypeDocumentController;
use App\Http\Controllers\TypeDossierController;
use App\Http\Controllers\TypeMetadonneeController;
use App\Http\Controllers\UserAffectationController;
use App\Http\Controllers\UtilisateurController;
use App\Http\Controllers\WorkflowEtapeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->middleware(['auth', 'verified', '2fa'])->name('home');

Route::middleware(['auth', 'verified', '2fa'])->group(function () {
    Route::get('/dashboard', fn () => redirect()->route('home'))->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // GED - Documents (download et valider avant resource pour éviter conflit)
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/fiche', [DocumentController::class, 'fiche'])->name('documents.fiche');
    Route::get('/documents/{document}/nouvelle-version', [DocumentController::class, 'nouvelleVersion'])->name('documents.nouvelle-version');
    Route::post('/documents/{document}/nouvelle-version', [DocumentController::class, 'storeNouvelleVersion'])->name('documents.nouvelle-version.store');
    Route::post('/documents/{document}/extraire-metadonnees', [DocumentController::class, 'extraireMetadonnees'])->name('documents.extraire-metadonnees');
    Route::post('/documents/{document}/valider', [DocumentController::class, 'valider'])->name('documents.valider');
    Route::post('/documents/{document}/envoyer-validation', [DocumentController::class, 'envoyerEnValidation'])->name('documents.envoyer-validation');
    Route::post('/documents/{document}/approuver', [DocumentController::class, 'approuver'])->name('documents.approuver');
    Route::post('/documents/{document}/rejeter', [DocumentController::class, 'rejeter'])->name('documents.rejeter');
    Route::post('/documents/{document}/archiver', [DocumentController::class, 'archiver'])->name('documents.archiver');
    Route::resource('documents', DocumentController::class);

    Route::get('/corbeille', [CorbeilleController::class, 'index'])->name('corbeille.index');
    Route::post('/corbeille/{document}/restore', [CorbeilleController::class, 'restore'])->name('corbeille.restore');

    // GED - Types de documents
    Route::resource('types-documents', TypeDocumentController::class)->parameters(['types-documents' => 'types_document']);

    // GED - Dossiers
    Route::get('/dossiers', [DossierController::class, 'index'])->name('dossiers.index');
    Route::get('/dossiers/create', [DossierController::class, 'create'])->name('dossiers.create');
    Route::post('/dossiers', [DossierController::class, 'store'])->name('dossiers.store');
    Route::post('/dossiers/{dossier}/favori', [DossierController::class, 'toggleFavori'])->name('dossiers.favori');
    Route::get('/dossiers/{dossier}/partages', [DossierController::class, 'partages'])->name('dossiers.partages');
    Route::post('/dossiers/{dossier}/partages', [DossierController::class, 'storePartage'])->name('dossiers.partages.store');
    Route::put('/dossiers/{dossier}/partages/{partage}', [DossierController::class, 'updatePartage'])->name('dossiers.partages.update');
    Route::delete('/dossiers/{dossier}/partages/{partage}', [DossierController::class, 'destroyPartage'])->name('dossiers.partages.destroy');
    Route::get('/dossiers/{dossier}/edit', [DossierController::class, 'edit'])->name('dossiers.edit');
    Route::put('/dossiers/{dossier}', [DossierController::class, 'update'])->name('dossiers.update');
    Route::delete('/dossiers/{dossier}', [DossierController::class, 'destroy'])->name('dossiers.destroy');
    Route::get('/dossiers/{dossier}', [DossierController::class, 'show'])->name('dossiers.show');
    Route::get('/recherche', [RechercheController::class, 'index'])->name('recherche.index');
    Route::get('/utilisateurs/export', [UtilisateurController::class, 'export'])->name('utilisateurs.export');
    Route::post('utilisateurs/bulk-2fa', [UtilisateurController::class, 'bulkToggle2FA'])->name('utilisateurs.bulk-2fa');
    Route::get('/utilisateurs/{user}/affectations', [UserAffectationController::class, 'index'])->name('utilisateurs.affectations.index');
    Route::post('/utilisateurs/{user}/affectations', [UserAffectationController::class, 'store'])->name('utilisateurs.affectations.store');
    Route::put('/utilisateurs/{user}/affectations/{structure}', [UserAffectationController::class, 'update'])->name('utilisateurs.affectations.update');
    Route::delete('/utilisateurs/{user}/affectations/{structure}', [UserAffectationController::class, 'destroy'])->name('utilisateurs.affectations.destroy');
    Route::resource('utilisateurs', UtilisateurController::class)->parameters(['utilisateurs' => 'user']);
    Route::get('/parametres', [ParametresController::class, 'index'])->name('parametres.index');
    Route::get('/parametres/ged-acces', [ParametresController::class, 'gedAcces'])->name('parametres.ged-acces');
    Route::put('/parametres/ged-acces', [ParametresController::class, 'updateGedAcces'])->name('parametres.ged-acces.update');
    Route::get('/parametres/audit', [AuditController::class, 'index'])->name('parametres.audit.index');
    Route::get('/parametres/roles', [RoleController::class, 'index'])->name('parametres.roles.index');
    Route::get('/parametres/roles/create', [RoleController::class, 'create'])->name('parametres.roles.create');
    Route::post('/parametres/roles', [RoleController::class, 'store'])->name('parametres.roles.store');
    Route::get('/parametres/roles/{role}/edit', [RoleController::class, 'edit'])->name('parametres.roles.edit');
    Route::put('/parametres/roles/{role}', [RoleController::class, 'update'])->name('parametres.roles.update');
    Route::delete('/parametres/roles/{role}', [RoleController::class, 'destroy'])->name('parametres.roles.destroy');
    Route::get('/parametres/permissions', [PermissionController::class, 'index'])->name('parametres.permissions.index');
    Route::get('/parametres/permissions/create', [PermissionController::class, 'create'])->name('parametres.permissions.create');
    Route::post('/parametres/permissions', [PermissionController::class, 'store'])->name('parametres.permissions.store');
    Route::get('/parametres/permissions/{permission}/edit', [PermissionController::class, 'edit'])->name('parametres.permissions.edit');
    Route::put('/parametres/permissions/{permission}', [PermissionController::class, 'update'])->name('parametres.permissions.update');
    Route::delete('/parametres/permissions/{permission}', [PermissionController::class, 'destroy'])->name('parametres.permissions.destroy');
    Route::get('/parametres/fonctions', [FonctionController::class, 'index'])->name('parametres.fonctions.index');
    Route::get('/parametres/fonctions/create', [FonctionController::class, 'create'])->name('parametres.fonctions.create');
    Route::post('/parametres/fonctions', [FonctionController::class, 'store'])->name('parametres.fonctions.store');
    Route::get('/parametres/fonctions/{fonction}/edit', [FonctionController::class, 'edit'])->name('parametres.fonctions.edit');
    Route::put('/parametres/fonctions/{fonction}', [FonctionController::class, 'update'])->name('parametres.fonctions.update');
    Route::delete('/parametres/fonctions/{fonction}', [FonctionController::class, 'destroy'])->name('parametres.fonctions.destroy');
    Route::get('/parametres/structures', [StructureController::class, 'index'])->name('parametres.structures.index');
    Route::get('/parametres/structures/create', [StructureController::class, 'create'])->name('parametres.structures.create');
    Route::post('/parametres/structures', [StructureController::class, 'store'])->name('parametres.structures.store');
    Route::get('/parametres/structures/{structure}/edit', [StructureController::class, 'edit'])->name('parametres.structures.edit');
    Route::put('/parametres/structures/{structure}', [StructureController::class, 'update'])->name('parametres.structures.update');
    Route::delete('/parametres/structures/{structure}', [StructureController::class, 'destroy'])->name('parametres.structures.destroy');
    Route::get('/parametres/workflow', [WorkflowEtapeController::class, 'index'])->name('parametres.workflow.index');
    Route::get('/parametres/workflow/create', [WorkflowEtapeController::class, 'create'])->name('parametres.workflow.create');
    Route::get('/parametres/workflow/create-circuit', [WorkflowEtapeController::class, 'createCircuit'])->name('parametres.workflow.create-circuit');
    Route::post('/parametres/workflow/store-circuit', [WorkflowEtapeController::class, 'storeCircuit'])->name('parametres.workflow.store-circuit');
    Route::post('/parametres/workflow', [WorkflowEtapeController::class, 'store'])->name('parametres.workflow.store');
    Route::get('/parametres/workflow/{workflow_etape}/edit', [WorkflowEtapeController::class, 'edit'])->name('parametres.workflow.edit');
    Route::put('/parametres/workflow/{workflow_etape}', [WorkflowEtapeController::class, 'update'])->name('parametres.workflow.update');
    Route::delete('/parametres/workflow/{workflow_etape}', [WorkflowEtapeController::class, 'destroy'])->name('parametres.workflow.destroy');
    Route::get('/parametres/types-dossiers', [TypeDossierController::class, 'index'])->name('parametres.types-dossiers.index');
    Route::get('/parametres/types-dossiers/create', [TypeDossierController::class, 'create'])->name('parametres.types-dossiers.create');
    Route::post('/parametres/types-dossiers', [TypeDossierController::class, 'store'])->name('parametres.types-dossiers.store');
    Route::get('/parametres/types-dossiers/{type_dossier}/edit', [TypeDossierController::class, 'edit'])->name('parametres.types-dossiers.edit');
    Route::put('/parametres/types-dossiers/{type_dossier}', [TypeDossierController::class, 'update'])->name('parametres.types-dossiers.update');
    Route::delete('/parametres/types-dossiers/{type_dossier}', [TypeDossierController::class, 'destroy'])->name('parametres.types-dossiers.destroy');
    Route::get('/parametres/types-metadonnees', [TypeMetadonneeController::class, 'index'])->name('parametres.types-metadonnees.index');
    Route::get('/parametres/types-metadonnees/create', [TypeMetadonneeController::class, 'create'])->name('parametres.types-metadonnees.create');
    Route::post('/parametres/types-metadonnees', [TypeMetadonneeController::class, 'store'])->name('parametres.types-metadonnees.store');
    Route::get('/parametres/types-metadonnees/{type_metadonnee}/edit', [TypeMetadonneeController::class, 'edit'])->name('parametres.types-metadonnees.edit');
    Route::put('/parametres/types-metadonnees/{type_metadonnee}', [TypeMetadonneeController::class, 'update'])->name('parametres.types-metadonnees.update');
    Route::get('/parametres/plan-classement', [PlanClassementController::class, 'index'])->name('parametres.plan-classement.index');
    Route::get('/parametres/plan-classement/create', [PlanClassementController::class, 'create'])->name('parametres.plan-classement.create');
    Route::post('/parametres/plan-classement', [PlanClassementController::class, 'store'])->name('parametres.plan-classement.store');
    Route::get('/parametres/plan-classement/{dossier}/edit', [PlanClassementController::class, 'edit'])->name('parametres.plan-classement.edit');
    Route::put('/parametres/plan-classement/{dossier}', [PlanClassementController::class, 'update'])->name('parametres.plan-classement.update');

    Route::view('/courriers', 'courriers.placeholder')->name('courriers.index');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/api/notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::get('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
});

require __DIR__.'/auth.php';
