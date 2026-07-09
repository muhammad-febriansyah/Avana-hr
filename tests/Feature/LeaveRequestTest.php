<?php

use App\Enums\Role as RoleEnum;
use App\Models\ApprovalFlow;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->approver = makeTenantUser($this->tenant, RoleEnum::Manager->value);
    $this->employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->requester = makeTenantUser($this->tenant, RoleEnum::Employee->value);
    $this->requester->update(['employee_id' => $this->employee->id]);
    $this->type = LeaveType::factory()->create([
        'tenant_id' => $this->tenant->id, 'annual_quota' => 12, 'deduct_balance' => true,
    ]);
});

function leaveFlow(Tenant $tenant, User $approver): void
{
    $flow = ApprovalFlow::create([
        'tenant_id' => $tenant->id, 'approvable_type' => 'leave_request', 'name' => 'Cuti', 'is_active' => true,
    ]);
    $flow->steps()->create(['seq' => 1, 'approver_type' => 'user', 'approver_id' => $approver->id]);
}

function balanceFor(int $employeeId, int $typeId): ?LeaveBalance
{
    return LeaveBalance::where('employee_id', $employeeId)
        ->where('leave_type_id', $typeId)->where('year', 2026)->first();
}

it('QA-0026 reserves pending balance when a request is submitted', function () {
    leaveFlow($this->tenant, $this->approver);

    $this->actingAs($this->requester)->post('/leave', [
        'leave_type_id' => $this->type->id,
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-12',
    ])->assertRedirect('/leave');

    $leave = LeaveRequest::firstOrFail();
    expect($leave->status)->toBe('pending');
    expect($leave->total_days)->toBe(3.0);

    $balance = balanceFor($this->employee->id, $this->type->id);
    expect($balance->pending)->toBe(3.0);
    expect($balance->available())->toBe(9.0);   // 12 - 3 pending
});

it('QA-0027 rejects a request exceeding the available balance', function () {
    leaveFlow($this->tenant, $this->approver);
    $this->type->update(['annual_quota' => 2]);

    $this->actingAs($this->requester)->post('/leave', [
        'leave_type_id' => $this->type->id,
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-12',
    ])->assertSessionHasErrors('leave_type_id');

    expect(LeaveRequest::count())->toBe(0);
});

it('QA-0027 allows an over-quota unpaid (non-deducting) leave type', function () {
    leaveFlow($this->tenant, $this->approver);
    $unpaid = LeaveType::factory()->create([
        'tenant_id' => $this->tenant->id, 'annual_quota' => 0, 'deduct_balance' => false, 'code' => 'UNP',
    ]);

    $this->actingAs($this->requester)->post('/leave', [
        'leave_type_id' => $unpaid->id,
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-20',
    ])->assertRedirect('/leave');

    expect(LeaveRequest::where('leave_type_id', $unpaid->id)->exists())->toBeTrue();
    expect(balanceFor($this->employee->id, $unpaid->id))->toBeNull();
});

it('E2E-0150 moves pending to used on approval', function () {
    leaveFlow($this->tenant, $this->approver);

    $this->actingAs($this->requester)->post('/leave', [
        'leave_type_id' => $this->type->id, 'start_date' => '2026-08-10', 'end_date' => '2026-08-12',
    ]);
    $approval = LeaveRequest::firstOrFail()->approvals()->firstOrFail();

    $this->actingAs($this->approver)->post("/approvals/{$approval->id}/approve")->assertRedirect();

    $balance = balanceFor($this->employee->id, $this->type->id);
    expect($balance->pending)->toBe(0.0);
    expect($balance->used)->toBe(3.0);
    expect(LeaveRequest::firstOrFail()->status)->toBe('approved');
});

it('E2E-0150 returns pending balance when a pending request is cancelled', function () {
    leaveFlow($this->tenant, $this->approver);

    $this->actingAs($this->requester)->post('/leave', [
        'leave_type_id' => $this->type->id, 'start_date' => '2026-08-10', 'end_date' => '2026-08-12',
    ]);
    $leave = LeaveRequest::firstOrFail();

    $this->actingAs($this->requester)->post("/leave/{$leave->id}/cancel")->assertRedirect();

    $balance = balanceFor($this->employee->id, $this->type->id);
    expect($balance->pending)->toBe(0.0);
    expect($leave->fresh()->status)->toBe('cancelled');
    expect($leave->approvals()->where('status', 'cancelled')->exists())->toBeTrue();
});

it('E2E-0150 returns used balance when an approved request is cancelled', function () {
    leaveFlow($this->tenant, $this->approver);

    $this->actingAs($this->requester)->post('/leave', [
        'leave_type_id' => $this->type->id, 'start_date' => '2026-08-10', 'end_date' => '2026-08-12',
    ]);
    $leave = LeaveRequest::firstOrFail();
    $approval = $leave->approvals()->firstOrFail();
    $this->actingAs($this->approver)->post("/approvals/{$approval->id}/approve");

    $this->actingAs($this->requester)->post("/leave/{$leave->id}/cancel")->assertRedirect();

    expect(balanceFor($this->employee->id, $this->type->id)->used)->toBe(0.0);
    expect($leave->fresh()->status)->toBe('cancelled');
});

it('returns pending balance when a request is rejected', function () {
    leaveFlow($this->tenant, $this->approver);

    $this->actingAs($this->requester)->post('/leave', [
        'leave_type_id' => $this->type->id, 'start_date' => '2026-08-10', 'end_date' => '2026-08-12',
    ]);
    $approval = LeaveRequest::firstOrFail()->approvals()->firstOrFail();

    $this->actingAs($this->approver)->post("/approvals/{$approval->id}/reject")->assertRedirect();

    expect(balanceFor($this->employee->id, $this->type->id)->pending)->toBe(0.0);
    expect(LeaveRequest::firstOrFail()->status)->toBe('rejected');
});

it('excludes holidays from the counted leave days', function () {
    leaveFlow($this->tenant, $this->approver);
    Holiday::factory()->create(['tenant_id' => null, 'date' => '2026-08-11']);

    $this->actingAs($this->requester)->post('/leave', [
        'leave_type_id' => $this->type->id, 'start_date' => '2026-08-10', 'end_date' => '2026-08-12',
    ]);

    // 3 calendar days minus 1 holiday = 2 counted.
    expect(LeaveRequest::firstOrFail()->total_days)->toBe(2.0);
});

it('forbids a user without a linked employee from requesting', function () {
    leaveFlow($this->tenant, $this->approver);
    $orphan = makeTenantUser($this->tenant, RoleEnum::Employee->value);

    $this->actingAs($orphan)->post('/leave', [
        'leave_type_id' => $this->type->id, 'start_date' => '2026-08-10', 'end_date' => '2026-08-12',
    ])->assertForbidden();
});

it('forbids cancelling another employee request', function () {
    $leave = LeaveRequest::factory()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id,
        'leave_type_id' => $this->type->id, 'status' => 'pending',
    ]);
    $other = makeTenantUser($this->tenant, RoleEnum::Employee->value);
    $other->update(['employee_id' => Employee::factory()->create(['tenant_id' => $this->tenant->id])->id]);

    $this->actingAs($other)->post("/leave/{$leave->id}/cancel")->assertForbidden();
});
