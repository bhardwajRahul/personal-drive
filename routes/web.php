<?php

use App\Http\Controllers\AdminController\AdminConfigController;
use App\Http\Controllers\AdminController\SetupController;
use App\Http\Controllers\DriveControllers;
use App\Http\Controllers\ShareControllers;
use App\Http\Controllers\ShareControllers\ShareFilesGuestController;
use App\Http\Middleware\CheckAdmin;
use App\Http\Middleware\HandleAuthOrGuestMiddleware;
use App\Http\Middleware\HandleGuestShareMiddleware;
use App\Http\Middleware\PreventSetupAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', CheckAdmin::class])->group(callback: function () {
    Route::get('/admin-config', [AdminConfigController::class, 'index'])->name('admin-config');
    Route::post('/admin-config/update', [AdminConfigController::class, 'update']);
    // Drive routes
    Route::get('/drive/{path?}', [DriveControllers\FileManagerController::class, 'index'])
        ->where('path', '.*')
        ->name('drive');
    Route::post('/upload', [DriveControllers\UploadController::class, 'store'])->name('s3.upload');
    Route::post('/create-folder', [DriveControllers\UploadController::class, 'createFolder']);
    Route::post('/delete-files', [DriveControllers\FileDeleteController::class, 'deleteFiles']);
    Route::post('/resync', [DriveControllers\ReSyncController::class, 'index']);
    Route::post('/gen-thumbs', [DriveControllers\ThumbnailController::class, 'update']);
    Route::post('/search-files', [DriveControllers\SearchFilesController::class, 'index']);
    Route::get('/search-files', fn () => redirect('/drive'));

    // Share control Routes
    Route::post('/pause-share', [ShareControllers\ShareFilesModController::class, 'pause']);
    Route::post('/delete-share', [ShareControllers\ShareFilesModController::class, 'delete']);
    Route::post('/share-files', [ShareControllers\ShareFilesGenController::class, 'index']);
    Route::get('/all-shares', [ShareControllers\SharedListController::class, 'index'])->name('all-shares');
});


// admin or shared
Route::get('/fetch-file/{id}/{slug?}', [DriveControllers\FetchFileController::class, 'index'])
    ->middleware([HandleAuthOrGuestMiddleware::class]);
Route::get('/fetch-thumb/{id}/{slug?}', [DriveControllers\FetchFileController::class, 'getThumb'])
    ->middleware([HandleAuthOrGuestMiddleware::class]);
Route::post('/download-files', [DriveControllers\DownloadController::class, 'index'])
    ->middleware([HandleAuthOrGuestMiddleware::class]);

// shared guest routes
Route::post(
    '/shared-check-password',
    [ShareFilesGuestController::class, 'checkPassword']
)->middleware(['throttle:shared']);
Route::get('/shared-password/{slug}', [ShareFilesGuestController::class, 'passwordPage'])
    ->name('shared.password')->middleware(['throttle:shared']);
Route::get('/shared/{slug}/{path?}', [ShareControllers\ShareFilesGuestController::class, 'index'])->where(
    'path',
    '.*'
)->middleware([HandleGuestShareMiddleware::class])->name('shared');

// Rejects
Route::get('/', fn () => to_route('drive'));
Route::fallback(fn () => to_route('rejected'));
Route::get(
    '/rejected',
    fn (Request $request) => Inertia::render('Rejected', [
        'message' => $request->query('message', 'No Permission or error'),
    ])
)->name('rejected');

// Setup
Route::middleware([PreventSetupAccess::class])->group(function () {
    Route::get('/setup/account', [SetupController::class, 'show']);
    Route::post('/setup/account', [SetupController::class, 'update']);
    Route::post('/setup/storage', [AdminConfigController::class, 'update']);
});

require __DIR__ . '/auth.php';
