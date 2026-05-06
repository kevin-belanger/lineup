<?php

use App\Http\Controllers\Teacher\ClassroomController;
use App\Http\Controllers\Teacher\DashboardController;
use App\Http\Controllers\Teacher\PriorityRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'approved', 'verified', 'role:teacher'])
    ->prefix('teacher')
    ->name('teacher.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/priority-requests', PriorityRequestController::class)->name('priority-requests.index');
        Route::get('/classroom', [ClassroomController::class, 'edit'])->name('classroom.edit');
        Route::put('/classroom', [ClassroomController::class, 'update'])->name('classroom.update');
    });
