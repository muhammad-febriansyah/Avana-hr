<?php

use App\Enums\Role as RoleEnum;
use App\Models\Menu;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantMenuSetting;
use App\Models\User;
use App\Services\MenuService;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(MenuSeeder::class);
});

/**
 * @param  list<string>  $features
 */
function planFeatured(string $code, array $features): Plan
{
    $plan = Plan::create(['code' => $code, 'name' => ucfirst($code)]);

    foreach ($features as $feature) {
        $plan->features()->create(['feature_code' => $feature]);
    }

    return $plan;
}

function menuTreeFor(User $user): array
{
    app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

    return collect(app(MenuService::class)->forUser($user))
        ->flatMap(fn (array $node) => [
            $node['code'] => $node,
            ...collect($node['children'])->keyBy('code'),
        ])
        ->all();
}

it('DoD lets a tenant admin hide, rename, and reorder menus', function () {
    $tenant = Tenant::factory()->create([
        'plan_id' => planFeatured('professional', ['crm', 'calendar'])->id,
    ]);
    $admin = makeTenantUser($tenant, RoleEnum::CompanyAdmin->value);

    $roles = Menu::where('code', 'roles')->firstOrFail();
    $employees = Menu::where('code', 'employees')->firstOrFail();

    $this->actingAs($admin)->put('/settings/menus', [
        'items' => [
            ['menu_id' => $employees->id, 'is_visible' => true, 'label_alias' => 'SDM', 'sort_order' => 0, 'parent_id' => null, 'role_ids' => []],
            ['menu_id' => $roles->id, 'is_visible' => false, 'label_alias' => null, 'sort_order' => 1, 'parent_id' => null, 'role_ids' => []],
        ],
    ])->assertRedirect();

    $this->assertDatabaseHas('tenant_menu_settings', [
        'tenant_id' => $tenant->id, 'menu_id' => $employees->id, 'label_alias' => 'SDM',
    ]);

    $tree = menuTreeFor($admin);
    expect($tree)->not->toHaveKey('roles'); // hidden
    expect($tree['employees']['label'])->toBe('SDM'); // renamed
});

it('never hides a core menu even when asked to', function () {
    $tenant = Tenant::factory()->create([
        'plan_id' => planFeatured('professional', ['crm'])->id,
    ]);
    $admin = makeTenantUser($tenant, RoleEnum::CompanyAdmin->value);
    $dashboard = Menu::where('code', 'dashboard')->firstOrFail();

    $this->actingAs($admin)->put('/settings/menus', [
        'items' => [
            ['menu_id' => $dashboard->id, 'is_visible' => false, 'label_alias' => null, 'sort_order' => 0, 'parent_id' => null, 'role_ids' => []],
        ],
    ])->assertRedirect();

    $this->assertDatabaseHas('tenant_menu_settings', [
        'tenant_id' => $tenant->id, 'menu_id' => $dashboard->id, 'is_visible' => true,
    ]);
    expect(menuTreeFor($admin))->toHaveKey('dashboard');
});

it('resets tenant menu customizations to default', function () {
    $tenant = Tenant::factory()->create([
        'plan_id' => planFeatured('professional', ['crm'])->id,
    ]);
    $admin = makeTenantUser($tenant, RoleEnum::CompanyAdmin->value);
    $roles = Menu::where('code', 'roles')->firstOrFail();

    TenantMenuSetting::create(['tenant_id' => $tenant->id, 'menu_id' => $roles->id, 'is_visible' => false]);

    $this->actingAs($admin)->delete('/settings/menus')->assertRedirect();

    $this->assertDatabaseMissing('tenant_menu_settings', ['tenant_id' => $tenant->id]);
});

it('requires menu.manage permission for the tenant menu editor', function () {
    $tenant = Tenant::factory()->create([
        'plan_id' => planFeatured('professional', ['crm'])->id,
    ]);
    $employee = makeTenantUser($tenant, RoleEnum::Employee->value);

    $this->actingAs($employee)->get('/settings/menus')->assertForbidden();
});

it('lets a super admin create, update and delete a registry menu', function () {
    $super = User::factory()->create(['tenant_id' => null]);

    $this->actingAs($super)->post('/platform/menus', [
        'code' => 'reports.custom', 'label_default' => 'Laporan Khusus', 'icon' => null,
        'route_name' => null, 'parent_id' => null, 'permission_code' => 'reports.view',
        'feature_code' => null, 'sort_order' => 20, 'is_core' => false, 'is_active' => true,
    ])->assertRedirect();

    $menu = Menu::where('code', 'reports.custom')->firstOrFail();
    expect($menu->label_default)->toBe('Laporan Khusus');

    $this->actingAs($super)->put("/platform/menus/{$menu->id}", [
        'code' => 'reports.custom', 'label_default' => 'Laporan Baru', 'icon' => null,
        'route_name' => null, 'parent_id' => null, 'permission_code' => 'reports.view',
        'feature_code' => null, 'sort_order' => 20, 'is_core' => false, 'is_active' => true,
    ])->assertRedirect();
    expect($menu->fresh()->label_default)->toBe('Laporan Baru');

    $this->actingAs($super)->delete("/platform/menus/{$menu->id}")->assertRedirect();
    $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
});

it('lets a super admin reorder and re-parent the registry', function () {
    $super = User::factory()->create(['tenant_id' => null]);
    $payroll = Menu::where('code', 'payroll')->firstOrFail();
    $employees = Menu::where('code', 'employees')->firstOrFail();

    // Move "employees" under "payroll" at position 0.
    $this->actingAs($super)->post('/platform/menus/reorder', [
        'items' => [
            ['id' => $employees->id, 'parent_id' => $payroll->id, 'sort_order' => 0],
        ],
    ])->assertRedirect();

    $this->assertDatabaseHas('menus', [
        'id' => $employees->id, 'parent_id' => $payroll->id, 'sort_order' => 0,
    ]);
});

it('forbids tenant users from the platform menu registry', function () {
    $tenant = Tenant::factory()->create([
        'plan_id' => planFeatured('professional', ['crm'])->id,
    ]);
    $admin = makeTenantUser($tenant, RoleEnum::CompanyAdmin->value);

    $this->actingAs($admin)->get('/platform/menus')->assertForbidden();
});

it('DoD keeps out-of-plan menus hidden until a Super Admin override enables them', function () {
    // Essential plan lacks the CRM feature.
    $tenant = Tenant::factory()->create(['plan_id' => planFeatured('essential', [])->id]);
    $admin = makeTenantUser($tenant, RoleEnum::CompanyAdmin->value);
    $crm = Menu::where('code', 'crm')->firstOrFail();

    expect(menuTreeFor($admin))->not->toHaveKey('crm');

    $super = User::factory()->create(['tenant_id' => null]);
    $this->actingAs($super)->post('/platform/menus/availability', [
        'tenant_id' => $tenant->id, 'menu_id' => $crm->id, 'is_enabled' => true,
    ])->assertRedirect();

    $this->assertDatabaseHas('tenant_menu_overrides', [
        'tenant_id' => $tenant->id, 'menu_id' => $crm->id, 'is_enabled' => true,
    ]);
    expect(menuTreeFor($admin))->toHaveKey('crm'); // now available via override
});

it('DoD blocks the route by permission even if the menu were shown', function () {
    $tenant = Tenant::factory()->create([
        'plan_id' => planFeatured('professional', ['crm'])->id,
    ]);
    // Employee lacks roles.view — the Roles route must 403 regardless of menu state.
    $employee = makeTenantUser($tenant, RoleEnum::Employee->value);

    $this->actingAs($employee)->get('/roles')->assertForbidden();
});

it('restricts a menu to selected roles through the editor', function () {
    $tenant = Tenant::factory()->create([
        'plan_id' => planFeatured('professional', ['crm'])->id,
    ]);
    $admin = makeTenantUser($tenant, RoleEnum::CompanyAdmin->value);

    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $hrRole = Role::where('tenant_id', $tenant->id)->where('name', RoleEnum::HrAdmin->value)->firstOrFail();
    $reports = Menu::where('code', 'reports')->firstOrFail();

    $this->actingAs($admin)->put('/settings/menus', [
        'items' => [
            ['menu_id' => $reports->id, 'is_visible' => true, 'label_alias' => null, 'sort_order' => 0, 'parent_id' => null, 'role_ids' => [$hrRole->id]],
        ],
    ])->assertRedirect();

    // Admin (Company Admin) is not in the allowed role set → Reports hidden.
    expect(menuTreeFor($admin))->not->toHaveKey('reports');
});
