<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SourceController;
use App\Http\Controllers\Api\StewardshipController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/meta/dimensions', [MetaController::class, 'dimensions']);

// Authenticated
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::delete('/conversations/{conversation}', [ConversationController::class, 'destroy']);
    Route::post('/conversations/{conversation}/messages', [ChatController::class, 'stream']);

    Route::get('/sources', [SourceController::class, 'index']);
    Route::get('/sources/{path}', [SourceController::class, 'show'])->where('path', '.*');
    Route::post('/uploads/classify', [UploadController::class, 'classify']);
    Route::post('/uploads', [UploadController::class, 'store']);
    Route::post('/uploads/status', [UploadController::class, 'status']);
    Route::post('/exports/xlsx', [ExportController::class, 'xlsx']);

    Route::get('/stewardship/tasks', [StewardshipController::class, 'index']);
    Route::post('/stewardship/tasks/{task}/approve', [StewardshipController::class, 'approve']);
    Route::post('/stewardship/tasks/{task}/reject', [StewardshipController::class, 'reject']);

    Route::get('/meta/stats', [MetaController::class, 'stats']);

    // Admin
    Route::get('/settings', [SettingsController::class, 'show']);
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::post('/settings/test-key', [SettingsController::class, 'test']);
});
