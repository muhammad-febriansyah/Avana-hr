<?php

use App\Enums\Role as RoleEnum;
use App\Models\ApprovalFlow;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

it('QA-0111 returns 404 and logs a security event on cross-tenant access', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = makeTenantUser($tenantA, RoleEnum::CompanyAdmin->value);
    makeTenantUser($tenantB); // provision B's roles

    // A flow that belongs to tenant B (created without auth → tenant_id stays B).
    $flowB = ApprovalFlow::create([
        'tenant_id' => $tenantB->id,
        'approvable_type' => 'leave_request',
        'name' => 'B only flow',
        'is_active' => true,
    ]);

    $this->actingAs($userA)
        ->delete("/approval-workflow/{$flowB->id}")
        ->assertNotFound();

    // Flow untouched, attempt recorded under the attacker's tenant.
    $this->assertDatabaseHas('approval_flows', ['id' => $flowB->id]);
    $this->assertDatabaseHas('security_logs', [
        'event' => 'cross_tenant_access_denied',
        'tenant_id' => $tenantA->id,
        'user_id' => $userA->id,
    ]);
});

it('does not log a security event for a genuinely missing record', function () {
    $tenant = Tenant::factory()->create();
    $user = makeTenantUser($tenant, RoleEnum::CompanyAdmin->value);

    $this->actingAs($user)
        ->delete('/approval-workflow/999999')
        ->assertNotFound();

    $this->assertDatabaseMissing('security_logs', [
        'event' => 'cross_tenant_access_denied',
    ]);
});

it('QA-0112 provisions a new tenant with isolated, empty data via the platform panel', function () {
    // Existing tenant with its own admin — must stay isolated from the new one.
    $tenantA = Tenant::factory()->create();
    makeTenantUser($tenantA, RoleEnum::CompanyAdmin->value);

    $super = User::factory()->create(['tenant_id' => null]);
    $plan = Plan::create(['code' => 'starter', 'name' => 'Starter']);

    $this->actingAs($super)->post('/platform/tenants', [
        'name' => 'Fresh Corp',
        'slug' => 'fresh-corp',
        'plan_id' => $plan->id,
        'employee_id_prefix' => 'FRSH',
        'admin_name' => 'Fresh Admin',
        'admin_email' => 'admin@fresh.test',
        'admin_password' => 'password123',
    ])->assertRedirect();

    $fresh = Tenant::where('slug', 'fresh-corp')->firstOrFail();

    // Exactly the five default roles, scoped to the new tenant.
    app(PermissionRegistrar::class)->setPermissionsTeamId($fresh->id);
    expect(Role::where('tenant_id', $fresh->id)->count())->toBe(5);

    // Only its own admin, no leaked users or employees.
    expect(User::where('tenant_id', $fresh->id)->count())->toBe(1);
    expect($fresh->employees()->count())->toBe(0);

    $admin = User::where('tenant_id', $fresh->id)->firstOrFail();
    expect($admin->hasRole(RoleEnum::CompanyAdmin->value))->toBeTrue();
});

it('forbids tenant users from the platform panel and allows super admins', function () {
    $tenant = Tenant::factory()->create();
    $tenantUser = makeTenantUser($tenant, RoleEnum::CompanyAdmin->value);
    $super = User::factory()->create(['tenant_id' => null]);

    $this->actingAs($tenantUser)->get('/platform/tenants')->assertForbidden();
    $this->actingAs($super)->get('/platform/tenants')->assertOk();
});
