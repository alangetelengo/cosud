<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\BordereauTransmissionController;
use App\Http\Controllers\CategorieDepenseController;
use App\Http\Controllers\CircuitCourrierController;
use App\Http\Controllers\CorbeilleController;
use App\Http\Controllers\CourrierController;
use App\Http\Controllers\CourrierRegistreController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DossierController;
use App\Http\Controllers\FactureRegularisationController;
use App\Http\Controllers\FonctionController;
use App\Http\Controllers\FournisseurPrestataireController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MoratoireController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ParametresController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PlanClassementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RechercheController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\StructureController;
use App\Http\Controllers\SuiviFacturesFournisseursController;
use App\Http\Controllers\SuiviPaiementController;
use App\Http\Controllers\TypeCourrierController;
use App\Http\Controllers\TypeDocumentController;
use App\Http\Controllers\TypeDossierController;
use App\Http\Controllers\TypeMetadonneeController;
use App\Http\Controllers\UserAffectationController;
use App\Http\Controllers\UtilisateurController;
use App\Http\Controllers\Webhooks\InfobipWhatsAppWebhookController;
use App\Http\Controllers\WorkflowEtapeController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/infobip/whatsapp', InfobipWhatsAppWebhookController::class)
    ->name('webhooks.infobip.whatsapp');

Route::get('/', HomeController::class)->middleware(['auth', 'verified', '2fa', 'password.changed'])->name('home');

Route::middleware(['auth', 'verified', '2fa', 'password.changed'])->group(function () {
    Route::get('/dashboard', fn () => redirect()->route('home'))->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // COSUD - Documents (download et valider avant resource pour éviter conflit)
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

    // COSUD - Types de documents
    Route::resource('types-documents', TypeDocumentController::class)->parameters(['types-documents' => 'types_document']);

    // COSUD - Dossiers
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
    Route::get('/parametres/cosud-acces', [ParametresController::class, 'cosudAcces'])->name('parametres.cosud-acces');
    Route::put('/parametres/cosud-acces', [ParametresController::class, 'updateCosudAcces'])->name('parametres.cosud-acces.update');
    Route::get('/parametres/notifications', [ParametresController::class, 'notifications'])->name('parametres.notifications');
    Route::put('/parametres/notifications', [ParametresController::class, 'updateNotifications'])->name('parametres.notifications.update');
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
    Route::get('/parametres/types-courriers', [TypeCourrierController::class, 'index'])->name('parametres.types-courriers.index');
    Route::get('/parametres/types-courriers/create', [TypeCourrierController::class, 'create'])->name('parametres.types-courriers.create');
    Route::post('/parametres/types-courriers', [TypeCourrierController::class, 'store'])->name('parametres.types-courriers.store');
    Route::get('/parametres/types-courriers/{type_courrier}/edit', [TypeCourrierController::class, 'edit'])->name('parametres.types-courriers.edit');
    Route::put('/parametres/types-courriers/{type_courrier}', [TypeCourrierController::class, 'update'])->name('parametres.types-courriers.update');
    Route::delete('/parametres/types-courriers/{type_courrier}', [TypeCourrierController::class, 'destroy'])->name('parametres.types-courriers.destroy');
    Route::get('/parametres/circuits-courriers', [CircuitCourrierController::class, 'index'])->name('parametres.circuits-courriers.index');
    Route::get('/parametres/circuits-courriers/create', [CircuitCourrierController::class, 'create'])->name('parametres.circuits-courriers.create');
    Route::post('/parametres/circuits-courriers', [CircuitCourrierController::class, 'store'])->name('parametres.circuits-courriers.store');
    Route::get('/parametres/circuits-courriers/{circuit_courrier}/edit', [CircuitCourrierController::class, 'edit'])->name('parametres.circuits-courriers.edit');
    Route::put('/parametres/circuits-courriers/{circuit_courrier}', [CircuitCourrierController::class, 'update'])->name('parametres.circuits-courriers.update');
    Route::delete('/parametres/circuits-courriers/{circuit_courrier}', [CircuitCourrierController::class, 'destroy'])->name('parametres.circuits-courriers.destroy');
    Route::get('/parametres/workflow/create', [WorkflowEtapeController::class, 'create'])->name('parametres.workflow.create');
    Route::get('/parametres/workflow/create-circuit', [WorkflowEtapeController::class, 'createCircuit'])->name('parametres.workflow.create-circuit');
    Route::post('/parametres/workflow/store-circuit', [WorkflowEtapeController::class, 'storeCircuit'])->name('parametres.workflow.store-circuit');
    Route::post('/parametres/workflow', [WorkflowEtapeController::class, 'store'])->name('parametres.workflow.store');
    Route::get('/parametres/workflow/{workflow_etape}/edit', [WorkflowEtapeController::class, 'edit'])->name('parametres.workflow.edit');
    Route::put('/parametres/workflow/{workflow_etape}', [WorkflowEtapeController::class, 'update'])->name('parametres.workflow.update');
    Route::delete('/parametres/workflow/{workflow_etape}', [WorkflowEtapeController::class, 'destroy'])->name('parametres.workflow.destroy');
    Route::get('/parametres/types-dossiers', [TypeDossierController::class, 'index'])->name('parametres.types-dossiers.index');
    Route::get('/parametres/categories-depense', [CategorieDepenseController::class, 'index'])->name('parametres.categories-depense.index');
    Route::get('/parametres/categories-depense/create', [CategorieDepenseController::class, 'create'])->name('parametres.categories-depense.create');
    Route::post('/parametres/categories-depense', [CategorieDepenseController::class, 'store'])->name('parametres.categories-depense.store');
    Route::get('/parametres/categories-depense/{categorie_depense}/edit', [CategorieDepenseController::class, 'edit'])->name('parametres.categories-depense.edit');
    Route::put('/parametres/categories-depense/{categorie_depense}', [CategorieDepenseController::class, 'update'])->name('parametres.categories-depense.update');
    Route::delete('/parametres/categories-depense/{categorie_depense}', [CategorieDepenseController::class, 'destroy'])->name('parametres.categories-depense.destroy');
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

    Route::get('/courriers-a-recevoir', [CourrierController::class, 'aRecevoir'])->name('courriers.a-recevoir');
    Route::get('/registres/courriers/arrivee', [CourrierRegistreController::class, 'arrivee'])->name('courriers.registres.arrivee');
    Route::get('/registres/courriers/depart', [CourrierRegistreController::class, 'depart'])->name('courriers.registres.depart');
    Route::get('/registres/courriers/arrivee/imprimer', [CourrierRegistreController::class, 'printArrivee'])->name('courriers.registres.print-arrivee');
    Route::get('/registres/courriers/depart/imprimer', [CourrierRegistreController::class, 'printDepart'])->name('courriers.registres.print-depart');
    Route::get('/bordereau-transmission', [BordereauTransmissionController::class, 'index'])->name('bordereau-transmission.index');
    Route::get('/bordereau-transmission/export', [BordereauTransmissionController::class, 'export'])->name('bordereau-transmission.export');
    Route::get('/suivi-paiements', [SuiviPaiementController::class, 'index'])->name('suivi-paiements.index');
    Route::get('/suivi-paiements/export', [SuiviPaiementController::class, 'export'])->name('suivi-paiements.export');
    Route::get('/suivi-paiements/export-hebdomadaire', [SuiviPaiementController::class, 'exportHebdomadaire'])->name('suivi-paiements.export-hebdomadaire');
    Route::get('/suivi-paiements/imprimer', [SuiviPaiementController::class, 'print'])->name('suivi-paiements.print');
    Route::post('/suivi-paiements/remise-dg', [SuiviPaiementController::class, 'storeRemiseDg'])->name('suivi-paiements.remise-dg');
    Route::get('/suivi-paiements/{suiviPaiement}/classer', [SuiviPaiementController::class, 'classerForm'])->name('suivi-paiements.classer');
    Route::post('/suivi-paiements/{suiviPaiement}/classer', [SuiviPaiementController::class, 'classer'])->name('suivi-paiements.classer.store');
    Route::get('/suivi-factures-fournisseurs', [SuiviFacturesFournisseursController::class, 'index'])->name('suivi-factures-fournisseurs.index');
    Route::get('/suivi-factures-fournisseurs/export', [SuiviFacturesFournisseursController::class, 'export'])->name('suivi-factures-fournisseurs.export');
    Route::get('/suivi-factures-fournisseurs/imprimer', [SuiviFacturesFournisseursController::class, 'print'])->name('suivi-factures-fournisseurs.print');

    Route::get('/fournisseurs-prestataires', [FournisseurPrestataireController::class, 'index'])->name('fournisseurs-prestataires.index');
    Route::get('/fournisseurs-prestataires/imprimer', [FournisseurPrestataireController::class, 'print'])->name('fournisseurs-prestataires.print');
    Route::get('/fournisseurs-prestataires/create', [FournisseurPrestataireController::class, 'create'])->name('fournisseurs-prestataires.create');
    Route::post('/fournisseurs-prestataires', [FournisseurPrestataireController::class, 'store'])->name('fournisseurs-prestataires.store');
    Route::get('/fournisseurs-prestataires/{fournisseur_prestataire}', [FournisseurPrestataireController::class, 'show'])->name('fournisseurs-prestataires.show');
    Route::get('/fournisseurs-prestataires/{fournisseur_prestataire}/pieces/{type}/{index}', [FournisseurPrestataireController::class, 'showPiece'])
        ->whereIn('type', ['contrat', 'fiscal'])
        ->whereNumber('index')
        ->name('fournisseurs-prestataires.pieces.show');
    Route::get('/fournisseurs-prestataires/{fournisseur_prestataire}/edit', [FournisseurPrestataireController::class, 'edit'])->name('fournisseurs-prestataires.edit');
    Route::put('/fournisseurs-prestataires/{fournisseur_prestataire}', [FournisseurPrestataireController::class, 'update'])->name('fournisseurs-prestataires.update');
    Route::delete('/fournisseurs-prestataires/{fournisseur_prestataire}', [FournisseurPrestataireController::class, 'destroy'])->name('fournisseurs-prestataires.destroy');

    Route::get('/factures-regularisation', [FactureRegularisationController::class, 'index'])->name('factures-regularisation.index');
    Route::get('/factures-regularisation/create', [FactureRegularisationController::class, 'create'])->name('factures-regularisation.create');
    Route::post('/factures-regularisation', [FactureRegularisationController::class, 'store'])->name('factures-regularisation.store');
    Route::get('/factures-regularisation/{courrier}/edit', [FactureRegularisationController::class, 'edit'])->name('factures-regularisation.edit');
    Route::put('/factures-regularisation/{courrier}', [FactureRegularisationController::class, 'update'])->name('factures-regularisation.update');
    Route::delete('/factures-regularisation/{courrier}', [FactureRegularisationController::class, 'destroy'])->name('factures-regularisation.destroy');
    Route::get('/factures-regularisation/{courrier}/payer', [FactureRegularisationController::class, 'payerForm'])->name('factures-regularisation.payer');
    Route::post('/factures-regularisation/{courrier}/payer', [FactureRegularisationController::class, 'payer'])->name('factures-regularisation.payer.store');

    Route::get('/moratoires', [MoratoireController::class, 'index'])->name('moratoires.index');
    Route::get('/moratoires/create', [MoratoireController::class, 'create'])->name('moratoires.create');
    Route::post('/moratoires', [MoratoireController::class, 'store'])->name('moratoires.store');
    Route::get('/moratoires/dettes/detail', [MoratoireController::class, 'detailDettes'])->name('moratoires.dettes.detail');
    Route::get('/moratoires/imprimer-dettes', [MoratoireController::class, 'printDettes'])->name('moratoires.print-dettes');
    Route::get('/moratoires/imprimer-plans', [MoratoireController::class, 'printPlans'])->name('moratoires.print-plans');
    Route::get('/moratoires/{moratoire}', [MoratoireController::class, 'show'])->name('moratoires.show');
    Route::get('/moratoires/{moratoire}/imprimer', [MoratoireController::class, 'print'])->name('moratoires.print');
    Route::patch('/moratoires/{moratoire}/echeances/{echeance}', [MoratoireController::class, 'updateEcheance'])->name('moratoires.echeances.update');
    Route::get('/courriers', [CourrierController::class, 'index'])->name('courriers.index');
    Route::get('/courriers/create', [CourrierController::class, 'create'])->name('courriers.create');
    Route::post('/courriers', [CourrierController::class, 'store'])->name('courriers.store');
    Route::get('/courriers/{courrier}', [CourrierController::class, 'show'])->name('courriers.show');
    Route::get('/courriers/{courrier}/edit', [CourrierController::class, 'edit'])->name('courriers.edit');
    Route::get('/courriers/{courrier}/documents/{document}/apercu', [CourrierController::class, 'apercuDocument'])->name('courriers.documents.apercu');
    Route::put('/courriers/{courrier}', [CourrierController::class, 'update'])->name('courriers.update');
    Route::delete('/courriers/{courrier}', [CourrierController::class, 'destroy'])->name('courriers.destroy');
    Route::post('/courriers/{courrier}/parapheur', [CourrierController::class, 'mettreEnParapheur'])->name('courriers.parapheur');
    Route::post('/courriers/{courrier}/orienter', [CourrierController::class, 'orienter'])->name('courriers.orienter');
    Route::post('/courriers/{courrier}/ventiler', [CourrierController::class, 'ventiler'])->name('courriers.ventiler');
    Route::post('/courriers/{courrier}/cloturer', [CourrierController::class, 'cloturer'])->name('courriers.cloturer');
    Route::post('/courriers/{courrier}/signer', [CourrierController::class, 'signer'])->name('courriers.signer');
    Route::post('/courriers/{courrier}/archiver', [CourrierController::class, 'archiverCourrier'])->name('courriers.archiver');
    Route::post('/courriers/{courrier}/classer-dossier', [CourrierController::class, 'classerDossier'])->name('courriers.classer-dossier');
    Route::post('/courriers/{courrier}/transmettre', [CourrierController::class, 'transmettre'])->name('courriers.transmettre');
    Route::post('/courriers/{courrier}/transmettre-directeur', [CourrierController::class, 'transmettreAuDirecteur'])->name('courriers.transmettre-directeur');
    Route::post('/courriers/{courrier}/rejeter-depart', [CourrierController::class, 'rejeterDepart'])->name('courriers.rejeter-depart');
    Route::post('/courriers/{courrier}/annuler', [CourrierController::class, 'annuler'])->name('courriers.annuler');
    Route::post('/courriers/{courrier}/expedier-interne', [CourrierController::class, 'expedierVersSecretariat'])->name('courriers.expedier-interne');
    Route::post('/courriers-depart/{courrier}/accepter-reception', [CourrierController::class, 'accepterReceptionInterne'])->name('courriers.accepter-reception');
    Route::post('/courriers-depart/{courrier}/refuser-reception', [CourrierController::class, 'refuserReceptionInterne'])->name('courriers.refuser-reception');
    Route::post('/courriers/{courrier}/creer-reponse', [CourrierController::class, 'creerReponse'])->name('courriers.creer-reponse');
    Route::post('/courriers/{courrier}/circuit/avancer', [CircuitCourrierController::class, 'avancer'])->name('courriers.circuit.avancer');
    Route::post('/courriers/{courrier}/circuit/instruire', [CircuitCourrierController::class, 'instruire'])->name('courriers.circuit.instruire');
    Route::post('/courriers/{courrier}/circuit/relancer', [CircuitCourrierController::class, 'relancer'])->name('courriers.circuit.relancer');
    Route::post('/courriers/{courrier}/circuit/soumettre-reponse', [CircuitCourrierController::class, 'soumettreReponse'])->name('courriers.circuit.soumettre-reponse');
    Route::post('/courriers/{courrier}/circuit/valider-reponse', [CircuitCourrierController::class, 'validerReponse'])->name('courriers.circuit.valider-reponse');
    Route::post('/courriers/{courrier}/circuit/rejeter-reponse', [CircuitCourrierController::class, 'rejeterReponse'])->name('courriers.circuit.rejeter-reponse');
    Route::post('/courriers/{courrier}/circuit/envoyer-cheque', [CircuitCourrierController::class, 'envoyerCheque'])->name('courriers.circuit.envoyer-cheque');
    Route::post('/courriers/{courrier}/circuit/signer-cheque', [CircuitCourrierController::class, 'signerCheque'])->name('courriers.circuit.signer-cheque');
    Route::post('/courriers/{courrier}/circuit/deposer-preuve-paiement', [CircuitCourrierController::class, 'deposerPreuvePaiement'])->name('courriers.circuit.deposer-preuve-paiement');
    Route::post('/courriers/{courrier}/circuit/payer-reliquat', [CircuitCourrierController::class, 'payerReliquat'])->name('courriers.circuit.payer-reliquat');
    Route::post('/courriers/{courrier}/circuit/confirmer-controle-depense', [CircuitCourrierController::class, 'confirmerControleDepense'])->name('courriers.circuit.confirmer-controle-depense');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/api/notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::get('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
});

require __DIR__.'/auth.php';
