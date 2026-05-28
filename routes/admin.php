<?php

use App\Http\Controllers\Admin\ClassroomController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'approved', 'verified', 'role:admin,teacher'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::view('/', 'admin.dashboard')->name('dashboard');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/password', [UserController::class, 'updatePassword'])->name('users.password');
        Route::patch('/users/{user}/approve', [UserController::class, 'approve'])->name('users.approve');
        Route::patch('/users/{user}/roles', [UserController::class, 'updateRoles'])->name('users.roles');
        Route::patch('/users/{user}/active', [UserController::class, 'toggleActive'])->name('users.active');

        Route::get('/classrooms', [ClassroomController::class, 'index'])->name('classrooms.index');
        Route::post('/classrooms', [ClassroomController::class, 'store'])->name('classrooms.store');
        Route::post('/classrooms/public-slugs', [ClassroomController::class, 'reservePublicSlug'])->name('classrooms.public-slugs.store');
        Route::patch('/classrooms/{classroom}', [ClassroomController::class, 'update'])->name('classrooms.update');
        Route::patch('/classrooms/{classroom}/active', [ClassroomController::class, 'toggleActive'])->name('classrooms.active');
        Route::delete('/classrooms/{classroom}', [ClassroomController::class, 'destroy'])->name('classrooms.destroy');

        Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');
        Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
        Route::patch('/subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
        Route::patch('/subjects/{subject}/active', [SubjectController::class, 'toggleActive'])->name('subjects.active');
        Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');

        Route::middleware('role:admin')->group(function (): void {
            Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
            Route::patch('/settings', [SettingController::class, 'update'])->name('settings.update');
            Route::post('/settings/request-types', [SettingController::class, 'storeRequestType'])->name('settings.request-types.store');
            Route::delete('/settings/request-types/{requestType}', [SettingController::class, 'destroyRequestType'])->name('settings.request-types.destroy');
        });
    });
