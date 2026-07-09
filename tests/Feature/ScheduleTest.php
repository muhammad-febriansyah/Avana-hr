<?php

use App\Enums\Role as RoleEnum;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\ShiftPattern;
use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = makeTenantUser($this->tenant, RoleEnum::CompanyAdmin->value);
    $this->employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
});

/**
 * Pattern: day1 = shift, day2 = off (cycle 2).
 */
function twoDayPattern(int $tenantId, int $shiftId): ShiftPattern
{
    $pattern = ShiftPattern::factory()->create(['tenant_id' => $tenantId, 'cycle_days' => 2]);
    $pattern->items()->createMany([
        ['day_seq' => 1, 'shift_id' => $shiftId],
        ['day_seq' => 2, 'shift_id' => null],
    ]);

    return $pattern;
}

it('generates the rotation across the range', function () {
    $shift = Shift::factory()->create(['tenant_id' => $this->tenant->id]);
    $pattern = twoDayPattern($this->tenant->id, $shift->id);

    $this->actingAs($this->admin)->post('/schedules/generate', [
        'pattern_id' => $pattern->id,
        'employee_ids' => [$this->employee->id],
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-04',
    ])->assertRedirect();

    $byDate = EmployeeSchedule::where('employee_id', $this->employee->id)
        ->get()->keyBy(fn (EmployeeSchedule $s) => $s->date->toDateString());

    expect($byDate['2026-06-01']->shift_id)->toBe($shift->id);   // seq1 work
    expect($byDate['2026-06-01']->is_day_off)->toBeFalse();
    expect($byDate['2026-06-02']->is_day_off)->toBeTrue();       // seq2 off
    expect($byDate['2026-06-03']->shift_id)->toBe($shift->id);   // seq1 work
    expect($byDate['2026-06-04']->is_day_off)->toBeTrue();       // seq2 off
});

it('QA-0024 skips holidays and approved leave and preserves manual days', function () {
    $pagi = Shift::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Pagi']);
    $malam = Shift::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Malam']);
    $pattern = twoDayPattern($this->tenant->id, $pagi->id);

    // National holiday on a would-be work day (seq1).
    Holiday::factory()->create(['tenant_id' => null, 'date' => '2026-06-03']);

    // Approved leave on another would-be work day (seq1).
    $leaveTypeId = DB::table('leave_types')->insertGetId([
        'tenant_id' => $this->tenant->id, 'name' => 'Cuti Tahunan', 'code' => 'AL',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('leave_requests')->insert([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id,
        'leave_type_id' => $leaveTypeId, 'start_date' => '2026-06-05', 'end_date' => '2026-06-05',
        'total_days' => 1, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
    ]);

    // Manually-edited day that must be preserved (would-be seq1 work day).
    EmployeeSchedule::factory()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id,
        'date' => '2026-06-01', 'shift_id' => $malam->id, 'source' => 'manual', 'is_day_off' => false,
    ]);

    $this->actingAs($this->admin)->post('/schedules/generate', [
        'pattern_id' => $pattern->id,
        'employee_ids' => [$this->employee->id],
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-10',
    ])->assertRedirect();

    $byDate = EmployeeSchedule::where('employee_id', $this->employee->id)
        ->get()->keyBy(fn (EmployeeSchedule $s) => $s->date->toDateString());

    // Manual day untouched.
    expect($byDate['2026-06-01']->source)->toBe('manual');
    expect($byDate['2026-06-01']->shift_id)->toBe($malam->id);

    // Holiday -> day off (not the pattern's shift).
    expect($byDate['2026-06-03']->is_day_off)->toBeTrue();
    expect($byDate['2026-06-03']->shift_id)->toBeNull();

    // Leave -> day off.
    expect($byDate['2026-06-05']->is_day_off)->toBeTrue();
    expect($byDate['2026-06-05']->shift_id)->toBeNull();

    // A clean seq1 day still becomes a work day.
    expect($byDate['2026-06-07']->shift_id)->toBe($pagi->id);
    expect($byDate['2026-06-07']->is_day_off)->toBeFalse();

    // 10 days total: 1 manual preserved + 9 generated.
    expect($byDate)->toHaveCount(10);
});

it('forbids an employee-role user from generating schedules', function () {
    $shift = Shift::factory()->create(['tenant_id' => $this->tenant->id]);
    $pattern = twoDayPattern($this->tenant->id, $shift->id);
    $user = makeTenantUser($this->tenant, RoleEnum::Employee->value);

    $this->actingAs($user)->post('/schedules/generate', [
        'pattern_id' => $pattern->id,
        'employee_ids' => [$this->employee->id],
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-04',
    ])->assertForbidden();
});
