<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return view('auth.login');
});

Route::get('/dashboard', function () {
    return redirect()->route(request()->user()->homeRouteName());
})->middleware(['auth', 'active', 'approved', 'verified'])->name('dashboard');

Route::get('/approval-pending', function () {
    if (request()->user()->is_approved) {
        return redirect()->route(request()->user()->homeRouteName());
    }

    return view('auth.approval-pending');
})->middleware(['auth', 'active'])->name('approval.pending');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/language', [ProfileController::class, 'updateLanguage'])->name('profile.language.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/student.php';
require __DIR__.'/teacher.php';
require __DIR__.'/admin.php';
require __DIR__.'/auth.php';
