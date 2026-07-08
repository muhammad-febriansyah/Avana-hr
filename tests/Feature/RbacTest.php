<?php

use App\Actions\Tenant\ProvisionTenantDefaults;
use App\Enums\Role as RoleEnum;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->registrar = app(PermissionRegistrar::class);
});

/**
 * Provision a tenant with default roles and return a user holding $role.
 */
function tenantUserWithRole(Tenant $tenant, string $role): User
{
    app(ProvisionTenantDefaults::class)->handle($tenant);

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->assignRole($role);

    return $user;
}

it('grants company admin all tenant permissions but no platform ones', function () {
    $tenant = Tenant::factory()->create();
    $admin = tenantUserWithRole($tenant, RoleEnum::CompanyAdmin->value);

    $this->registrar->setPermissionsTeamId($tenant->id);

    expect($admin->can('payroll.approve'))->toBeTrue();
    expect($admin->can('employees.create'))->toBeTrue();
    expect($admin->can('platform.tenants.manage'))->toBeFalse();
});

it('limits employee role to self-service permissions', function () {
    $tenant = Tenant::factory()->create();
    $employee = tenantUserWithRole($tenant, RoleEnum::Employee->value);

    $this->registrar->setPermissionsTeamId($tenant->id);

    expect($employee->can('leave.request'))->toBeTrue();
    expect($employee->can('payroll.approve'))->toBeFalse();
    expect($employee->can('employees.create'))->toBeFalse();
});

it('lets super admin (tenant_id null) bypass every check', function () {
    $super = User::factory()->create(['tenant_id' => null]);

    expect($super->can('payroll.lock'))->toBeTrue();
    expect($super->can('platform.tenants.manage'))->toBeTrue();
});

it('scopes roles per tenant with no cross-tenant leak', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $adminA = tenantUserWithRole($a, RoleEnum::CompanyAdmin->value);
    app(ProvisionTenantDefaults::class)->handle($b);

    // Under tenant B's context, tenant A's admin holds no role.
    $this->registrar->setPermissionsTeamId($b->id);
    expect($adminA->fresh()->hasRole(RoleEnum::CompanyAdmin->value))->toBeFalse();

    // Under its own tenant, the role applies.
    $this->registrar->setPermissionsTeamId($a->id);
    expect($adminA->fresh()->hasRole(RoleEnum::CompanyAdmin->value))->toBeTrue();
});

it('provisions exactly the five default roles per tenant', function () {
    $tenant = Tenant::factory()->create();
    app(ProvisionTenantDefaults::class)->handle($tenant);

    $this->registrar->setPermissionsTeamId($tenant->id);

    expect(Role::where('tenant_id', $tenant->id)->count())->toBe(5);
});
