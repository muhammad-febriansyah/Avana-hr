<?php

use App\Enums\Role as RoleEnum;
use App\Models\AttendanceEvent;
use App\Models\AttendanceSummary;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = makeTenantUser($this->tenant, RoleEnum::CompanyAdmin->value);
    $this->employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
    $this->shift = Shift::factory()->create([
        'tenant_id' => $this->tenant->id, 'start_time' => '08:00', 'end_time' => '17:00',
        'late_tolerance_min' => 15, 'break_minutes' => 60,
    ]);
});

function scheduleFor(int $tenantId, int $employeeId, ?int $shiftId, bool $dayOff = false): void
{
    EmployeeSchedule::factory()->create([
        'tenant_id' => $tenantId, 'employee_id' => $employeeId,
        'date' => '2026-08-10', 'shift_id' => $shiftId, 'is_day_off' => $dayOff, 'source' => 'generated',
    ]);
}

function punch(int $tenantId, int $employeeId, string $time, string $type): void
{
    AttendanceEvent::factory()->create([
        'tenant_id' => $tenantId, 'employee_id' => $employeeId,
        'event_uuid' => md5("{$employeeId}|2026-08-10 {$time}|{$type}"),
        'type' => $type, 'occurred_at' => "2026-08-10 {$time}", 'channel' => 'mobile_face',
    ]);
}

function rebuild($test): void
{
    $test->actingAs($test->admin)->post('/attendance/rebuild', ['date' => '2026-08-10'])->assertRedirect();
}

it('QA-0017 marks an on-time employee present with work minutes', function () {
    scheduleFor($this->tenant->id, $this->employee->id, $this->shift->id);
    punch($this->tenant->id, $this->employee->id, '08:01:00', 'in');
    punch($this->tenant->id, $this->employee->id, '17:05:00', 'out');

    rebuild($this);

    $summary = AttendanceSummary::where('employee_id', $this->employee->id)->firstOrFail();
    expect($summary->status)->toBe('present');
    expect($summary->late_minutes)->toBe(0);
    expect($summary->work_minutes)->toBe(484); // 08:01..17:05 = 544m - 60 break
});

it('QA-0017 flags a late arrival with late minutes', function () {
    scheduleFor($this->tenant->id, $this->employee->id, $this->shift->id);
    punch($this->tenant->id, $this->employee->id, '08:30:00', 'in');
    punch($this->tenant->id, $this->employee->id, '17:00:00', 'out');

    rebuild($this);

    $summary = AttendanceSummary::where('employee_id', $this->employee->id)->firstOrFail();
    expect($summary->status)->toBe('late');
    expect($summary->late_minutes)->toBe(30); // 08:00 -> 08:30
});

it('QA-0017 marks a scheduled employee with no punches absent', function () {
    scheduleFor($this->tenant->id, $this->employee->id, $this->shift->id);

    rebuild($this);

    expect(AttendanceSummary::where('employee_id', $this->employee->id)->value('status'))->toBe('absent');
});

it('marks a day-off schedule as day_off', function () {
    scheduleFor($this->tenant->id, $this->employee->id, null, dayOff: true);

    rebuild($this);

    expect(AttendanceSummary::where('employee_id', $this->employee->id)->value('status'))->toBe('day_off');
});

it('marks a holiday date as holiday', function () {
    scheduleFor($this->tenant->id, $this->employee->id, $this->shift->id);
    Holiday::factory()->create(['tenant_id' => null, 'date' => '2026-08-10']);

    rebuild($this);

    expect(AttendanceSummary::where('employee_id', $this->employee->id)->value('status'))->toBe('holiday');
});

it('marks an approved-leave date as leave', function () {
    scheduleFor($this->tenant->id, $this->employee->id, $this->shift->id);
    $leaveTypeId = DB::table('leave_types')->insertGetId([
        'tenant_id' => $this->tenant->id, 'name' => 'Cuti', 'code' => 'AL', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('leave_requests')->insert([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id, 'leave_type_id' => $leaveTypeId,
        'start_date' => '2026-08-10', 'end_date' => '2026-08-10', 'total_days' => 1, 'status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    rebuild($this);

    expect(AttendanceSummary::where('employee_id', $this->employee->id)->value('status'))->toBe('leave');
});

it('does not overwrite a locked summary', function () {
    scheduleFor($this->tenant->id, $this->employee->id, $this->shift->id);
    AttendanceSummary::factory()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id,
        'date' => '2026-08-10', 'status' => 'present', 'is_locked' => true,
    ]);
    punch($this->tenant->id, $this->employee->id, '08:30:00', 'in');

    rebuild($this);

    // Stays 'present' (locked), not recomputed to 'late'.
    expect(AttendanceSummary::where('employee_id', $this->employee->id)->value('status'))->toBe('present');
});

it('QA-0021 rejects a duplicate event uuid at the database level', function () {
    punch($this->tenant->id, $this->employee->id, '08:00:00', 'in');

    expect(fn () => punch($this->tenant->id, $this->employee->id, '08:00:00', 'in'))
        ->toThrow(QueryException::class);
});

it('forbids an employee-role user from rebuilding', function () {
    $user = makeTenantUser($this->tenant, RoleEnum::Employee->value);

    $this->actingAs($user)->post('/attendance/rebuild', ['date' => '2026-08-10'])->assertForbidden();
});
