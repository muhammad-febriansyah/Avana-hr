<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ApprovalFlowController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CustomFieldController;
use App\Http\Controllers\EmployeeChangeRequestController;
use App\Http\Controllers\EmployeeContractController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeImportController;
use App\Http\Controllers\EmployeeMovementController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrgUnitController;
use App\Http\Controllers\PositionController;
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

    Route::get('employees', [EmployeeController::class, 'index'])
        ->middleware('can:employees.view')
        ->name('employees.index');

    // Custom field definitions — registered before the {employee} wildcard.
    Route::middleware('can:employees.update')->group(function () {
        Route::get('employees/custom-fields', [CustomFieldController::class, 'index'])->name('employees.custom-fields.index');
        Route::post('employees/custom-fields', [CustomFieldController::class, 'store'])->name('employees.custom-fields.store');
        Route::put('employees/custom-fields/{customField}', [CustomFieldController::class, 'update'])->name('employees.custom-fields.update');
        Route::delete('employees/custom-fields/{customField}', [CustomFieldController::class, 'destroy'])->name('employees.custom-fields.destroy');
    });

    Route::middleware('can:employees.create')->group(function () {
        Route::get('employees/create', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');

        // Bulk import (Excel/CSV). Deeper paths — no clash with the {employee} wildcard.
        Route::get('employees/import/template', [EmployeeImportController::class, 'template'])->name('employees.import.template');
        Route::post('employees/import', [EmployeeImportController::class, 'store'])->name('employees.import.store');
        Route::get('employees/import/exceptions/{token}', [EmployeeImportController::class, 'exceptions'])->name('employees.import.exceptions');
    });
    Route::middleware('can:employees.update')->group(function () {
        Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    });
    Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])
        ->middleware('can:employees.delete')
        ->name('employees.destroy');

    // Employee contracts — managed from the employee detail page.
    Route::middleware('can:employees.update')->scopeBindings()->group(function () {
        Route::post('employees/{employee}/contracts', [EmployeeContractController::class, 'store'])->name('employees.contracts.store');
        Route::put('employees/{employee}/contracts/{contract}', [EmployeeContractController::class, 'update'])->name('employees.contracts.update');
        Route::delete('employees/{employee}/contracts/{contract}', [EmployeeContractController::class, 'destroy'])->name('employees.contracts.destroy');
    });
    Route::get('employees/{employee}/contracts/{contract}/download', [EmployeeContractController::class, 'download'])
        ->middleware('can:employees.view')
        ->scopeBindings()
        ->name('employees.contracts.download');

    // Employee lifecycle: movements + data change requests (both via approval).
    Route::middleware('can:employees.update')->group(function () {
        Route::post('employees/{employee}/movements', [EmployeeMovementController::class, 'store'])->name('employees.movements.store');
        Route::post('employees/{employee}/change-requests', [EmployeeChangeRequestController::class, 'store'])->name('employees.change-requests.store');
    });

    // Keep the wildcard show route last so /employees/create resolves first.
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])
        ->middleware('can:employees.view')
        ->name('employees.show');

    Route::get('organization', [OrganizationController::class, 'index'])
        ->middleware('can:organization.view')
        ->name('organization.index');

    Route::middleware('can:organization.manage')->group(function () {
        Route::post('org-units', [OrgUnitController::class, 'store'])->name('org-units.store');
        Route::put('org-units/{orgUnit}', [OrgUnitController::class, 'update'])->name('org-units.update');
        Route::delete('org-units/{orgUnit}', [OrgUnitController::class, 'destroy'])->name('org-units.destroy');

        Route::post('grades', [GradeController::class, 'store'])->name('grades.store');
        Route::put('grades/{grade}', [GradeController::class, 'update'])->name('grades.update');
        Route::delete('grades/{grade}', [GradeController::class, 'destroy'])->name('grades.destroy');

        Route::post('positions', [PositionController::class, 'store'])->name('positions.store');
        Route::put('positions/{position}', [PositionController::class, 'update'])->name('positions.update');
        Route::delete('positions/{position}', [PositionController::class, 'destroy'])->name('positions.destroy');
    });

    Route::get('branches', [BranchController::class, 'index'])
        ->middleware('can:branches.view')
        ->name('branches.index');

    Route::middleware('can:branches.manage')->group(function () {
        Route::get('branches/create', [BranchController::class, 'create'])->name('branches.create');
        Route::post('branches', [BranchController::class, 'store'])->name('branches.store');
        Route::get('branches/{branch}/edit', [BranchController::class, 'edit'])->name('branches.edit');
        Route::put('branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
        Route::delete('branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');
    });

    Route::get('roles', [RoleController::class, 'index'])
        ->middleware('can:roles.view')
        ->name('roles.index');

    Route::middleware('can:roles.manage')->group(function () {
        Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
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
