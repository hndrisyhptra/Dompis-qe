<?php

use App\Enums\UserRole;
use App\Http\Controllers\DesignatorController;
use App\Http\Controllers\DesignatorPriceController;
use App\Http\Controllers\EvidenceApprovalController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\LopAssignmentController;
use App\Http\Controllers\LopController;
use App\Http\Controllers\LopNameFormatController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\TechnicianController;
use App\Http\Controllers\TechnicianWorkflowController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

Route::middleware(['auth', 'role:TEKNISI'])->prefix('technician')->name('technician.')->group(function () {
    Route::get('/', [TechnicianController::class, 'dashboard'])->name('dashboard');
    Route::get('/inbox', [TechnicianController::class, 'inbox'])->name('inbox');
    Route::get('/projects/{qe_lop}', [TechnicianController::class, 'project'])->name('projects.show');
    Route::post('/projects/{qe_lop}/pickup', [TechnicianWorkflowController::class, 'pickup'])->name('projects.pickup');
    Route::post('/projects/{qe_lop}/resume', [TechnicianWorkflowController::class, 'resume'])->name('projects.resume');
    Route::put('/projects/{qe_lop}/materials', [TechnicianWorkflowController::class, 'materials'])->name('projects.materials');
    Route::put('/projects/{qe_lop}/location', [TechnicianWorkflowController::class, 'location'])->name('projects.location');
    Route::post('/projects/{qe_lop}/evidence', [TechnicianWorkflowController::class, 'evidence'])->name('projects.evidence');
    Route::put('/projects/{qe_lop}/evidence/{evidence}/replace', [TechnicianWorkflowController::class, 'replaceEvidence'])->name('projects.evidence.replace');
    Route::post('/projects/{qe_lop}/survey-complete', [TechnicianWorkflowController::class, 'completeSurvey'])->name('projects.survey-complete');
    Route::post('/projects/{qe_lop}/submit', [TechnicianWorkflowController::class, 'submitApproval'])->name('projects.submit');
    Route::get('/notifications', [TechnicianController::class, 'notifications'])->name('notifications');
    Route::post('/notifications/read-all', [TechnicianController::class, 'readAllNotifications'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [TechnicianController::class, 'readNotification'])->name('notifications.read');
    Route::get('/profile', [TechnicianController::class, 'profile'])->name('profile');
});

Route::get('/', function () {
    if (request()->user()->hasRole(UserRole::TEKNISI)) {
        return redirect()->route('technician.dashboard');
    }

    if (request()->user()->hasRole(UserRole::SUPER_ADMIN)) {
        return redirect()->route('evidence-approval.index');
    }

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
    Route::delete('/{qe_lop}/assign', [LopAssignmentController::class, 'destroy'])->name('unassign');
    Route::post('/{qe_lop}/evidence', [EvidenceController::class, 'store'])->name('evidence.store');
    Route::delete('/{qe_lop}/evidence/{evidence}', [EvidenceController::class, 'destroy'])->name('evidence.destroy');
});

Route::middleware(['auth'])->prefix('settings/lop-name-format')->name('lop-name-format.')->group(function () {
    Route::get('/', [LopNameFormatController::class, 'edit'])->name('edit');
    Route::put('/', [LopNameFormatController::class, 'update'])->name('update');
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
    Route::get('/lop/{qe_lop}', [EvidenceApprovalController::class, 'reviewLop'])->name('lop.review');
    Route::get('/{evidence}', [EvidenceApprovalController::class, 'show'])->name('show');
    Route::post('/{evidence}/approve', [EvidenceApprovalController::class, 'approve'])->name('approve');
    Route::post('/{evidence}/reject', [EvidenceApprovalController::class, 'reject'])->name('reject');
});
