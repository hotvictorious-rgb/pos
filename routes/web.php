<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\BackupController;

// API routes - run inside 'web' middleware group automatically (session support)
Route::prefix('api')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    Route::get('/data', [DataController::class, 'get']);
    Route::post('/data', [DataController::class, 'post']);
    Route::post('/reset', [DataController::class, 'reset']);

    // Backup & Restore Module Routes
    Route::get('/backups', [BackupController::class, 'index']);
    Route::post('/backups', [BackupController::class, 'create']);
    Route::get('/backups/{id}/download', [BackupController::class, 'download']);
    Route::post('/backups/{id}/restore', [BackupController::class, 'restore']);
    Route::post('/backups/upload', [BackupController::class, 'upload']);
    Route::delete('/backups/{id}', [BackupController::class, 'destroy']);
});

// Catch-all route to serve the React SPA for any non-API URLs
Route::get('{any}', function () {
    return view('app');
})->where('any', '.*');
