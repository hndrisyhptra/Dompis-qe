<?php

use App\Http\Controllers\DesignatorController;
use App\Http\Controllers\DesignatorPriceController;
use App\Http\Controllers\EvidenceApprovalController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\LopAssignmentController;
use App\Http\Controllers\LopController;
use App\Http\Controllers\PackageController;
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
    Route::post('/{qe_lop}/evidence', [EvidenceController::class, 'store'])->name('evidence.store');
    Route::delete('/{qe_lop}/evidence/{evidence}', [EvidenceController::class, 'destroy'])->name('evidence.destroy');
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

Route::middleware(['auth'])->prefix('designators')->name('designators.')->group(function () {
    Route::get('/', [DesignatorController::class, 'index'])->name('index');
    Route::get('/create', [DesignatorController::class, 'create'])->name('create');
    Route::post('/', [DesignatorController::class, 'store'])->name('store');
    Route::get('/import', [DesignatorController::class, 'importForm'])->name('import.form');
    Route::post('/import', [DesignatorController::class, 'import'])->name('import');
    Route::get('/{designator}/edit', [DesignatorController::class, 'edit'])->name('edit');
    Route::put('/{designator}', [DesignatorController::class, 'update'])->name('update');
    Route::delete('/{designator}', [DesignatorController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth'])->prefix('packages')->name('packages.')->group(function () {
    Route::get('/', [PackageController::class, 'index'])->name('index');
    Route::get('/create', [PackageController::class, 'create'])->name('create');
    Route::post('/', [PackageController::class, 'store'])->name('store');
    Route::get('/{package}/edit', [PackageController::class, 'edit'])->name('edit');
    Route::put('/{package}', [PackageController::class, 'update'])->name('update');
    Route::delete('/{package}', [PackageController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth'])->prefix('designator-prices')->name('designator-prices.')->group(function () {
    Route::get('/', [DesignatorPriceController::class, 'index'])->name('index');
    Route::get('/create', [DesignatorPriceController::class, 'create'])->name('create');
    Route::post('/', [DesignatorPriceController::class, 'store'])->name('store');
    Route::get('/import', [DesignatorPriceController::class, 'importForm'])->name('import.form');
    Route::post('/import', [DesignatorPriceController::class, 'import'])->name('import');
    Route::get('/{designator_price}/edit', [DesignatorPriceController::class, 'edit'])->name('edit');
    Route::put('/{designator_price}', [DesignatorPriceController::class, 'update'])->name('update');
    Route::delete('/{designator_price}', [DesignatorPriceController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth'])->prefix('evidence-approval')->name('evidence-approval.')->group(function () {
    Route::get('/', [EvidenceApprovalController::class, 'index'])->name('index');
    Route::get('/{evidence}', [EvidenceApprovalController::class, 'show'])->name('show');
    Route::post('/{evidence}/approve', [EvidenceApprovalController::class, 'approve'])->name('approve');
    Route::post('/{evidence}/reject', [EvidenceApprovalController::class, 'reject'])->name('reject');
});
