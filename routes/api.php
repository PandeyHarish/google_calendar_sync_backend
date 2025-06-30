<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\GoogleCalendarController;
use App\Http\Controllers\Api\Auth\GoogleAuthController;
use App\Http\Controllers\Api\Auth\AuthController;

Route::prefix('auth')->group(function () {
    // Standard JWT Auth
    Route::post('login', [AuthController::class, 'login']);

    // Google OAuth
    Route::prefix('google')->group(function () {
        Route::get('redirect', [GoogleAuthController::class, 'redirectToGoogle']);
        Route::get('callback', [GoogleAuthController::class, 'callback']);
        // Route::get('redirect', [GoogleAuthController::class, 'redirect']);
        // Route::get('callback', [GoogleAuthController::class, 'callback']);
    });
});

// Public routes (no auth)
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{event}', [EventController::class, 'show']);
Route::post('/events', [EventController::class, 'store']);

// Protected routes (auth required)
Route::middleware('auth:api')->group(function () {
    Route::put('/events/{id}', [EventController::class, 'update']);
    Route::patch('/events/{id}', [EventController::class, 'update']);
    Route::delete('/events/{id}', [EventController::class, 'destroy']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/refresh', [AuthController::class, 'refresh']);
    Route::post('/auth/google/disconnect', [GoogleAuthController::class, 'disconnect']);
    Route::apiResource('google-calendar', GoogleCalendarController::class);
    Route::post('/google-calendar/push-local', [GoogleCalendarController::class, 'pushLocalEvents']);
    Route::get('/events/debug/google-connection', [EventController::class, 'debugGoogleConnection']);
});