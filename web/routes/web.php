<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MigrationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

Route::get('/sessions/{session}', [SessionController::class, 'show'])->name('sessions.show');

Route::get('/migration/wizard', [MigrationController::class, 'wizard'])->name('migration.wizard');
Route::post('/migration/start', [MigrationController::class, 'start'])->name('migration.start');
