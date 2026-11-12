<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Dashboard/Index'))->name('dashboard');
Route::get('/analysis', fn () => Inertia::render('Analysis/Index'))->name('analysis');
Route::get('/planning', fn () => Inertia::render('Planning/Index'))->name('planning');
Route::get('/goals', fn () => Inertia::render('Goals/Index'))->name('goals');
Route::get('/net-worth', fn () => Inertia::render('NetWorth/Index'))->name('net-worth');
Route::get('/reports', fn () => Inertia::render('Reports/Index'))->name('reports');
Route::get('/manage', fn () => Inertia::render('Manage/Index'))->name('manage');
Route::get('/styleguide', fn () => Inertia::render('Styleguide/Index'))->name('styleguide');
