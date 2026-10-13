<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MigrationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

// No public registration: users are created on the server with `php artisan movez:user`
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

    Route::get('/sessions/{session}', [SessionController::class, 'show'])->name('sessions.show');

    Route::get('/migration/wizard', [MigrationController::class, 'wizard'])->name('migration.wizard');
    Route::post('/migration/start', [MigrationController::class, 'start'])->name('migration.start');
});
