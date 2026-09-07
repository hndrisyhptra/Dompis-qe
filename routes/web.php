<?php

use App\Enums\UserRole;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DesignatorCategoryController;
use App\Http\Controllers\DesignatorController;
use App\Http\Controllers\DesignatorPriceController;
use App\Http\Controllers\DesignatorTypeController;
use App\Http\Controllers\EvidenceApprovalController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\LopAssignmentController;
use App\Http\Controllers\LopController;
use App\Http\Controllers\LopNameFormatController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TechnicianController;
use App\Http\Controllers\TechnicianWorkflowController;
use App\Http\Controllers\TicketSegmentMapController;
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
    Route::put('/projects/{qe_lop}/material-usage', [TechnicianWorkflowController::class, 'materialUsage'])->name('projects.material-usage');
    Route::put('/projects/{qe_lop}/location', [TechnicianWorkflowController::class, 'location'])->name('projects.location');
    Route::post('/projects/{qe_lop}/evidence', [TechnicianWorkflowController::class, 'evidence'])->name('projects.evidence');
    Route::post('/projects/{qe_lop}/evidence/file', [TechnicianWorkflowController::class, 'evidenceFile'])->name('projects.evidence.file');
    Route::put('/projects/{qe_lop}/evidence/{evidence}/replace', [TechnicianWorkflowController::class, 'replaceEvidence'])->name('projects.evidence.replace');
    Route::post('/projects/{qe_lop}/survey-complete', [TechnicianWorkflowController::class, 'completeSurvey'])->name('projects.survey-complete');
    Route::post('/projects/{qe_lop}/submit', [TechnicianWorkflowController::class, 'submitApproval'])->name('projects.submit');
    Route::get('/notifications', [TechnicianController::class, 'notifications'])->name('notifications');
    Route::post('/notifications/read-all', [TechnicianController::class, 'readAllNotifications'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [TechnicianController::class, 'readNotification'])->name('notifications.read');
    Route::get('/profile', [TechnicianController::class, 'profile'])->name('profile');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'role:SUPER_ADMIN,ADMIN'])
    ->name('dashboard');

Route::get('/', function () {
    if (request()->user()->hasRole(UserRole::TEKNISI)) {
        return redirect()->route('technician.dashboard');
    }

    if (request()->user()->hasRole(UserRole::SUPER_ADMIN, UserRole::ADMIN)) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('lop.index');
})->middleware('auth');

Route::middleware(['auth'])->prefix('lop')->name('lop.')->group(function () {
    Route::get('/', [LopController::class, 'index'])->name('index');
    Route::get('/history', [LopController::class, 'history'])->name('history');
    Route::get('/create', [LopController::class, 'create'])->name('create');
    Route::get('/ticket-lookup', [LopController::class, 'ticketLookup'])->name('ticket-lookup');
    Route::get('/manual-incident', [LopController::class, 'manualIncident'])->name('manual-incident');
    Route::get('/parse-datek', [LopController::class, 'parseDatek'])->name('parse-datek');
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

Route::middleware(['auth'])->prefix('program')->name('program.')->group(function () {
    Route::get('/', [ProgramController::class, 'index'])->name('index');
    Route::get('/{program}', [ProgramController::class, 'show'])->name('show');
});

Route::middleware(['auth'])->prefix('reports')->name('reports.')->group(function () {
    Route::get('/boq-actual', [ReportController::class, 'boqActual'])->name('boq-actual');
    Route::get('/boq-actual/export', [ReportController::class, 'boqActualExport'])->name('boq-actual.export');
    Route::get('/sisa-material', [ReportController::class, 'sisaMaterial'])->name('sisa-material');
    Route::get('/sisa-material/export', [ReportController::class, 'sisaMaterialExport'])->name('sisa-material.export');
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

Route::middleware(['auth'])->prefix('master-data')->name('master-data.')->group(function () {
    Route::get('/', [MasterDataController::class, 'index'])->name('index');
});

Route::middleware(['auth'])->prefix('regions')->name('regions.')->group(function () {
    Route::get('/', [RegionController::class, 'index'])->name('index');
    Route::get('/create', [RegionController::class, 'create'])->name('create');
    Route::post('/', [RegionController::class, 'store'])->name('store');
    Route::get('/{region}/edit', [RegionController::class, 'edit'])->name('edit');
    Route::put('/{region}', [RegionController::class, 'update'])->name('update');
    Route::delete('/{region}', [RegionController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth'])->prefix('branches')->name('branches.')->group(function () {
    Route::get('/', [BranchController::class, 'index'])->name('index');
    Route::get('/create', [BranchController::class, 'create'])->name('create');
    Route::post('/', [BranchController::class, 'store'])->name('store');
    Route::get('/{branch}/edit', [BranchController::class, 'edit'])->name('edit');
    Route::put('/{branch}', [BranchController::class, 'update'])->name('update');
    Route::delete('/{branch}', [BranchController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth'])->prefix('designator-categories')->name('designator-categories.')->group(function () {
    Route::get('/', [DesignatorCategoryController::class, 'index'])->name('index');
    Route::get('/create', [DesignatorCategoryController::class, 'create'])->name('create');
    Route::post('/', [DesignatorCategoryController::class, 'store'])->name('store');
    Route::get('/{designator_category}/edit', [DesignatorCategoryController::class, 'edit'])->name('edit');
    Route::put('/{designator_category}', [DesignatorCategoryController::class, 'update'])->name('update');
    Route::delete('/{designator_category}', [DesignatorCategoryController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth'])->prefix('designator-types')->name('designator-types.')->group(function () {
    Route::get('/', [DesignatorTypeController::class, 'index'])->name('index');
    Route::get('/create', [DesignatorTypeController::class, 'create'])->name('create');
    Route::post('/', [DesignatorTypeController::class, 'store'])->name('store');
    Route::get('/{designator_type}/edit', [DesignatorTypeController::class, 'edit'])->name('edit');
    Route::put('/{designator_type}', [DesignatorTypeController::class, 'update'])->name('update');
    Route::delete('/{designator_type}', [DesignatorTypeController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth'])->prefix('ticket-segment-maps')->name('ticket-segment-maps.')->group(function () {
    Route::get('/', [TicketSegmentMapController::class, 'index'])->name('index');
    Route::get('/create', [TicketSegmentMapController::class, 'create'])->name('create');
    Route::post('/', [TicketSegmentMapController::class, 'store'])->name('store');
    Route::get('/{ticket_segment_map}/edit', [TicketSegmentMapController::class, 'edit'])->name('edit');
    Route::put('/{ticket_segment_map}', [TicketSegmentMapController::class, 'update'])->name('update');
    Route::delete('/{ticket_segment_map}', [TicketSegmentMapController::class, 'destroy'])->name('destroy');
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
    Route::post('/lop/{qe_lop}/complete', [EvidenceApprovalController::class, 'completeReview'])->name('lop.complete');
    Route::get('/{evidence}', [EvidenceApprovalController::class, 'show'])->name('show');
    Route::post('/{evidence}/approve', [EvidenceApprovalController::class, 'approve'])->name('approve');
    Route::post('/{evidence}/reject', [EvidenceApprovalController::class, 'reject'])->name('reject');
    Route::post('/{evidence}/reset', [EvidenceApprovalController::class, 'resetReview'])->name('reset');
});
