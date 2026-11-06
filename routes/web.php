<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Gestionnaire\DocumentController as GestionnaireDocumentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Prestataire\DocumentController;
use App\Http\Controllers\Prestataire\SafetyTeamMemberController as PrestataireSafetyTeamMemberController;
use App\Http\Controllers\ResponsableSecurite\DocumentController as ResponsableSecuriteDocumentController;
use App\Http\Middleware\ShareUnreadNotificationCount;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('throttle:login')
    ->name('login.store');

Route::middleware(['auth', ShareUnreadNotificationCount::class])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::middleware('role:prestataire,gestionnaire,responsable_securite')->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
            ->name('notifications.read-all');
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
            ->name('notifications.read');
    });

    Route::prefix('prestataire')->name('prestataire.')->middleware('role:prestataire')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'prestataire'])->name('dashboard');
        Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create');
        Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('/documents/{document}/edit', [DocumentController::class, 'edit'])
            ->whereNumber('document')
            ->name('documents.edit');
        Route::put('/documents/{document}', [DocumentController::class, 'update'])
            ->whereNumber('document')
            ->name('documents.update');
        Route::get('/documents/{document}/download', [DocumentController::class, 'download'])
            ->whereNumber('document')
            ->name('documents.download');
        Route::get('/documents/{document}', [DocumentController::class, 'show'])
            ->whereNumber('document')
            ->name('documents.show');
        Route::get('/equipe-securite', [PrestataireSafetyTeamMemberController::class, 'index'])
            ->name('safety-team');
        Route::get('/equipe-securite/create', [PrestataireSafetyTeamMemberController::class, 'create'])
            ->name('safety-team.create');
        Route::post('/equipe-securite', [PrestataireSafetyTeamMemberController::class, 'store'])
            ->name('safety-team.store');
        Route::get('/equipe-securite/{member}/edit', [PrestataireSafetyTeamMemberController::class, 'edit'])
            ->whereNumber('member')
            ->name('safety-team.edit');
        Route::put('/equipe-securite/{member}', [PrestataireSafetyTeamMemberController::class, 'update'])
            ->whereNumber('member')
            ->name('safety-team.update');
        Route::delete('/equipe-securite/{member}', [PrestataireSafetyTeamMemberController::class, 'destroy'])
            ->whereNumber('member')
            ->name('safety-team.destroy');
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/profil', [DashboardController::class, 'placeholder'])->name('profile');
    });

    Route::prefix('gestionnaire')->name('gestionnaire.')->middleware('role:gestionnaire')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'gestionnaire'])->name('dashboard');
        Route::get('/documents', [GestionnaireDocumentController::class, 'index'])->name('documents.index');
        Route::get('/documents/en-attente', [GestionnaireDocumentController::class, 'pending'])->name('documents.pending');
        Route::get('/documents/valides', [DashboardController::class, 'placeholder'])->name('documents.validated');
        Route::get('/documents/rejetes', [DashboardController::class, 'placeholder'])->name('documents.rejected');
        Route::get('/documents/{document}/download', [GestionnaireDocumentController::class, 'download'])
            ->whereNumber('document')
            ->name('documents.download');
        Route::post('/documents/{document}/approve', [GestionnaireDocumentController::class, 'approve'])
            ->whereNumber('document')
            ->name('documents.approve');
        Route::post('/documents/{document}/reject', [GestionnaireDocumentController::class, 'reject'])
            ->whereNumber('document')
            ->name('documents.reject');
        Route::get('/documents/{document}', [GestionnaireDocumentController::class, 'show'])
            ->whereNumber('document')
            ->name('documents.show');
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/profil', [DashboardController::class, 'placeholder'])->name('profile');
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
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/profil', [DashboardController::class, 'placeholder'])->name('profile');
    });
});
