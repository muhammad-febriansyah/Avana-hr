<?php

use App\Actions\Tenant\ProvisionTenantDefaults;
use App\Enums\Role as RoleEnum;
use App\Models\Menu;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantMenuSetting;
use App\Services\MenuService;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->service = app(MenuService::class);
});

/**
 * @param  list<string>  $features
 */
function planWith(string $code, array $features): Plan
{
    $plan = Plan::create(['code' => $code, 'name' => ucfirst($code)]);

    foreach ($features as $feature) {
        $plan->features()->create(['feature_code' => $feature]);
    }

    return $plan;
}

/**
 * Flatten a menu tree to a code=>node map for easy assertions.
 *
 * @param  list<array<string, mixed>>  $tree
 * @return array<string, array<string, mixed>>
 */
function flattenMenu(array $tree): array
{
    $flat = [];
    foreach ($tree as $node) {
        $flat[$node['code']] = $node;
        $flat += flattenMenu($node['children']);
    }

    return $flat;
}

it('builds a nested tree with resolved urls for a company admin', function () {
    $plan = planWith('professional', ['crm', 'calendar']);
    $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
    $admin = makeTenantUser($tenant, RoleEnum::CompanyAdmin->value);

    $tree = flattenMenu($this->service->forUser($admin));

    expect($tree)->toHaveKeys(['dashboard', 'payroll', 'payroll.process', 'roles']);
    expect($tree['payroll']['children'])->not->toBeEmpty();
    expect($tree['roles']['url'])->toContain('/roles');
});

it('hides menus whose feature is outside the tenant plan', function () {
    $essential = planWith('essential', []);
    $tenant = Tenant::factory()->create(['plan_id' => $essential->id]);
    $admin = makeTenantUser($tenant, RoleEnum::CompanyAdmin->value);

    $tree = flattenMenu($this->service->forUser($admin));

    // CRM/Calendar are feature-gated and not in the essential plan.
    expect($tree)->not->toHaveKey('crm');
    expect($tree)->not->toHaveKey('calendar');
    expect($tree)->toHaveKey('dashboard');
});

it('hides a menu the tenant marked not visible, but never a core menu', function () {
    $plan = planWith('professional', ['crm', 'calendar']);
    $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
    $admin = makeTenantUser($tenant, RoleEnum::CompanyAdmin->value);

    $rolesMenu = Menu::where('code', 'roles')->firstOrFail();
    $dashboard = Menu::where('code', 'dashboard')->firstOrFail();

    TenantMenuSetting::create(['tenant_id' => $tenant->id, 'menu_id' => $rolesMenu->id, 'is_visible' => false]);
    TenantMenuSetting::create(['tenant_id' => $tenant->id, 'menu_id' => $dashboard->id, 'is_visible' => false]);

    $tree = flattenMenu($this->service->forUser($admin));

    expect($tree)->not->toHaveKey('roles');
    expect($tree)->toHaveKey('dashboard'); // is_core cannot be hidden
});

it('applies a tenant label alias', function () {
    $plan = planWith('professional', ['crm', 'calendar']);
    $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
    $admin = makeTenantUser($tenant, RoleEnum::CompanyAdmin->value);

    $employees = Menu::where('code', 'employees')->firstOrFail();
    TenantMenuSetting::create([
        'tenant_id' => $tenant->id,
        'menu_id' => $employees->id,
        'is_visible' => true,
        'label_alias' => 'SDM',
    ]);

    $tree = flattenMenu($this->service->forUser($admin));

    expect($tree['employees']['label'])->toBe('SDM');
});

it('filters menus the user has no permission for', function () {
    $plan = planWith('professional', ['crm', 'calendar']);
    $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
    $employee = makeTenantUser($tenant, RoleEnum::Employee->value);

    $tree = flattenMenu($this->service->forUser($employee));

    // Employee lacks roles.view / audit.view / approval.manage-flows.
    expect($tree)->not->toHaveKey('roles');
    expect($tree)->not->toHaveKey('audit');
    expect($tree)->not->toHaveKey('approval-workflow');
});

it('invalidates the cached menu on a database cache store after a write', function () {
    // The database store no-ops Cache::increment on a missing key, so this
    // guards the versioned-cache invalidation across cache drivers.
    config(['cache.default' => 'database']);
    Cache::clear();

    $plan = planWith('professional', ['crm', 'calendar']);
    $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
    $admin = makeTenantUser($tenant, RoleEnum::CompanyAdmin->value);

    // First resolve caches the tree with Roles visible.
    expect(flattenMenu($this->service->forUser($admin)))->toHaveKey('roles');

    // Hiding it must invalidate the cache (observer -> flushTenant).
    $roles = Menu::where('code', 'roles')->firstOrFail();
    TenantMenuSetting::create(['tenant_id' => $tenant->id, 'menu_id' => $roles->id, 'is_visible' => false]);

    expect(flattenMenu($this->service->forUser($admin)))->not->toHaveKey('roles');
});

it('previews the tree for a specific tenant role without an actual user', function () {
    $plan = planWith('professional', ['crm', 'calendar']);
    $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
    app(ProvisionTenantDefaults::class)->handle($tenant);

    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $employeeRole = Role::where('tenant_id', $tenant->id)->where('name', RoleEnum::Employee->value)->firstOrFail();
    $adminRole = Role::where('tenant_id', $tenant->id)->where('name', RoleEnum::CompanyAdmin->value)->firstOrFail();

    $asEmployee = flattenMenu($this->service->previewForTenantRole($tenant, $employeeRole));
    expect($asEmployee)->not->toHaveKey('roles');
    expect($asEmployee)->not->toHaveKey('audit');

    $asAdmin = flattenMenu($this->service->previewForTenantRole($tenant, $adminRole));
    expect($asAdmin)->toHaveKey('roles');
});

it('restricts a menu to specific roles via role visibility', function () {
    $plan = planWith('professional', ['crm', 'calendar']);
    $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
    $admin = makeTenantUser($tenant, RoleEnum::CompanyAdmin->value);

    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $hrRole = Role::where('tenant_id', $tenant->id)->where('name', RoleEnum::HrAdmin->value)->firstOrFail();

    $reports = Menu::where('code', 'reports')->firstOrFail();
    $setting = TenantMenuSetting::create(['tenant_id' => $tenant->id, 'menu_id' => $reports->id, 'is_visible' => true]);
    // Only the HR Admin role may see Reports; the admin holds Company Admin.
    $setting->roleVisibilities()->create(['role_id' => $hrRole->id]);

    $tree = flattenMenu($this->service->forUser($admin));

    expect($tree)->not->toHaveKey('reports');
});
