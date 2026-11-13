<?php

use App\Http\Controllers\BankController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ManageController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PocketController;
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
    Route::get('/manage', [ManageController::class, 'index'])->name('manage');

    Route::post('/manage/banks', [BankController::class, 'store'])->name('banks.store');
    Route::put('/manage/banks/{bank}', [BankController::class, 'update'])->name('banks.update')->whereNumber('bank');
    Route::post('/manage/banks/{bank}/archive', [BankController::class, 'archive'])->name('banks.archive')->whereNumber('bank');
    Route::post('/manage/banks/{bank}/restore', [BankController::class, 'restore'])->name('banks.restore')->whereNumber('bank');

    Route::post('/manage/pockets', [PocketController::class, 'store'])->name('pockets.store');
    Route::put('/manage/pockets/{pocket}', [PocketController::class, 'update'])->name('pockets.update')->whereNumber('pocket');
    Route::post('/manage/pockets/{pocket}/archive', [PocketController::class, 'archive'])->name('pockets.archive')->whereNumber('pocket');
    Route::post('/manage/pockets/{pocket}/restore', [PocketController::class, 'restore'])->name('pockets.restore')->whereNumber('pocket');

    Route::post('/manage/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/manage/categories/{category}', [CategoryController::class, 'update'])->name('categories.update')->whereNumber('category');
    Route::post('/manage/categories/{category}/archive', [CategoryController::class, 'archive'])->name('categories.archive')->whereNumber('category');
    Route::post('/manage/categories/{category}/restore', [CategoryController::class, 'restore'])->name('categories.restore')->whereNumber('category');

    Route::post('/manage/cards', [CardController::class, 'store'])->name('cards.store');
    Route::put('/manage/cards/{card}', [CardController::class, 'update'])->name('cards.update')->whereNumber('card');
    Route::post('/manage/cards/{card}/archive', [CardController::class, 'archive'])->name('cards.archive')->whereNumber('card');
    Route::post('/manage/cards/{card}/restore', [CardController::class, 'restore'])->name('cards.restore')->whereNumber('card');

    Route::post('/manage/payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
    Route::put('/manage/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update')->whereNumber('paymentMethod');
    Route::post('/manage/payment-methods/{paymentMethod}/archive', [PaymentMethodController::class, 'archive'])->name('payment-methods.archive')->whereNumber('paymentMethod');
    Route::post('/manage/payment-methods/{paymentMethod}/restore', [PaymentMethodController::class, 'restore'])->name('payment-methods.restore')->whereNumber('paymentMethod');
    Route::get('/styleguide', fn () => Inertia::render('Styleguide/Index'))->name('styleguide');
});
