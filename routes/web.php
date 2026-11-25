<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\CompanyListImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Gestionnaire\CompanyController as GestionnaireCompanyController;
use App\Http\Controllers\Gestionnaire\CompanyEmployeeController as GestionnaireCompanyEmployeeController;
use App\Http\Controllers\Gestionnaire\CompanyEmployeeTrainingController as GestionnaireCompanyEmployeeTrainingController;
use App\Http\Controllers\Gestionnaire\CompanyMachineryController as GestionnaireCompanyMachineryController;
use App\Http\Controllers\Gestionnaire\CompanyToolingController as GestionnaireCompanyToolingController;
use App\Http\Controllers\Gestionnaire\DelegatedDossierController;
use App\Http\Controllers\Gestionnaire\ManagerController;
use App\Http\Controllers\Gestionnaire\PrestataireController as GestionnairePrestataireController;
use App\Http\Controllers\Gestionnaire\RequiredDocumentsGuideController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Prestataire\CompanyController as PrestataireCompanyController;
use App\Http\Controllers\Prestataire\CompanyEmployeeController as PrestataireCompanyEmployeeController;
use App\Http\Controllers\Prestataire\CompanyEmployeeTrainingController as PrestataireCompanyEmployeeTrainingController;
use App\Http\Controllers\Prestataire\CompanyMachineryController as PrestataireCompanyMachineryController;
use App\Http\Controllers\Prestataire\CompanyToolingController as PrestataireCompanyToolingController;
use App\Http\Controllers\Prestataire\PasswordController;
use App\Http\Controllers\Prestataire\RequiredDocumentsGuideController as PrestataireRequiredDocumentsGuideController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ResponsableSecurite\DocumentController as ResponsableSecuriteDocumentController;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsurePrestatairePasswordChange;
use App\Http\Middleware\ShareUnreadNotificationCount;
use App\Models\CompanyEmployeeDocument;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('throttle:login')
    ->name('login.store');
Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
    ->name('password.request');
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware('throttle:password-reset')
    ->name('password.email');
Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
    ->name('password.reset');
Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware('throttle:password-reset')
    ->name('password.update');

Route::middleware([
    'auth',
    EnsureActiveUser::class,
    EnsurePrestatairePasswordChange::class,
    ShareUnreadNotificationCount::class,
])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::middleware('role:prestataire,gestionnaire,responsable_securite')->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
            ->name('notifications.read-all');
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
            ->name('notifications.read');
    });

    Route::prefix('prestataire')->name('prestataire.')->middleware('role:prestataire')->group(function () {
        Route::get('/mot-de-passe', [PasswordController::class, 'edit'])->name('password.edit');
        Route::put('/mot-de-passe', [PasswordController::class, 'update'])->name('password.update');
        Route::get('/profil', [ProfileController::class, 'show'])->name('profile');
        Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profil/mot-de-passe', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::get('/dashboard', [DashboardController::class, 'prestataire'])->name('dashboard');
        Route::get('/guide-documents-requis', [PrestataireRequiredDocumentsGuideController::class, 'download'])
            ->name('guides.required-documents.download');
        Route::get('/entreprises', [PrestataireCompanyController::class, 'index'])->name('companies.index');
        Route::get('/companies', [PrestataireCompanyController::class, 'index'])->name('companies.index.alt');
        Route::get('/entreprises/create', [PrestataireCompanyController::class, 'create'])->name('companies.create');
        Route::post('/entreprises', [PrestataireCompanyController::class, 'store'])->name('companies.store');
        Route::get('/entreprises/{company}', [PrestataireCompanyController::class, 'show'])->whereNumber('company')->name('companies.show');
        Route::get('/entreprises/{company}/edit', [PrestataireCompanyController::class, 'edit'])->whereNumber('company')->name('companies.edit');
        Route::put('/entreprises/{company}', [PrestataireCompanyController::class, 'update'])->whereNumber('company')->name('companies.update');
        Route::post('/entreprises/{company}/submit', [PrestataireCompanyController::class, 'submit'])->whereNumber('company')->name('companies.submit');
        Route::post('/entreprises/{company}/documents/{documentType}', [PrestataireCompanyController::class, 'storeDocument'])->whereNumber('company')->name('companies.documents.store');
        Route::get('/entreprises/{company}/documents/{document}/import-preview', [CompanyListImportController::class, 'providerPreview'])
            ->whereNumber(['company', 'document'])
            ->name('companies.documents.import-preview');
        Route::post('/entreprises/{company}/documents/{document}/import-confirm', [CompanyListImportController::class, 'providerConfirm'])
            ->whereNumber(['company', 'document'])
            ->name('companies.documents.import-confirm');
        Route::get('/entreprises/{company}/documents/{document}/versions/{version}/preview', [PrestataireCompanyController::class, 'previewVersion'])
            ->whereNumber(['company', 'document', 'version'])
            ->name('companies.documents.versions.preview');
        Route::get('/entreprises/{company}/documents/{document}/versions/{version}/download', [PrestataireCompanyController::class, 'downloadVersion'])
            ->whereNumber(['company', 'document', 'version'])
            ->name('companies.documents.versions.download');
        Route::get('/entreprises/{company}/documents/{document}/preview', [PrestataireCompanyController::class, 'previewDocument'])
            ->whereNumber(['company', 'document'])
            ->name('companies.documents.preview');
        Route::get('/entreprises/{company}/documents/{document}/download', [PrestataireCompanyController::class, 'downloadDocument'])->whereNumber(['company', 'document'])->name('companies.documents.download');
        Route::get('/entreprises/{company}/documents/{document}/imported-data', [CompanyListImportController::class, 'providerData'])
            ->whereNumber(['company', 'document'])
            ->name('companies.documents.imported-data');
        Route::delete('/entreprises/{company}/documents/{document}', [PrestataireCompanyController::class, 'removeDocument'])
            ->whereNumber(['company', 'document'])
            ->name('companies.documents.remove');
        Route::get('/mon-equipe', [PrestataireCompanyEmployeeController::class, 'index'])->name('employees.index');
        Route::get('/mon-equipe/create', [PrestataireCompanyEmployeeController::class, 'create'])->name('employees.create');
        Route::post('/mon-equipe', [PrestataireCompanyEmployeeController::class, 'store'])->name('employees.store');
        Route::get('/mon-equipe/{employee}/photo', [PrestataireCompanyEmployeeController::class, 'photo'])
            ->whereNumber('employee')
            ->name('employees.photo');
        Route::post('/mon-equipe/{employee}/submit', [PrestataireCompanyEmployeeController::class, 'submit'])->whereNumber('employee')->name('employees.submit');
        Route::post('/mon-equipe/{employee}/formations', [PrestataireCompanyEmployeeTrainingController::class, 'store'])
            ->whereNumber('employee')
            ->name('employees.trainings.store');
        Route::put('/mon-equipe/{employee}/formations/{training}', [PrestataireCompanyEmployeeTrainingController::class, 'update'])
            ->whereNumber(['employee', 'training'])
            ->scopeBindings()
            ->name('employees.trainings.update');
        Route::delete('/mon-equipe/{employee}/formations/{training}', [PrestataireCompanyEmployeeTrainingController::class, 'destroy'])
            ->whereNumber(['employee', 'training'])
            ->scopeBindings()
            ->name('employees.trainings.destroy');
        Route::get('/mon-equipe/{employee}/formations/{training}/justificatif', [PrestataireCompanyEmployeeTrainingController::class, 'downloadCertificate'])
            ->whereNumber(['employee', 'training'])
            ->scopeBindings()
            ->name('employees.trainings.download');
        Route::get('/mon-equipe/{employee}/formations/{training}/justificatif/preview', [PrestataireCompanyEmployeeTrainingController::class, 'previewCertificate'])
            ->whereNumber(['employee', 'training'])
            ->scopeBindings()
            ->name('employees.trainings.preview');
        Route::get('/mon-equipe/{employee}/formations/{training}/versions/{version}/download', [PrestataireCompanyEmployeeTrainingController::class, 'downloadVersion'])
            ->whereNumber(['employee', 'training', 'version'])
            ->scopeBindings()
            ->name('employees.trainings.versions.download');
        Route::get('/mon-equipe/{employee}/formations/{training}/versions/{version}/preview', [PrestataireCompanyEmployeeTrainingController::class, 'previewVersion'])
            ->whereNumber(['employee', 'training', 'version'])
            ->scopeBindings()
            ->name('employees.trainings.versions.preview');
        Route::post('/mon-equipe/{employee}/documents/{type}', [PrestataireCompanyEmployeeController::class, 'storeDocument'])
            ->whereNumber('employee')
            ->whereIn('type', CompanyEmployeeDocument::allTypes())
            ->name('employees.documents.store');
        Route::get('/mon-equipe/{employee}/documents/{document}/versions/{version}/preview', [PrestataireCompanyEmployeeController::class, 'previewVersion'])
            ->whereNumber(['employee', 'document', 'version'])
            ->name('employees.documents.versions.preview');
        Route::get('/mon-equipe/{employee}/documents/{document}/versions/{version}/download', [PrestataireCompanyEmployeeController::class, 'downloadVersion'])
            ->whereNumber(['employee', 'document', 'version'])
            ->name('employees.documents.versions.download');
        Route::get('/mon-equipe/{employee}/documents/{document}/preview', [PrestataireCompanyEmployeeController::class, 'previewDocument'])
            ->whereNumber(['employee', 'document'])
            ->name('employees.documents.preview');
        Route::get('/mon-equipe/{employee}/documents/{document}/download', [PrestataireCompanyEmployeeController::class, 'downloadDocument'])
            ->whereNumber(['employee', 'document'])
            ->name('employees.documents.download');
        Route::delete('/mon-equipe/{employee}/documents/{document}', [PrestataireCompanyEmployeeController::class, 'removeDocument'])
            ->whereNumber(['employee', 'document'])
            ->name('employees.documents.remove');
        Route::get('/mon-equipe/{employee}/edit', [PrestataireCompanyEmployeeController::class, 'edit'])
            ->whereNumber('employee')
            ->name('employees.edit');
        Route::put('/mon-equipe/{employee}', [PrestataireCompanyEmployeeController::class, 'update'])
            ->whereNumber('employee')
            ->name('employees.update');
        Route::get('/mon-equipe/{employee}', [PrestataireCompanyEmployeeController::class, 'show'])
            ->whereNumber('employee')
            ->name('employees.show');
        Route::get('/mes-engins', [PrestataireCompanyMachineryController::class, 'index'])->name('machinery.index');
        Route::get('/mes-engins/create', [PrestataireCompanyMachineryController::class, 'create'])->name('machinery.create');
        Route::post('/mes-engins', [PrestataireCompanyMachineryController::class, 'store'])->name('machinery.store');
        Route::get('/mes-engins/{machinery}/photo', [PrestataireCompanyMachineryController::class, 'photo'])
            ->whereNumber('machinery')
            ->name('machinery.photo');
        Route::post('/mes-engins/{machinery}/submit', [PrestataireCompanyMachineryController::class, 'submit'])
            ->whereNumber('machinery')
            ->name('machinery.submit');
        Route::post('/mes-engins/{machinery}/documents/{type}', [PrestataireCompanyMachineryController::class, 'storeDocument'])
            ->whereNumber('machinery')
            ->whereIn('type', ['registration_card', 'insurance', 'regulatory_certificate', 'technical_inspection'])
            ->name('machinery.documents.store');
        Route::get('/mes-engins/{machinery}/documents/{document}/versions/{version}/preview', [PrestataireCompanyMachineryController::class, 'previewVersion'])
            ->whereNumber(['machinery', 'document', 'version'])
            ->name('machinery.documents.versions.preview');
        Route::get('/mes-engins/{machinery}/documents/{document}/versions/{version}/download', [PrestataireCompanyMachineryController::class, 'downloadVersion'])
            ->whereNumber(['machinery', 'document', 'version'])
            ->name('machinery.documents.versions.download');
        Route::get('/mes-engins/{machinery}/documents/{document}/preview', [PrestataireCompanyMachineryController::class, 'previewDocument'])
            ->whereNumber(['machinery', 'document'])
            ->name('machinery.documents.preview');
        Route::get('/mes-engins/{machinery}/documents/{document}/download', [PrestataireCompanyMachineryController::class, 'downloadDocument'])
            ->whereNumber(['machinery', 'document'])
            ->name('machinery.documents.download');
        Route::delete('/mes-engins/{machinery}/documents/{document}', [PrestataireCompanyMachineryController::class, 'removeDocument'])
            ->whereNumber(['machinery', 'document'])
            ->name('machinery.documents.remove');
        Route::get('/mes-engins/{machinery}/edit', [PrestataireCompanyMachineryController::class, 'edit'])
            ->whereNumber('machinery')
            ->name('machinery.edit');
        Route::put('/mes-engins/{machinery}', [PrestataireCompanyMachineryController::class, 'update'])
            ->whereNumber('machinery')
            ->name('machinery.update');
        Route::get('/mes-engins/{machinery}', [PrestataireCompanyMachineryController::class, 'show'])
            ->whereNumber('machinery')
            ->name('machinery.show');
        Route::get('/mes-outillages', [PrestataireCompanyToolingController::class, 'index'])->name('toolings.index');
        Route::get('/mes-outillages/create', [PrestataireCompanyToolingController::class, 'create'])->name('toolings.create');
        Route::post('/mes-outillages', [PrestataireCompanyToolingController::class, 'store'])->name('toolings.store');
        Route::get('/mes-outillages/{tooling}/photo', [PrestataireCompanyToolingController::class, 'photo'])
            ->whereNumber('tooling')
            ->name('toolings.photo');
        Route::post('/mes-outillages/{tooling}/submit', [PrestataireCompanyToolingController::class, 'submit'])
            ->whereNumber('tooling')
            ->name('toolings.submit');
        Route::post('/mes-outillages/{tooling}/documents/custom', [PrestataireCompanyToolingController::class, 'storeCustomDocument'])
            ->whereNumber('tooling')
            ->name('toolings.documents.custom.store');
        Route::post('/mes-outillages/{tooling}/documents/{type}', [PrestataireCompanyToolingController::class, 'storeDocument'])
            ->whereNumber('tooling')
            ->whereIn('type', ['technical_sheet', 'acquisition_proof', 'conformity_certificate', 'inspection_certificate'])
            ->name('toolings.documents.store');
        Route::put('/mes-outillages/{tooling}/documents/{document}/replace', [PrestataireCompanyToolingController::class, 'replaceDocument'])
            ->whereNumber(['tooling', 'document'])
            ->name('toolings.documents.replace');
        Route::get('/mes-outillages/{tooling}/documents/{document}/versions/{version}/preview', [PrestataireCompanyToolingController::class, 'previewVersion'])
            ->whereNumber(['tooling', 'document', 'version'])
            ->name('toolings.documents.versions.preview');
        Route::get('/mes-outillages/{tooling}/documents/{document}/versions/{version}/download', [PrestataireCompanyToolingController::class, 'downloadVersion'])
            ->whereNumber(['tooling', 'document', 'version'])
            ->name('toolings.documents.versions.download');
        Route::get('/mes-outillages/{tooling}/documents/{document}/preview', [PrestataireCompanyToolingController::class, 'previewDocument'])
            ->whereNumber(['tooling', 'document'])
            ->name('toolings.documents.preview');
        Route::get('/mes-outillages/{tooling}/documents/{document}/download', [PrestataireCompanyToolingController::class, 'downloadDocument'])
            ->whereNumber(['tooling', 'document'])
            ->name('toolings.documents.download');
        Route::delete('/mes-outillages/{tooling}/documents/{document}', [PrestataireCompanyToolingController::class, 'removeDocument'])
            ->whereNumber(['tooling', 'document'])
            ->name('toolings.documents.remove');
        Route::get('/mes-outillages/{tooling}/edit', [PrestataireCompanyToolingController::class, 'edit'])
            ->whereNumber('tooling')
            ->name('toolings.edit');
        Route::put('/mes-outillages/{tooling}', [PrestataireCompanyToolingController::class, 'update'])
            ->whereNumber('tooling')
            ->name('toolings.update');
        Route::get('/mes-outillages/{tooling}', [PrestataireCompanyToolingController::class, 'show'])
            ->whereNumber('tooling')
            ->name('toolings.show');
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    });

    Route::prefix('gestionnaire')->name('gestionnaire.')->middleware('role:gestionnaire')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'gestionnaire'])->name('dashboard');
        Route::get('/mot-de-passe', [PasswordController::class, 'edit'])->name('password.edit');
        Route::put('/mot-de-passe', [PasswordController::class, 'update'])->name('password.update');
        Route::get('/profil', [ProfileController::class, 'show'])->name('profile');
        Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profil/mot-de-passe', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::get('/guide-documents-requis', [RequiredDocumentsGuideController::class, 'index'])
            ->middleware('manager.permission:prestataires.view')
            ->name('guides.required-documents.index');
        Route::post('/guide-documents-requis', [RequiredDocumentsGuideController::class, 'store'])
            ->middleware('primary.manager')
            ->name('guides.required-documents.store');
        Route::delete('/guide-documents-requis', [RequiredDocumentsGuideController::class, 'destroy'])
            ->middleware(['manager.permission:prestataires.view', 'primary.manager'])
            ->name('guides.required-documents.destroy');
        Route::get('/guide-documents-requis/fichier', [RequiredDocumentsGuideController::class, 'downloadForManager'])
            ->middleware('manager.permission:prestataires.view')
            ->name('guides.required-documents.download');
        Route::post('/guide-documents-requis/email', [RequiredDocumentsGuideController::class, 'sendToSelectedPrestataire'])
            ->middleware('manager.permission:prestataires.view')
            ->name('guides.required-documents.send');
        Route::resource('prestataires', GestionnairePrestataireController::class)
            ->except('destroy')
            ->middlewareFor(['index', 'show'], 'manager.permission:prestataires.view')
            ->middlewareFor(['create', 'store'], 'manager.permission:prestataires.create')
            ->middlewareFor(['edit', 'update'], 'manager.permission:prestataires.update');
        Route::get('/prestataires/{prestataire}/guide.docx', [RequiredDocumentsGuideController::class, 'downloadForPrestataire'])
            ->whereNumber('prestataire')
            ->middleware('manager.permission:prestataires.view')
            ->name('prestataires.guide.download');
        Route::post('/prestataires/{prestataire}/guide/email', [RequiredDocumentsGuideController::class, 'sendToPrestataire'])
            ->whereNumber('prestataire')
            ->middleware('manager.permission:prestataires.view')
            ->name('prestataires.guide.send');
        Route::patch('/prestataires/{prestataire}/status', [GestionnairePrestataireController::class, 'toggleStatus'])
            ->whereNumber('prestataire')
            ->middleware('manager.permission:prestataires.toggle_status')
            ->name('prestataires.toggle-status');
        Route::get('/entreprises', [GestionnaireCompanyController::class, 'index'])
            ->middleware('manager.permission:entreprises.view')
            ->name('companies.index');
        Route::get('/companies', [GestionnaireCompanyController::class, 'index'])
            ->middleware('manager.permission:entreprises.view')
            ->name('companies.index.alt');
        Route::get('/entreprises/{company}', [GestionnaireCompanyController::class, 'show'])
            ->whereNumber('company')
            ->middleware('manager.permission:entreprises.view')
            ->name('companies.show');
        Route::put('/entreprises/{company}', [GestionnaireCompanyController::class, 'update'])
            ->whereNumber('company')
            ->middleware('manager.permission:entreprises.edit')
            ->name('companies.update');
        Route::get('/gestion-equipes', [GestionnaireCompanyEmployeeController::class, 'index'])
            ->middleware('manager.permission:employees.view')
            ->name('employees.index');
        Route::get('/gestion-equipes/{employee}', [GestionnaireCompanyEmployeeController::class, 'show'])
            ->whereNumber('employee')
            ->middleware('manager.permission:employees.view')
            ->name('employees.show');
        Route::get('/gestion-equipes/{employee}/photo', [GestionnaireCompanyEmployeeController::class, 'photo'])
            ->whereNumber('employee')
            ->middleware('manager.permission:employees.view')
            ->name('employees.photo');
        Route::get('/gestion-equipes/{employee}/formations/{training}/justificatif', [GestionnaireCompanyEmployeeTrainingController::class, 'downloadCertificate'])
            ->whereNumber(['employee', 'training'])
            ->scopeBindings()
            ->middleware('manager.permission:employees.download')
            ->name('employees.trainings.download');
        Route::get('/gestion-equipes/{employee}/formations/{training}/justificatif/preview', [GestionnaireCompanyEmployeeTrainingController::class, 'previewCertificate'])
            ->whereNumber(['employee', 'training'])
            ->scopeBindings()
            ->middleware('manager.permission:employees.download')
            ->name('employees.trainings.preview');
        Route::get('/gestion-equipes/{employee}/formations/{training}/versions/{version}/download', [GestionnaireCompanyEmployeeTrainingController::class, 'downloadVersion'])
            ->whereNumber(['employee', 'training', 'version'])
            ->scopeBindings()
            ->middleware('manager.permission:employees.download')
            ->name('employees.trainings.versions.download');
        Route::get('/gestion-equipes/{employee}/formations/{training}/versions/{version}/preview', [GestionnaireCompanyEmployeeTrainingController::class, 'previewVersion'])
            ->whereNumber(['employee', 'training', 'version'])
            ->scopeBindings()
            ->middleware('manager.permission:employees.download')
            ->name('employees.trainings.versions.preview');
        Route::put('/gestion-equipes/{employee}', [GestionnaireCompanyEmployeeController::class, 'update'])
            ->whereNumber('employee')
            ->middleware('manager.permission:employees.edit')
            ->name('employees.update');
        Route::post('/gestion-equipes/{employee}/approve', [GestionnaireCompanyEmployeeController::class, 'approve'])
            ->whereNumber('employee')
            ->middleware('manager.permission:employees.validate')
            ->name('employees.approve');
        Route::post('/gestion-equipes/{employee}/reject', [GestionnaireCompanyEmployeeController::class, 'reject'])
            ->whereNumber('employee')
            ->middleware('manager.permission:employees.reject')
            ->name('employees.reject');
        Route::post('/gestion-equipes/{employee}/documents/{document}/approve', [GestionnaireCompanyEmployeeController::class, 'approveDocument'])
            ->whereNumber(['employee', 'document'])
            ->middleware('manager.permission:employees.validate')
            ->name('employees.documents.approve');
        Route::post('/gestion-equipes/{employee}/documents/approve-selected', [GestionnaireCompanyEmployeeController::class, 'approveSelectedDocuments'])
            ->whereNumber('employee')
            ->middleware('manager.permission:employees.validate')
            ->name('employees.documents.approve-selected');
        Route::post('/gestion-equipes/{employee}/documents/{document}/reject', [GestionnaireCompanyEmployeeController::class, 'rejectDocument'])
            ->whereNumber(['employee', 'document'])
            ->middleware('manager.permission:employees.reject')
            ->name('employees.documents.reject');
        Route::get('/gestion-equipes/{employee}/documents/{document}/versions/{version}/preview', [GestionnaireCompanyEmployeeController::class, 'previewVersion'])
            ->whereNumber(['employee', 'document', 'version'])
            ->middleware('manager.permission:employees.download')
            ->name('employees.documents.versions.preview');
        Route::get('/gestion-equipes/{employee}/documents/{document}/versions/{version}/download', [GestionnaireCompanyEmployeeController::class, 'downloadVersion'])
            ->whereNumber(['employee', 'document', 'version'])
            ->middleware('manager.permission:employees.download')
            ->name('employees.documents.versions.download');
        Route::get('/gestion-equipes/{employee}/documents/{document}/preview', [GestionnaireCompanyEmployeeController::class, 'previewDocument'])
            ->whereNumber(['employee', 'document'])
            ->middleware('manager.permission:employees.download')
            ->name('employees.documents.preview');
        Route::get('/gestion-equipes/{employee}/documents/{document}/download', [GestionnaireCompanyEmployeeController::class, 'downloadDocument'])
            ->whereNumber(['employee', 'document'])
            ->middleware('manager.permission:employees.download')
            ->name('employees.documents.download');
        Route::get('/gestion-engins', [GestionnaireCompanyMachineryController::class, 'index'])
            ->middleware('manager.permission:machinery.view')
            ->name('machinery.index');
        Route::get('/gestion-engins/{machinery}', [GestionnaireCompanyMachineryController::class, 'show'])
            ->whereNumber('machinery')
            ->middleware('manager.permission:machinery.view')
            ->name('machinery.show');
        Route::get('/gestion-engins/{machinery}/photo', [GestionnaireCompanyMachineryController::class, 'photo'])
            ->whereNumber('machinery')
            ->middleware('manager.permission:machinery.view')
            ->name('machinery.photo');
        Route::put('/gestion-engins/{machinery}', [GestionnaireCompanyMachineryController::class, 'update'])
            ->whereNumber('machinery')
            ->middleware(['manager.permission:machinery.view', 'manager.permission:machinery.update'])
            ->name('machinery.update');
        Route::post('/gestion-engins/{machinery}/approve', [GestionnaireCompanyMachineryController::class, 'approve'])
            ->whereNumber('machinery')
            ->middleware(['manager.permission:machinery.view', 'manager.permission:machinery.approve'])
            ->name('machinery.approve');
        Route::post('/gestion-engins/{machinery}/reject', [GestionnaireCompanyMachineryController::class, 'reject'])
            ->whereNumber('machinery')
            ->middleware(['manager.permission:machinery.view', 'manager.permission:machinery.reject'])
            ->name('machinery.reject');
        Route::post('/gestion-engins/{machinery}/documents/{document}/approve', [GestionnaireCompanyMachineryController::class, 'approveDocument'])
            ->whereNumber(['machinery', 'document'])
            ->middleware(['manager.permission:machinery.view', 'manager.permission:machinery.approve'])
            ->name('machinery.documents.approve');
        Route::post('/gestion-engins/{machinery}/documents/approve-selected', [GestionnaireCompanyMachineryController::class, 'approveSelectedDocuments'])
            ->whereNumber('machinery')
            ->middleware(['manager.permission:machinery.view', 'manager.permission:machinery.approve'])
            ->name('machinery.documents.approve-selected');
        Route::post('/gestion-engins/{machinery}/documents/{document}/reject', [GestionnaireCompanyMachineryController::class, 'rejectDocument'])
            ->whereNumber(['machinery', 'document'])
            ->middleware(['manager.permission:machinery.view', 'manager.permission:machinery.reject'])
            ->name('machinery.documents.reject');
        Route::get('/gestion-engins/{machinery}/documents/{document}/versions/{version}/preview', [GestionnaireCompanyMachineryController::class, 'previewVersion'])
            ->whereNumber(['machinery', 'document', 'version'])
            ->middleware(['manager.permission:machinery.view', 'manager.permission:machinery.download'])
            ->name('machinery.documents.versions.preview');
        Route::get('/gestion-engins/{machinery}/documents/{document}/versions/{version}/download', [GestionnaireCompanyMachineryController::class, 'downloadVersion'])
            ->whereNumber(['machinery', 'document', 'version'])
            ->middleware(['manager.permission:machinery.view', 'manager.permission:machinery.download'])
            ->name('machinery.documents.versions.download');
        Route::get('/gestion-engins/{machinery}/documents/{document}/preview', [GestionnaireCompanyMachineryController::class, 'previewDocument'])
            ->whereNumber(['machinery', 'document'])
            ->middleware(['manager.permission:machinery.view', 'manager.permission:machinery.download'])
            ->name('machinery.documents.preview');
        Route::get('/gestion-engins/{machinery}/documents/{document}/download', [GestionnaireCompanyMachineryController::class, 'downloadDocument'])
            ->whereNumber(['machinery', 'document'])
            ->middleware(['manager.permission:machinery.view', 'manager.permission:machinery.download'])
            ->name('machinery.documents.download');
        Route::get('/gestion-outillages', [GestionnaireCompanyToolingController::class, 'index'])
            ->middleware('manager.permission:toolings.view')
            ->name('toolings.index');
        Route::get('/gestion-outillages/{tooling}/photo', [GestionnaireCompanyToolingController::class, 'photo'])
            ->whereNumber('tooling')
            ->middleware('manager.permission:toolings.view')
            ->name('toolings.photo');
        Route::get('/gestion-outillages/{tooling}', [GestionnaireCompanyToolingController::class, 'show'])
            ->whereNumber('tooling')
            ->middleware('manager.permission:toolings.view')
            ->name('toolings.show');
        Route::put('/gestion-outillages/{tooling}', [GestionnaireCompanyToolingController::class, 'update'])
            ->whereNumber('tooling')
            ->middleware(['manager.permission:toolings.view', 'manager.permission:toolings.update'])
            ->name('toolings.update');
        Route::post('/gestion-outillages/{tooling}/approve', [GestionnaireCompanyToolingController::class, 'approve'])
            ->whereNumber('tooling')
            ->middleware(['manager.permission:toolings.view', 'manager.permission:toolings.approve'])
            ->name('toolings.approve');
        Route::post('/gestion-outillages/{tooling}/reject', [GestionnaireCompanyToolingController::class, 'reject'])
            ->whereNumber('tooling')
            ->middleware(['manager.permission:toolings.view', 'manager.permission:toolings.reject'])
            ->name('toolings.reject');
        Route::post('/gestion-outillages/{tooling}/documents/{document}/approve', [GestionnaireCompanyToolingController::class, 'approveDocument'])
            ->whereNumber(['tooling', 'document'])
            ->middleware(['manager.permission:toolings.view', 'manager.permission:toolings.approve'])
            ->name('toolings.documents.approve');
        Route::post('/gestion-outillages/{tooling}/documents/approve-selected', [GestionnaireCompanyToolingController::class, 'approveSelectedDocuments'])
            ->whereNumber('tooling')
            ->middleware(['manager.permission:toolings.view', 'manager.permission:toolings.approve'])
            ->name('toolings.documents.approve-selected');
        Route::post('/gestion-outillages/{tooling}/documents/{document}/reject', [GestionnaireCompanyToolingController::class, 'rejectDocument'])
            ->whereNumber(['tooling', 'document'])
            ->middleware(['manager.permission:toolings.view', 'manager.permission:toolings.reject'])
            ->name('toolings.documents.reject');
        Route::get('/gestion-outillages/{tooling}/documents/{document}/versions/{version}/preview', [GestionnaireCompanyToolingController::class, 'previewVersion'])
            ->whereNumber(['tooling', 'document', 'version'])
            ->middleware(['manager.permission:toolings.view', 'manager.permission:toolings.download'])
            ->name('toolings.documents.versions.preview');
        Route::get('/gestion-outillages/{tooling}/documents/{document}/versions/{version}/download', [GestionnaireCompanyToolingController::class, 'downloadVersion'])
            ->whereNumber(['tooling', 'document', 'version'])
            ->middleware(['manager.permission:toolings.view', 'manager.permission:toolings.download'])
            ->name('toolings.documents.versions.download');
        Route::get('/gestion-outillages/{tooling}/documents/{document}/preview', [GestionnaireCompanyToolingController::class, 'previewDocument'])
            ->whereNumber(['tooling', 'document'])
            ->middleware(['manager.permission:toolings.view', 'manager.permission:toolings.download'])
            ->name('toolings.documents.preview');
        Route::get('/gestion-outillages/{tooling}/documents/{document}/download', [GestionnaireCompanyToolingController::class, 'downloadDocument'])
            ->whereNumber(['tooling', 'document'])
            ->middleware(['manager.permission:toolings.view', 'manager.permission:toolings.download'])
            ->name('toolings.documents.download');
        Route::post('/entreprises/{company}/validate', [GestionnaireCompanyController::class, 'approve'])
            ->whereNumber('company')
            ->middleware('manager.permission:entreprises.validate')
            ->name('companies.approve');
        Route::post('/entreprises/{company}/reject', [GestionnaireCompanyController::class, 'reject'])
            ->whereNumber('company')
            ->middleware('manager.permission:entreprises.reject')
            ->name('companies.reject');
        Route::post('/entreprises/{company}/documents/{document}/approve', [GestionnaireCompanyController::class, 'approveDocument'])
            ->whereNumber(['company', 'document'])
            ->middleware('manager.permission:entreprises.validate')
            ->name('companies.documents.approve');
        Route::post('/entreprises/{company}/documents/approve-selected', [GestionnaireCompanyController::class, 'approveSelectedDocuments'])
            ->whereNumber('company')
            ->middleware('manager.permission:entreprises.validate')
            ->name('companies.documents.approve-selected');
        Route::post('/entreprises/{company}/documents/{document}/reject', [GestionnaireCompanyController::class, 'rejectDocument'])
            ->whereNumber(['company', 'document'])
            ->middleware('manager.permission:entreprises.reject')
            ->name('companies.documents.reject');
        Route::get('/entreprises/{company}/documents/{document}/versions/{version}/preview', [GestionnaireCompanyController::class, 'previewVersion'])
            ->whereNumber(['company', 'document', 'version'])
            ->middleware('manager.permission:entreprises.download')
            ->name('companies.documents.versions.preview');
        Route::get('/entreprises/{company}/documents/{document}/versions/{version}/download', [GestionnaireCompanyController::class, 'downloadVersion'])
            ->whereNumber(['company', 'document', 'version'])
            ->middleware('manager.permission:entreprises.download')
            ->name('companies.documents.versions.download');
        Route::get('/entreprises/{company}/documents/{document}/preview', [GestionnaireCompanyController::class, 'previewDocument'])
            ->whereNumber(['company', 'document'])
            ->middleware('manager.permission:entreprises.download')
            ->name('companies.documents.preview');
        Route::get('/entreprises/{company}/documents/{document}/download', [GestionnaireCompanyController::class, 'downloadDocument'])
            ->whereNumber(['company', 'document'])
            ->middleware('manager.permission:entreprises.download')
            ->name('companies.documents.download');
        Route::get('/entreprises/{company}/documents/{document}/imported-data', [GestionnaireCompanyController::class, 'importedDocumentData'])
            ->whereNumber(['company', 'document'])
            ->middleware('manager.permission:entreprises.download')
            ->name('companies.documents.imported-data');
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

        Route::prefix('dossiers')->name('delegated.')->middleware('primary.manager')->group(function () {
            Route::get('/', [DelegatedDossierController::class, 'index'])->name('index');
            Route::get('/{prestataire}', [DelegatedDossierController::class, 'show'])
                ->whereNumber('prestataire')
                ->name('show');
            Route::get('/{prestataire}/entreprise/{company}', [DelegatedDossierController::class, 'showCompany'])
                ->whereNumber(['prestataire', 'company'])
                ->name('companies.show');
            Route::get('/{prestataire}/entreprise/create', [DelegatedDossierController::class, 'createCompany'])
                ->whereNumber('prestataire')
                ->name('companies.create');
            Route::post('/{prestataire}/entreprise', [DelegatedDossierController::class, 'storeCompany'])
                ->whereNumber('prestataire')
                ->name('companies.store');
            Route::get('/{prestataire}/entreprise/{company}/edit', [DelegatedDossierController::class, 'editCompany'])
                ->whereNumber(['prestataire', 'company'])
                ->name('companies.edit');
            Route::put('/{prestataire}/entreprise/{company}', [DelegatedDossierController::class, 'updateCompany'])
                ->whereNumber(['prestataire', 'company'])
                ->name('companies.update');
            Route::post('/{prestataire}/entreprise/{company}/approve', [GestionnaireCompanyController::class, 'approveDelegated'])
                ->whereNumber(['prestataire', 'company'])
                ->middleware('manager.permission:entreprises.validate')
                ->name('companies.approve');
            Route::post('/{prestataire}/entreprise/{company}/documents/approve-selected', [GestionnaireCompanyController::class, 'approveSelectedDelegatedDocuments'])
                ->whereNumber(['prestataire', 'company'])
                ->middleware('manager.permission:entreprises.validate')
                ->name('companies.documents.approve-selected');
            Route::post('/{prestataire}/entreprise/{company}/documents/{type}', [DelegatedDossierController::class, 'storeCompanyDocument'])
                ->whereNumber(['prestataire', 'company'])
                ->name('companies.documents.store');
            Route::get('/{prestataire}/entreprise/{company}/documents/{document}/imported-data', [DelegatedDossierController::class, 'importedCompanyDocumentData'])
                ->whereNumber(['prestataire', 'company', 'document'])
                ->name('companies.documents.imported-data');
            Route::get('/{prestataire}/entreprise/{company}/documents/{document}/import-preview', [CompanyListImportController::class, 'delegatedPreview'])
                ->whereNumber(['prestataire', 'company', 'document'])
                ->name('companies.documents.import-preview');
            Route::post('/{prestataire}/entreprise/{company}/documents/{document}/import-confirm', [CompanyListImportController::class, 'delegatedConfirm'])
                ->whereNumber(['prestataire', 'company', 'document'])
                ->name('companies.documents.import-confirm');
            Route::post('/{prestataire}/entreprise/{company}/submit', [DelegatedDossierController::class, 'submitCompany'])
                ->whereNumber(['prestataire', 'company'])
                ->name('companies.submit');

            Route::get('/{prestataire}/employes/create', [DelegatedDossierController::class, 'createEmployee'])
                ->whereNumber('prestataire')
                ->name('employees.create');
            Route::post('/{prestataire}/employes', [DelegatedDossierController::class, 'storeEmployee'])
                ->whereNumber('prestataire')
                ->name('employees.store');
            Route::get('/{prestataire}/employes/{employee}/edit', [DelegatedDossierController::class, 'editEmployee'])
                ->whereNumber(['prestataire', 'employee'])
                ->name('employees.edit');
            Route::put('/{prestataire}/employes/{employee}', [DelegatedDossierController::class, 'updateEmployee'])
                ->whereNumber(['prestataire', 'employee'])
                ->name('employees.update');
            Route::post('/{prestataire}/employes/{employee}/documents/{type}', [DelegatedDossierController::class, 'storeEmployeeDocument'])
                ->whereNumber(['prestataire', 'employee'])
                ->name('employees.documents.store');
            Route::post('/{prestataire}/employes/{employee}/submit', [DelegatedDossierController::class, 'submitEmployee'])
                ->whereNumber(['prestataire', 'employee'])
                ->name('employees.submit');

            Route::get('/{prestataire}/engins/create', [DelegatedDossierController::class, 'createMachinery'])
                ->whereNumber('prestataire')
                ->name('machinery.create');
            Route::post('/{prestataire}/engins', [DelegatedDossierController::class, 'storeMachinery'])
                ->whereNumber('prestataire')
                ->name('machinery.store');
            Route::get('/{prestataire}/engins/{machinery}/edit', [DelegatedDossierController::class, 'editMachinery'])
                ->whereNumber(['prestataire', 'machinery'])
                ->name('machinery.edit');
            Route::put('/{prestataire}/engins/{machinery}', [DelegatedDossierController::class, 'updateMachinery'])
                ->whereNumber(['prestataire', 'machinery'])
                ->name('machinery.update');
            Route::post('/{prestataire}/engins/{machinery}/documents/{type}', [DelegatedDossierController::class, 'storeMachineryDocument'])
                ->whereNumber(['prestataire', 'machinery'])
                ->name('machinery.documents.store');
            Route::post('/{prestataire}/engins/{machinery}/submit', [DelegatedDossierController::class, 'submitMachinery'])
                ->whereNumber(['prestataire', 'machinery'])
                ->name('machinery.submit');

            Route::get('/{prestataire}/outillages/create', [DelegatedDossierController::class, 'createTooling'])
                ->whereNumber('prestataire')
                ->name('toolings.create');
            Route::post('/{prestataire}/outillages', [DelegatedDossierController::class, 'storeTooling'])
                ->whereNumber('prestataire')
                ->name('toolings.store');
            Route::get('/{prestataire}/outillages/{tooling}/edit', [DelegatedDossierController::class, 'editTooling'])
                ->whereNumber(['prestataire', 'tooling'])
                ->name('toolings.edit');
            Route::put('/{prestataire}/outillages/{tooling}', [DelegatedDossierController::class, 'updateTooling'])
                ->whereNumber(['prestataire', 'tooling'])
                ->name('toolings.update');
            Route::post('/{prestataire}/outillages/{tooling}/documents/custom', [DelegatedDossierController::class, 'storeCustomToolingDocument'])
                ->whereNumber(['prestataire', 'tooling'])
                ->name('toolings.documents.custom.store');
            Route::post('/{prestataire}/outillages/{tooling}/documents/{type}', [DelegatedDossierController::class, 'storeToolingDocument'])
                ->whereNumber(['prestataire', 'tooling'])
                ->name('toolings.documents.store');
            Route::post('/{prestataire}/outillages/{tooling}/submit', [DelegatedDossierController::class, 'submitTooling'])
                ->whereNumber(['prestataire', 'tooling'])
                ->name('toolings.submit');
        });

        Route::prefix('employes')->name('employes.')->middleware('primary.manager')->group(function () {
            Route::get('/', [ManagerController::class, 'index'])->name('index');
            Route::get('/create', [ManagerController::class, 'create'])->name('create');
            Route::post('/', [ManagerController::class, 'store'])->name('store');
            Route::get('/{manager}', [ManagerController::class, 'show'])->whereNumber('manager')->name('show');
            Route::get('/{manager}/edit', [ManagerController::class, 'edit'])->whereNumber('manager')->name('edit');
            Route::put('/{manager}', [ManagerController::class, 'update'])->whereNumber('manager')->name('update');
            Route::put('/{manager}/permissions', [ManagerController::class, 'updatePermissions'])->whereNumber('manager')->name('permissions.update');
            Route::put('/{manager}/prestataires', [ManagerController::class, 'updateAssignments'])->whereNumber('manager')->name('assignments.update');
            Route::patch('/{manager}/status', [ManagerController::class, 'toggleStatus'])->whereNumber('manager')->name('status');
            Route::post('/{manager}/principal', [ManagerController::class, 'transferPrincipal'])->whereNumber('manager')->name('principal.transfer');
        });
    });

    Route::prefix('responsable-securite')->name('responsable-securite.')->middleware('role:responsable_securite')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'responsableSecurite'])->name('dashboard');
        Route::get('/documents', [ResponsableSecuriteDocumentController::class, 'index'])->name('documents.index');
        Route::get('/documents/{document}/download', [ResponsableSecuriteDocumentController::class, 'download'])
            ->whereNumber('document')
            ->name('documents.download');
        Route::get('/documents/{document}', [ResponsableSecuriteDocumentController::class, 'show'])
            ->whereNumber('document')
            ->name('documents.show');
        Route::get('/documents-valides', fn () => to_route('responsable-securite.documents.index'))
            ->name('documents.validated');
        Route::get('/mot-de-passe', [PasswordController::class, 'edit'])->name('password.edit');
        Route::put('/mot-de-passe', [PasswordController::class, 'update'])->name('password.update');
        Route::get('/profil', [ProfileController::class, 'show'])->name('profile');
        Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profil/mot-de-passe', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    });
});
