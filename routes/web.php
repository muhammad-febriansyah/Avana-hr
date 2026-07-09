<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ApprovalFlowController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('audit', [AuditLogController::class, 'index'])
        ->middleware('can:audit.view')
        ->name('audit.index');

    Route::middleware('can:approval.act')->group(function () {
        Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::post('approvals/{approval}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('approvals/{approval}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
    });

    Route::middleware('can:approval.manage-flows')->group(function () {
        Route::get('approval-workflow', [ApprovalFlowController::class, 'index'])->name('approval-workflow.index');
        Route::post('approval-workflow', [ApprovalFlowController::class, 'store'])->name('approval-workflow.store');
        Route::delete('approval-workflow/{approvalFlow}', [ApprovalFlowController::class, 'destroy'])->name('approval-workflow.destroy');
    });

    Route::get('roles', [RoleController::class, 'index'])
        ->middleware('can:roles.view')
        ->name('roles.index');

    Route::middleware('can:roles.manage')->group(function () {
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });
});

// Shared UI showcase (Design System 05 Bagian C) — non-production only.
if (! app()->isProduction()) {
    Route::inertia('dev/components', 'dev/components')->name('dev.components');
}

require __DIR__.'/platform.php';
require __DIR__.'/settings.php';
