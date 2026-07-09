<?php

use App\Enums\Role as RoleEnum;
use App\Models\LeaveType;
use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = makeTenantUser($this->tenant, RoleEnum::CompanyAdmin->value);
});

function leaveTypePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Cuti Tahunan',
        'code' => 'AL',
        'annual_quota' => 12,
        'deduct_balance' => true,
        'allow_carry_over' => false,
        'carry_over_max' => 0,
        'requires_attachment' => false,
        'min_notice_days' => 0,
        'max_consecutive_days' => null,
    ], $overrides);
}

it('creates a leave type', function () {
    $this->actingAs($this->admin)->post('/leave-types', leaveTypePayload())->assertRedirect();

    $this->assertDatabaseHas('leave_types', [
        'tenant_id' => $this->tenant->id,
        'code' => 'AL',
        'annual_quota' => 12,
        'deduct_balance' => true,
    ]);
});

it('rejects a duplicate code within the tenant', function () {
    LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'AL']);

    $this->actingAs($this->admin)
        ->post('/leave-types', leaveTypePayload(['code' => 'AL']))
        ->assertSessionHasErrors('code');
});

it('updates and deletes a leave type', function () {
    $type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->put("/leave-types/{$type->id}", leaveTypePayload(['code' => $type->code, 'deduct_balance' => false]))
        ->assertRedirect();
    expect($type->fresh()->deduct_balance)->toBeFalse();

    $this->actingAs($this->admin)->delete("/leave-types/{$type->id}")->assertRedirect();
    $this->assertSoftDeleted('leave_types', ['id' => $type->id]);
});

it('forbids an employee-role user from managing leave types', function () {
    $user = makeTenantUser($this->tenant, RoleEnum::Employee->value);

    $this->actingAs($user)->post('/leave-types', leaveTypePayload())->assertForbidden();
});
