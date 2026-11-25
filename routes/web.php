<?php

use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FixedExpenseController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\InstallmentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ManageController;
use App\Http\Controllers\NetWorthController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavedFilterController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\TransactionController;
use App\Http\Middleware\SyncCurrentPendings;
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

Route::middleware(['auth', 'inertia.encrypt', SyncCurrentPendings::class])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::get('/analysis', [AnalysisController::class, 'index'])->name('analysis');
    Route::post('/analysis/clear', [AnalysisController::class, 'clear'])->name('analysis.clear');

    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices');
    Route::post('/invoices/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
    Route::delete('/invoices/pay', [InvoiceController::class, 'unpay'])->name('invoices.unpay');
    Route::get('/cards/{card}/invoice-preview', [InvoiceController::class, 'preview'])->name('cards.invoice-preview')->whereNumber('card');

    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::put('/transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update')->whereNumber('transaction');
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy')->whereNumber('transaction');
    Route::post('/transactions/{transaction}/pay', [TransactionController::class, 'pay'])->name('transactions.pay')->whereNumber('transaction');

    Route::post('/saved-filters', [SavedFilterController::class, 'store'])->name('saved-filters.store');
    Route::delete('/saved-filters/{savedFilter}', [SavedFilterController::class, 'destroy'])->name('saved-filters.destroy')->whereNumber('savedFilter');
    Route::get('/planning', [PlanningController::class, 'index'])->name('planning');
    Route::put('/planning/settings', [PlanningController::class, 'settings'])->name('planning.settings');
    Route::post('/planning/installments/preview', [PlanningController::class, 'preview'])->name('planning.installments.preview');
    Route::put('/planning/{month}', [PlanningController::class, 'update'])->name('planning.update')->where('month', '[^/]+');
    Route::post('/planning/{month}/copy', [PlanningController::class, 'copy'])->name('planning.copy')->where('month', '[^/]+');
    Route::post('/planning/{month}/confirm', [PlanningController::class, 'confirm'])->name('planning.confirm')->where('month', '[^/]+');
    Route::post('/planning/{month}/reopen', [PlanningController::class, 'reopen'])->name('planning.reopen')->where('month', '[^/]+');

    Route::post('/planning/fixed-expenses', [FixedExpenseController::class, 'store'])->name('planning.fixed-expenses.store');
    Route::put('/planning/fixed-expenses/{fixedExpense}', [FixedExpenseController::class, 'update'])->name('planning.fixed-expenses.update')->whereNumber('fixedExpense');
    Route::post('/planning/fixed-expenses/{fixedExpense}/archive', [FixedExpenseController::class, 'archive'])->name('planning.fixed-expenses.archive')->whereNumber('fixedExpense');
    Route::post('/planning/fixed-expenses/{fixedExpense}/restore', [FixedExpenseController::class, 'restore'])->name('planning.fixed-expenses.restore')->whereNumber('fixedExpense');

    Route::post('/planning/installments', [InstallmentController::class, 'store'])->name('planning.installments.store');
    Route::put('/planning/installments/{installment}', [InstallmentController::class, 'update'])->name('planning.installments.update')->whereNumber('installment');
    Route::post('/planning/installments/{installment}/archive', [InstallmentController::class, 'archive'])->name('planning.installments.archive')->whereNumber('installment');
    Route::post('/planning/installments/{installment}/restore', [InstallmentController::class, 'restore'])->name('planning.installments.restore')->whereNumber('installment');
    Route::get('/goals', [GoalController::class, 'index'])->name('goals');
    Route::post('/goals', [GoalController::class, 'store'])->name('goals.store');
    Route::get('/goals/{goal}', [GoalController::class, 'show'])->name('goals.show')->whereNumber('goal');
    Route::put('/goals/{goal}', [GoalController::class, 'update'])->name('goals.update')->whereNumber('goal');
    Route::patch('/goals/{goal}/archive', [GoalController::class, 'archive'])->name('goals.archive')->whereNumber('goal');
    Route::patch('/goals/{goal}/restore', [GoalController::class, 'restore'])->name('goals.restore')->whereNumber('goal');
    Route::delete('/goals/{goal}', [GoalController::class, 'destroy'])->name('goals.destroy')->whereNumber('goal');
    Route::get('/net-worth', [NetWorthController::class, 'index'])->name('net-worth');
    Route::get('/net-worth/form', [NetWorthController::class, 'form'])->name('net-worth.form');
    Route::post('/net-worth', [NetWorthController::class, 'store'])->name('net-worth.store');
    Route::put('/net-worth/{closing}', [NetWorthController::class, 'update'])->name('net-worth.update')->whereNumber('closing');
    Route::delete('/net-worth/{closing}', [NetWorthController::class, 'destroy'])->name('net-worth.destroy')->whereNumber('closing');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports');
    Route::get('/reports/download', [ReportController::class, 'download'])->name('reports.download');
    Route::get('/manage', [ManageController::class, 'index'])->name('manage');

    Route::post('/manage/banks', [BankController::class, 'store'])->name('banks.store');
    Route::put('/manage/banks/{bank}', [BankController::class, 'update'])->name('banks.update')->whereNumber('bank');
    Route::post('/manage/banks/{bank}/archive', [BankController::class, 'archive'])->name('banks.archive')->whereNumber('bank');
    Route::post('/manage/banks/{bank}/restore', [BankController::class, 'restore'])->name('banks.restore')->whereNumber('bank');
    Route::delete('/manage/banks/{bank}', [BankController::class, 'destroy'])->name('banks.destroy')->whereNumber('bank');

    Route::post('/manage/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/manage/categories/{category}', [CategoryController::class, 'update'])->name('categories.update')->whereNumber('category');
    Route::post('/manage/categories/{category}/archive', [CategoryController::class, 'archive'])->name('categories.archive')->whereNumber('category');
    Route::post('/manage/categories/{category}/restore', [CategoryController::class, 'restore'])->name('categories.restore')->whereNumber('category');
    Route::delete('/manage/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy')->whereNumber('category');

    Route::post('/manage/cards', [CardController::class, 'store'])->name('cards.store');
    Route::put('/manage/cards/{card}', [CardController::class, 'update'])->name('cards.update')->whereNumber('card');
    Route::post('/manage/cards/{card}/archive', [CardController::class, 'archive'])->name('cards.archive')->whereNumber('card');
    Route::post('/manage/cards/{card}/restore', [CardController::class, 'restore'])->name('cards.restore')->whereNumber('card');
    Route::delete('/manage/cards/{card}', [CardController::class, 'destroy'])->name('cards.destroy')->whereNumber('card');

    Route::post('/manage/payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
    Route::put('/manage/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update')->whereNumber('paymentMethod');
    Route::post('/manage/payment-methods/{paymentMethod}/archive', [PaymentMethodController::class, 'archive'])->name('payment-methods.archive')->whereNumber('paymentMethod');
    Route::post('/manage/payment-methods/{paymentMethod}/restore', [PaymentMethodController::class, 'restore'])->name('payment-methods.restore')->whereNumber('paymentMethod');
    Route::delete('/manage/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy')->whereNumber('paymentMethod');
    Route::put('/manage/tags/{tag}', [TagController::class, 'update'])->name('tags.update')->whereNumber('tag');
    Route::delete('/manage/tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy')->whereNumber('tag');
    Route::get('/styleguide', fn () => Inertia::render('Styleguide/Index'))->name('styleguide');
});
