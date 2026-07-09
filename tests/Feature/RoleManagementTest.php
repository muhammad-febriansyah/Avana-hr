<?php

use App\Enums\Role as RoleEnum;
use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

it('QA-0113 lets a view-only role open the page but not mutate roles', function () {
    $tenant = Tenant::factory()->create();
    // HR Admin holds roles.view but not roles.manage.
    $viewer = makeTenantUser($tenant, RoleEnum::HrAdmin->value);

    $this->actingAs($viewer)->get('/roles')->assertOk();

    $this->actingAs($viewer)
        ->post('/roles', ['name' => 'Custom', 'permissions' => []])
        ->assertForbidden();

    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $role = Role::create(['name' => 'Existing', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

    $this->actingAs($viewer)
        ->put("/roles/{$role->id}", ['name' => 'Renamed', 'permissions' => []])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->delete("/roles/{$role->id}")
        ->assertForbidden();
});

it('lets a manager create a custom role with tenant permissions', function () {
    $tenant = Tenant::factory()->create();
    $admin = makeTenantUser($tenant, RoleEnum::CompanyAdmin->value);

    $this->actingAs($admin)->post('/roles', [
        'name' => 'Payroll Admin',
        'permissions' => ['payroll.view', 'payroll.process'],
    ])->assertRedirect();

    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $role = Role::with('permissions')
        ->where('tenant_id', $tenant->id)->where('name', 'Payroll Admin')->first();

    expect($role)->not->toBeNull();
    expect($role->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['payroll.view', 'payroll.process']);
});

it('rejects platform permissions on tenant roles', function () {
    $tenant = Tenant::factory()->create();
    $admin = makeTenantUser($tenant, RoleEnum::CompanyAdmin->value);

    $this->actingAs($admin)->post('/roles', [
        'name' => 'Sneaky',
        'permissions' => ['platform.tenants.manage'],
    ])->assertSessionHasErrors('permissions.0');
});

it('forbids deleting a default role but allows editing its permissions', function () {
    $tenant = Tenant::factory()->create();
    $admin = makeTenantUser($tenant, RoleEnum::CompanyAdmin->value);

    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $manager = Role::where('tenant_id', $tenant->id)->where('name', RoleEnum::Manager->value)->first();

    $this->actingAs($admin)->delete("/roles/{$manager->id}")->assertRedirect();
    expect(Role::find($manager->id))->not->toBeNull();

    $this->actingAs($admin)->put("/roles/{$manager->id}", [
        'name' => 'Should Be Ignored',
        'permissions' => ['leave.approve'],
    ])->assertRedirect();

    $manager->refresh()->load('permissions');
    expect($manager->name)->toBe(RoleEnum::Manager->value);
    expect($manager->permissions->pluck('name')->all())->toBe(['leave.approve']);
});
