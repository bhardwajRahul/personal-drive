<?php

use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\FileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('v1')->group(function () {
    Route::get('/tokens', [ApiTokenController::class, 'index']);
    Route::post('/tokens', [ApiTokenController::class, 'store']);
    Route::delete('/tokens/{tokenId}', [ApiTokenController::class, 'destroy']);
});

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('/files', [FileController::class, 'index']);
    Route::post('/files/upload', [FileController::class, 'upload']);
    Route::post('/files/create', [FileController::class, 'create']);
    Route::post('/files/move', [FileController::class, 'move']);
    Route::get('/files/{id}', [FileController::class, 'show']);
    Route::get('/files/{id}/download', [FileController::class, 'download']);
    Route::delete('/files/{id}', [FileController::class, 'destroy']);
    Route::post('/files/{id}/rename', [FileController::class, 'rename']);
    Route::post('/files/{id}/save', [FileController::class, 'save']);
});
