<?php

use App\Http\Controllers\LopAssignmentController;
use App\Http\Controllers\LopController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

Route::get('/', function () {
    return redirect()->route('lop.index');
})->middleware('auth');

Route::middleware(['auth'])->prefix('lop')->name('lop.')->group(function () {
    Route::get('/', [LopController::class, 'index'])->name('index');
    Route::get('/history', [LopController::class, 'history'])->name('history');
    Route::get('/create', [LopController::class, 'create'])->name('create');
    Route::post('/', [LopController::class, 'store'])->name('store');
    Route::get('/{qe_lop}', [LopController::class, 'show'])->name('show');
    Route::get('/{qe_lop}/edit', [LopController::class, 'edit'])->name('edit');
    Route::put('/{qe_lop}', [LopController::class, 'update'])->name('update');
    Route::post('/{qe_lop}/transition', [LopController::class, 'transitionStatus'])->name('transition');
    Route::post('/{qe_lop}/assign', [LopAssignmentController::class, 'store'])->name('assign');
});

Route::middleware(['auth'])->prefix('users')->name('users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/create', [UserController::class, 'create'])->name('create');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::post('/{id_user}/restore', [UserController::class, 'restore'])->name('restore');
    Route::get('/{user}', [UserController::class, 'show'])->name('show');
    Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
    Route::put('/{user}', [UserController::class, 'update'])->name('update');
    Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    Route::post('/{user}/activate', [UserController::class, 'activate'])->name('activate');
    Route::post('/{user}/deactivate', [UserController::class, 'deactivate'])->name('deactivate');
});
