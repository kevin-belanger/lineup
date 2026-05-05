<?php

use App\Http\Controllers\Student\ClassroomController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\SupportRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'approved', 'verified', 'role:student'])
    ->prefix('student')
    ->name('student.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/classroom', [ClassroomController::class, 'edit'])->name('classroom.edit');
        Route::put('/classroom', [ClassroomController::class, 'update'])->name('classroom.update');

        Route::get('/requests/create', [SupportRequestController::class, 'create'])->name('requests.create');
        Route::post('/requests', [SupportRequestController::class, 'store'])->name('requests.store');
        Route::get('/requests/{supportRequest}/edit', [SupportRequestController::class, 'edit'])->name('requests.edit');
        Route::patch('/requests/{supportRequest}', [SupportRequestController::class, 'update'])->name('requests.update');
        Route::patch('/requests/{supportRequest}/cancel', [SupportRequestController::class, 'cancel'])->name('requests.cancel');
        Route::patch('/requests/{supportRequest}/ready', [SupportRequestController::class, 'markReady'])->name('requests.ready');
        Route::get('/history', [SupportRequestController::class, 'history'])->name('history');
    });
