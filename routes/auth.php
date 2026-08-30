<?php

use App\Http\Controllers\AuthControllers\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:login','guest'])->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
