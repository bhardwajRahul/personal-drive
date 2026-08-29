<?php

use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\ShareController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('v1')->group(function () {
    Route::get('/files', [FileController::class, 'index']);
    Route::post('/files/upload', [FileController::class, 'upload']);
    Route::post('/files/create', [FileController::class, 'create']);
    Route::post('/files/move', [FileController::class, 'move']);
    Route::get('/files/{id}', [FileController::class, 'show']);
    Route::get('/files/{id}/download', [FileController::class, 'download']);
    Route::delete('/files/{id}', [FileController::class, 'destroy']);
    Route::post('/files/{id}/rename', [FileController::class, 'rename']);
    Route::post('/files/{id}/save', [FileController::class, 'save']);

    Route::get('/search', SearchController::class);

    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{id}', [FavoriteController::class, 'destroy']);

    Route::get('/shares', [ShareController::class, 'index']);
    Route::post('/shares', [ShareController::class, 'store']);
    Route::delete('/shares/{id}', [ShareController::class, 'destroy']);
    Route::post('/shares/{id}/toggle', [ShareController::class, 'toggle']);
});
