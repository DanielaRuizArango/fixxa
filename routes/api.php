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

/*
|--------------------------------------------------------------------------
| Rutas de Cliente
|--------------------------------------------------------------------------
*/
Route::prefix('client')->group(function () {
    Route::post('/register', [ClientAuthController::class, 'register']);
    Route::post('/login', [ClientAuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
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
    Route::post('/login', [TechnicianAuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
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
