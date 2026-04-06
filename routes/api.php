<?php

use App\Http\Controllers\Api\Client\AuthController as ClientAuthController;
use App\Http\Controllers\Api\Client\ProfileController as ClientProfileController;
use App\Http\Controllers\Api\Client\ServiceCaseController as ClientServiceCaseController;
use App\Http\Controllers\Api\Technician\AuthController as TechnicianAuthController;
use App\Http\Controllers\Api\Technician\CaseResponseController;
use App\Http\Controllers\Api\Technician\ProfileController as TechnicianProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas Generales
|--------------------------------------------------------------------------
*/
Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);
Route::post('/forgot-password', [App\Http\Controllers\Api\AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [App\Http\Controllers\Api\AuthController::class, 'resetPassword']);

/*
|--------------------------------------------------------------------------
| Rutas de Cliente
|--------------------------------------------------------------------------
*/
Route::prefix('client')->group(function () {
    Route::post('/register', [ClientAuthController::class, 'register']);

    Route::middleware(['auth:sanctum', 'role:client'])->group(function () {
        // Perfil del cliente
        Route::get('/me', [ClientProfileController::class, 'show']);
        Route::post('/profile', [ClientProfileController::class, 'update']);

        // Casos de servicio (Cliente)
        Route::prefix('cases')->group(function () {
            Route::get('/', [ClientServiceCaseController::class, 'index']);
            Route::post('/', [ClientServiceCaseController::class, 'store']);
            Route::get('/{id}', [ClientServiceCaseController::class, 'show']);
        });

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

    Route::middleware(['auth:sanctum', 'role:technician'])->group(function () {
        // Perfil del técnico
        Route::get('/me', [TechnicianProfileController::class, 'show']);
        Route::post('/profile', [TechnicianProfileController::class, 'update']);

        // Respuestas y Casos Disponibles (Técnico)
        Route::get('/cases', [CaseResponseController::class, 'availableCases']);
        Route::get('/cases/{id}', [CaseResponseController::class, 'showCase']);
        Route::post('/responses', [CaseResponseController::class, 'store']);
        Route::get('/responses/mine', [CaseResponseController::class, 'myResponses']);

        Route::post('/logout', [TechnicianAuthController::class, 'logout']);
    });
});

/*
|--------------------------------------------------------------------------
| Rutas de Admin
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // Perfil del admin
    Route::get('/me', [App\Http\Controllers\Admin\ProfileController::class, 'show']);
    Route::post('/profile', [App\Http\Controllers\Admin\ProfileController::class, 'update']);

    Route::apiResource('clients', App\Http\Controllers\Admin\ClientController::class);
    Route::patch('clients/{id}/block', [App\Http\Controllers\Admin\ClientController::class, 'block']);
    
    Route::apiResource('technicians', App\Http\Controllers\Admin\TechnicianController::class);
    Route::patch('technicians/{id}/block', [App\Http\Controllers\Admin\TechnicianController::class, 'block']);
});
