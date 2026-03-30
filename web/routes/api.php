<?php

use App\Http\Controllers\SyncController;
use App\Http\Middleware\ApiTokenMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(ApiTokenMiddleware::class)->group(function (): void {
    Route::post('/sync/push', [SyncController::class, 'push'])->name('sync.push');
    Route::get('/sync/pull',  [SyncController::class, 'pull'])->name('sync.pull');
});
