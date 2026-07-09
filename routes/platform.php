<?php

use App\Http\Controllers\Platform\MenuController;
use App\Http\Controllers\Platform\TenantController;
use Illuminate\Support\Facades\Route;

/**
 * Super Admin platform panel — cross-tenant management, gated to users with
 * `tenant_id = null` via the `platform` middleware.
 */
Route::middleware(['auth', 'verified', 'platform'])
    ->prefix('platform')
    ->name('platform.')
    ->group(function () {
        Route::redirect('/', '/platform/tenants');

        Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('tenants/create', [TenantController::class, 'create'])->name('tenants.create');
        Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::put('tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');

        Route::get('menus', [MenuController::class, 'index'])->name('menus.index');
        Route::get('menus/create', [MenuController::class, 'create'])->name('menus.create');
        Route::post('menus', [MenuController::class, 'store'])->name('menus.store');
        Route::post('menus/reorder', [MenuController::class, 'reorder'])->name('menus.reorder');
        Route::post('menus/availability', [MenuController::class, 'availability'])->name('menus.availability');
        Route::get('menus/{menu}/edit', [MenuController::class, 'edit'])->name('menus.edit');
        Route::put('menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
        Route::delete('menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');
    });
