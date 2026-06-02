<?php

use App\Http\Controllers\Api\Client\AuthController as ClientAuthController;
use App\Http\Controllers\Api\Client\CaseManagementController;
use App\Http\Controllers\Api\Client\ProfileController as ClientProfileController;
use App\Http\Controllers\Api\Client\RatingController;
use App\Http\Controllers\Api\Client\ServiceCaseController as ClientServiceCaseController;
use App\Http\Controllers\Api\Technician\AuthController as TechnicianAuthController;
use App\Http\Controllers\Api\Technician\CaseResponseController;
use App\Http\Controllers\Api\Technician\ProfileController as TechnicianProfileController;
use App\Http\Controllers\Api\Technician\TechnicianAssetController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas Generales
|--------------------------------------------------------------------------
*/
Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login'])->name('login');
Route::post('/forgot-password', [App\Http\Controllers\Api\AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [App\Http\Controllers\Api\AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->get('/technicians/{id}/profile', [\App\Http\Controllers\Api\PublicTechnicianController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Rutas de Cliente
|--------------------------------------------------------------------------
*/
Route::prefix('client')->group(function () {
    Route::post('/register', [ClientAuthController::class, 'register']);
    Route::post('/login', [ClientAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'role:client'])->group(function () {
        // Perfil del cliente
        Route::get('/me', [ClientProfileController::class, 'show']);
        Route::post('/profile', [ClientProfileController::class, 'update']);

        // Casos de servicio (Cliente)
        Route::prefix('cases')->group(function () {
            Route::get('/', [ClientServiceCaseController::class, 'index']);
            Route::post('/', [ClientServiceCaseController::class, 'store']);
            Route::get('/{id}', [ClientServiceCaseController::class, 'show']);
            Route::put('/{id}', [ClientServiceCaseController::class, 'update']);
            Route::delete('/{id}', [ClientServiceCaseController::class, 'destroy']);
        });

        // Gestión de propuestas y estados
        Route::post('/cases/{caseId}/proposals/{responseId}/accept', [CaseManagementController::class, 'acceptProposal']);
        Route::delete('/cases/{caseId}/proposals/{responseId}/reject', [CaseManagementController::class, 'rejectProposal']);
        Route::patch('/cases/{caseId}/resolve', [CaseManagementController::class, 'resolveCase']);
        Route::patch('/cases/{caseId}/cancel', [CaseManagementController::class, 'cancelCase']);

        // Calificaciones
        Route::get('/ratings', [RatingController::class, 'index']);
        Route::post('/ratings', [RatingController::class, 'store']);

        Route::post('/logout', [ClientAuthController::class, 'logout']);
    });
});

/*
|--------------------------------------------------------------------------
| Rutas de Técnico
|--------------------------------------------------------------------------
*/
Route::prefix('technician')->group(function () {
    Route::post('/register', [TechnicianAuthController::class, 'register']);
    Route::post('/login', [TechnicianAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'role:technician'])->group(function () {
        // Perfil del técnico
        Route::get('/me', [TechnicianProfileController::class, 'show']);
        Route::post('/profile', [TechnicianProfileController::class, 'update']);

        // Respuestas y Casos Disponibles (Técnico)
        Route::get('/cases', [CaseResponseController::class, 'availableCases']);
        Route::get('/cases/{id}', [CaseResponseController::class, 'showCase']);
        Route::post('/responses', [CaseResponseController::class, 'store']);
        Route::put('/responses/{id}', [CaseResponseController::class, 'update']);
        Route::get('/my-responses', [CaseResponseController::class, 'myResponses']);

        // Calificación promedio del técnico
        Route::get('/my-rating', [App\Http\Controllers\Api\Technician\RatingController::class, 'index']);

        // Activos del técnico (Herramientas, Certificaciones, Trabajos)
        Route::get('/assets', [TechnicianAssetController::class, 'index']);
        Route::post('/assets', [TechnicianAssetController::class, 'store']);
        Route::delete('/assets/{id}', [TechnicianAssetController::class, 'destroy']);

        Route::post('/logout', [TechnicianAuthController::class, 'logout']);
    });
});

/*
|--------------------------------------------------------------------------
| Rutas de Admin
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth:sanctum', 'role:super_admin|admin|moderator'])->group(function () {
    // Perfil del admin (todos)
    Route::get('/me', [\App\Http\Controllers\Api\Admin\ProfileController::class, 'show']);
    Route::post('/profile', [\App\Http\Controllers\Api\Admin\ProfileController::class, 'update']);
    Route::get('/dashboard/metrics', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'getMetrics']);
    Route::get('/logs', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'getLogs']);

    // Gestión de otros admins (solo super_admin)
    Route::middleware('role:super_admin')->group(function () {
        Route::apiResource('admins', \App\Http\Controllers\Api\Admin\AdminController::class);
        Route::patch('admins/{id}/block', [\App\Http\Controllers\Api\Admin\AdminController::class, 'block']);
    });

    // Gestión de clientes y técnicos (super_admin, admin y moderator)
    Route::middleware('role:super_admin|admin|moderator')->group(function () {
        Route::apiResource('clients', \App\Http\Controllers\Api\Admin\ClientController::class);
        Route::patch('clients/{id}/block', [\App\Http\Controllers\Api\Admin\ClientController::class, 'block']);
        
        Route::apiResource('technicians', \App\Http\Controllers\Api\Admin\TechnicianController::class);
        Route::patch('technicians/{id}/block', [\App\Http\Controllers\Api\Admin\TechnicianController::class, 'block']);

        // Gestión de Casos
        Route::get('cases', [\App\Http\Controllers\Api\Admin\ServiceCaseController::class, 'index']);
        Route::get('cases/{id}', [\App\Http\Controllers\Api\Admin\ServiceCaseController::class, 'show']);
        Route::patch('cases/{id}/status', [\App\Http\Controllers\Api\Admin\ServiceCaseController::class, 'updateStatus']);

        // Gestión de Calificaciones
        Route::get('ratings', [\App\Http\Controllers\Api\Admin\RatingController::class, 'index']);
        Route::delete('ratings/{id}', [\App\Http\Controllers\Api\Admin\RatingController::class, 'destroy']);

        // Alertas de Sistema
        Route::get('alerts', [\App\Http\Controllers\Api\Admin\SystemAlertController::class, 'index']);

        // Aprobación de Certificaciones de Técnicos
        Route::get('certifications', [\App\Http\Controllers\Api\Admin\CertificationController::class, 'index']);
        Route::get('certifications/{id}', [\App\Http\Controllers\Api\Admin\CertificationController::class, 'show']);
        Route::patch('certifications/{id}/approve', [\App\Http\Controllers\Api\Admin\CertificationController::class, 'approve']);
        Route::patch('certifications/{id}/reject', [\App\Http\Controllers\Api\Admin\CertificationController::class, 'reject']);

        // Aprobación de Cédulas (id_document)
        Route::get('id-documents', [\App\Http\Controllers\Api\Admin\CertificationController::class, 'indexIdDocuments']);
        Route::patch('id-documents/{id}/approve', [\App\Http\Controllers\Api\Admin\CertificationController::class, 'approveIdDocument']);
        Route::patch('id-documents/{id}/reject', [\App\Http\Controllers\Api\Admin\CertificationController::class, 'rejectIdDocument']);
    });
});

 /*
|--------------------------------------------------------------------------
| Rutas de Chat
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:client|technician'])->prefix('chat')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\ChatController::class, 'index']);
    Route::post('/start', [App\Http\Controllers\Api\ChatController::class, 'startConversation']);
    Route::get('/{id}', [App\Http\Controllers\Api\ChatController::class, 'show']);
    Route::post('/{id}/send', [App\Http\Controllers\Api\ChatController::class, 'sendMessage']);
});

/*
|--------------------------------------------------------------------------
| Rutas de Notificaciones
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::post('/mark-as-read', [App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
    Route::patch('/{id}/read', [App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::delete('/{id}', [App\Http\Controllers\Api\NotificationController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Rutas de Broadcasting
|--------------------------------------------------------------------------
*/
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['api', 'auth:sanctum']]);
require __DIR__.'/channels.php';