<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');

Route::get('/access', [AccessController::class, 'showLoginForm'])->name('login')->name('access');
Route::post('/access', [AccessController::class, 'login'])->name('access.login');
Route::post('/access/logout', [AccessController::class, 'logout'])->name('access.logout');

// Redirect any attempt to access /admin/login to /access
Route::redirect('/admin/login', '/access');
