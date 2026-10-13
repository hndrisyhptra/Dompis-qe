<?php

use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::get('auth/session', [AuthenticatedSessionController::class, 'context'])
    ->middleware('throttle:120,1')->name('auth.session');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('account/password', [AccountPasswordController::class, 'edit'])->name('account.password.edit');
    Route::put('account/password', [AccountPasswordController::class, 'update'])
        ->middleware('throttle:6,1')->name('account.password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
