<?php

use App\Enums\Role as RoleEnum;
use App\Models\PayrollGroup;
use App\Models\SalaryComponent;
use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = makeTenantUser($this->tenant, RoleEnum::CompanyAdmin->value);
});

function groupPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'MID',
        'name' => 'Payroll Mid-Month',
        'frequency' => 'monthly',
        'period_start_day' => 1,
        'cutoff_day' => 15,
        'attendance_source' => 'current',
        'overtime_source' => 'current',
        'prorate_method' => 'calendar',
        'is_active' => true,
        'component_ids' => [],
    ], $overrides);
}

it('seeds a default monthly group with all components on provision', function () {
    $group = PayrollGroup::where('code', 'MONTHLY')->firstOrFail();
    expect($group->frequency)->toBe('monthly');
    expect($group->components()->count())->toBe(8); // the 8 standard components
});

it('creates a payroll group with attached components', function () {
    $componentIds = SalaryComponent::where('tenant_id', $this->tenant->id)->pluck('id')->take(3)->all();

    $this->actingAs($this->admin)
        ->post('/payroll-groups', groupPayload(['component_ids' => $componentIds]))
        ->assertRedirect();

    $group = PayrollGroup::where('code', 'MID')->firstOrFail();
    expect($group->components()->count())->toBe(3);
});

it('rejects a duplicate code within the tenant', function () {
    $this->actingAs($this->admin)
        ->post('/payroll-groups', groupPayload(['code' => 'MONTHLY']))
        ->assertSessionHasErrors('code');
});

it('syncs components on update', function () {
    $group = PayrollGroup::factory()->create(['tenant_id' => $this->tenant->id]);
    $ids = SalaryComponent::where('tenant_id', $this->tenant->id)->pluck('id')->take(2)->all();

    $this->actingAs($this->admin)
        ->put("/payroll-groups/{$group->id}", groupPayload(['code' => $group->code, 'component_ids' => $ids]))
        ->assertRedirect();

    expect($group->fresh()->components()->count())->toBe(2);
});

it('deletes a payroll group', function () {
    $group = PayrollGroup::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)->delete("/payroll-groups/{$group->id}")->assertRedirect();
    $this->assertSoftDeleted('payroll_groups', ['id' => $group->id]);
});

it('forbids an employee-role user from managing groups', function () {
    $user = makeTenantUser($this->tenant, RoleEnum::Employee->value);

    $this->actingAs($user)->post('/payroll-groups', groupPayload())->assertForbidden();
});
