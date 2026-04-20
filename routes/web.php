<?php

use App\Http\Controllers\CharacterController;
use App\Http\Controllers\MudLogController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [CharacterController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('characters/{character}/areas', [CharacterController::class, 'areas'])->name('characters.areas');
    Route::post('characters/{character}/areas/toggle', [CharacterController::class, 'toggleArea'])->name('characters.areas.toggle');

    Route::resource('characters', CharacterController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);

    Route::post('active-character', [CharacterController::class, 'setActive'])->name('active-character.set');
    Route::get('areas', [CharacterController::class, 'areasIndex'])->name('areas.index');

    Route::get('mudlogs', [MudLogController::class, 'index'])->name('mudlogs.index');
    Route::get('mudlogs/items', [MudLogController::class, 'items'])->name('mudlogs.items');
    Route::get('mudlogs/pending', [MudLogController::class, 'pending'])->name('mudlogs.pending');
    Route::post('mudlogs/pending/{item}/confirm', [MudLogController::class, 'confirmPending'])->name('mudlogs.pending.confirm');
    Route::post('mudlogs/pending/{item}/ignore', [MudLogController::class, 'ignorePending'])->name('mudlogs.pending.ignore');
    Route::post('mudlogs/scan', [MudLogController::class, 'scan'])->name('mudlogs.scan');
    Route::post('mudlogs/rescan-all', [MudLogController::class, 'rescanAll'])->name('mudlogs.rescan-all');
    Route::post('mudlogs/clear-database', [MudLogController::class, 'clearDatabase'])->name('mudlogs.clear-database');
    Route::post('mudlogs/bulk', [MudLogController::class, 'bulk'])->name('mudlogs.bulk');
    Route::post('mudlogs/failed/{uuid}/retry', [MudLogController::class, 'retryFailedJob'])->name('mudlogs.failed.retry');
    Route::post('mudlogs/failed/{uuid}/forget', [MudLogController::class, 'forgetFailedJob'])->name('mudlogs.failed.forget');
    Route::post('mudlogs/failed/flush', [MudLogController::class, 'flushFailedJobs'])->name('mudlogs.failed.flush');
    Route::post('mudlogs/upload', [MudLogController::class, 'upload'])->name('mudlogs.upload');
    Route::get('mudlogs/{mudlog}', [MudLogController::class, 'show'])->name('mudlogs.show');
    Route::post('mudlogs/{mudlog}/toggle', [MudLogController::class, 'toggleReviewed'])->name('mudlogs.toggle');
    Route::post('mudlogs/{mudlog}/rescan', [MudLogController::class, 'rescan'])->name('mudlogs.rescan');
    Route::delete('mudlogs/{mudlog}', [MudLogController::class, 'destroy'])->name('mudlogs.destroy');
});

require __DIR__.'/auth.php';
