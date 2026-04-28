<?php

use App\Http\Controllers\CharacterController;
use App\Http\Controllers\MobController;
use App\Http\Controllers\MudLogController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Queue worker endpoint for cron jobs (URL-based triggering)
Route::get('/cron/worker/{token}', function ($token) {
    if ($token !== env('CRON_SECRET_TOKEN')) {
        abort(403);
    }

    // Process jobs from queue (without needing php artisan queue:work)
    \Illuminate\Support\Facades\Artisan::call('queue:work', [
        '--stop-when-empty' => true,
        '--tries' => 3,
        '--timeout' => 60,
    ]);

    return response()->json([
        'processed' => true,
        'output' => \Illuminate\Support\Facades\Artisan::output(),
    ]);
})->name('cron.worker');

Route::get('/dashboard', [CharacterController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/areas/{area}/wiki', [CharacterController::class, 'wiki'])->name('areas.wiki');
    Route::post('/wiki/rescrape', [CharacterController::class, 'rescrapeWiki'])->name('wiki.rescrape');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('characters/{character}/areas', [CharacterController::class, 'areas'])->name('characters.areas');
    Route::post('characters/{character}/areas/toggle', [CharacterController::class, 'toggleArea'])->name('characters.areas.toggle');

    Route::resource('characters', CharacterController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
    Route::patch('characters/{character}/level', [CharacterController::class, 'updateLevel'])->name('characters.update-level');

    Route::post('active-character', [CharacterController::class, 'setActive'])->name('active-character.set');
    Route::get('areas', [CharacterController::class, 'areasIndex'])->name('areas.index');
    Route::get('areas/create', [CharacterController::class, 'createArea'])->name('areas.create');
    Route::post('areas', [CharacterController::class, 'storeArea'])->name('areas.store');

    Route::get('mudlogs', [MudLogController::class, 'index'])->name('mudlogs.index');
    Route::get('mudlogs/items', [MudLogController::class, 'items'])->name('mudlogs.items');
    Route::get('mudlogs/items/{item}/edit', [MudLogController::class, 'editItem'])->name('mudlogs.items.edit');
    Route::post('mudlogs/items/{item}', [MudLogController::class, 'updateItem'])->name('mudlogs.items.update');
    Route::get('mudlogs/pending', [MudLogController::class, 'pending'])->name('mudlogs.pending');
    Route::post('mudlogs/pending/{item}/confirm', [MudLogController::class, 'confirmPending'])->name('mudlogs.pending.confirm');
    Route::post('mudlogs/pending/{item}/ignore', [MudLogController::class, 'ignorePending'])->name('mudlogs.pending.ignore');
    Route::post('mudlogs/pending/{item}/overwrite', [MudLogController::class, 'overwritePending'])->name('mudlogs.pending.overwrite');
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

    // Mobs
    Route::get('mobs', [MobController::class, 'index'])->name('mobs.index');
    Route::get('mobs/create', [MobController::class, 'create'])->name('mobs.create');
    Route::post('mobs', [MobController::class, 'store'])->name('mobs.store');
    Route::get('mobs/{mob}/edit', [MobController::class, 'edit'])->name('mobs.edit');
    Route::put('mobs/{mob}', [MobController::class, 'update'])->name('mobs.update');
    Route::delete('mobs/{mob}', [MobController::class, 'destroy'])->name('mobs.destroy');
    Route::post('mudlogs/{mudlog}/rescan', [MudLogController::class, 'rescan'])->name('mudlogs.rescan');
    Route::delete('mudlogs/{mudlog}', [MudLogController::class, 'destroy'])->name('mudlogs.destroy');
});

require __DIR__.'/auth.php';
