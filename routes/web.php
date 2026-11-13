<?php

use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store']);
    Route::get('/register', [RegistrationController::class, 'create'])->name('register');
    Route::post('/register', [RegistrationController::class, 'store']);
});

Route::post('/logout', [SessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'inertia.encrypt'])->group(function () {
    Route::get('/', fn () => Inertia::render('Dashboard/Index'))->name('dashboard');
    Route::get('/analysis', fn () => Inertia::render('Analysis/Index'))->name('analysis');
    Route::get('/planning', fn () => Inertia::render('Planning/Index'))->name('planning');
    Route::get('/goals', fn () => Inertia::render('Goals/Index'))->name('goals');
    Route::get('/net-worth', fn () => Inertia::render('NetWorth/Index'))->name('net-worth');
    Route::get('/reports', fn () => Inertia::render('Reports/Index'))->name('reports');
    Route::get('/manage', fn () => Inertia::render('Manage/Index'))->name('manage');
    Route::get('/styleguide', fn () => Inertia::render('Styleguide/Index'))->name('styleguide');
});
