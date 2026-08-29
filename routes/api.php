<?php

use App\Http\Controllers\Api\ApiTokenController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('v1')->group(function () {
    Route::get('/tokens', [ApiTokenController::class, 'index']);
    Route::post('/tokens', [ApiTokenController::class, 'store']);
    Route::delete('/tokens/{tokenId}', [ApiTokenController::class, 'destroy']);
});
