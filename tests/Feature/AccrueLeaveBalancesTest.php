<?php

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Tenant;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'annual_quota' => 12]);
});

it('accrues the full quota for an employee who joined before the year', function () {
    $employee = Employee::factory()->create([
        'tenant_id' => $this->tenant->id, 'status' => 'active', 'join_date' => '2024-03-01',
    ]);

    $this->artisan('leave:accrue', ['year' => 2026])->assertSuccessful();

    $balance = LeaveBalance::where('employee_id', $employee->id)->firstOrFail();
    expect($balance->entitled)->toBe(12.0);
    expect($balance->year)->toBe(2026);
});

it('prorates the quota for a mid-year joiner', function () {
    $employee = Employee::factory()->create([
        'tenant_id' => $this->tenant->id, 'status' => 'active', 'join_date' => '2026-07-15',
    ]);

    $this->artisan('leave:accrue', ['year' => 2026])->assertSuccessful();

    // July..December = 6 months -> 12 * 6/12 = 6.0
    expect(LeaveBalance::where('employee_id', $employee->id)->value('entitled'))->toBe(6.0);
});

it('preserves used and pending days when re-run', function () {
    $employee = Employee::factory()->create([
        'tenant_id' => $this->tenant->id, 'status' => 'active', 'join_date' => '2024-01-01',
    ]);
    LeaveBalance::factory()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id,
        'leave_type_id' => $this->type->id, 'year' => 2026,
        'entitled' => 12, 'used' => 5, 'pending' => 2,
    ]);

    $this->artisan('leave:accrue', ['year' => 2026])->assertSuccessful();

    $balance = LeaveBalance::where('employee_id', $employee->id)->firstOrFail();
    expect($balance->used)->toBe(5.0);
    expect($balance->pending)->toBe(2.0);
    expect(LeaveBalance::where('employee_id', $employee->id)->count())->toBe(1);
});

it('skips inactive employees', function () {
    Employee::factory()->create([
        'tenant_id' => $this->tenant->id, 'status' => 'inactive', 'join_date' => '2024-01-01',
    ]);

    $this->artisan('leave:accrue', ['year' => 2026])->assertSuccessful();

    expect(LeaveBalance::count())->toBe(0);
});
