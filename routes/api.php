<?php

use App\Http\Controllers\Api\Client\AuthController as ClientAuthController;
use App\Http\Controllers\Api\Technician\AuthController as TechnicianAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de Cliente
|--------------------------------------------------------------------------
*/
Route::prefix('client')->group(function () {
    Route::post('/register', [ClientAuthController::class, 'register']);
    Route::post('/login',    [ClientAuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me',       fn(Request $r) => $r->user());
        Route::post('/logout',  [ClientAuthController::class, 'logout']);
    });
});

/*
|--------------------------------------------------------------------------
| Rutas de Técnico
|--------------------------------------------------------------------------
*/
Route::prefix('technician')->group(function () {
    Route::post('/register', [TechnicianAuthController::class, 'register']);
    Route::post('/login',    [TechnicianAuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me',       fn(Request $r) => $r->user());
        Route::post('/logout',  [TechnicianAuthController::class, 'logout']);
    });
});
