<?php

use App\Enums\Role as RoleEnum;
use App\Models\ApprovalFlow;
use App\Models\Employee;
use App\Models\EmployeeMovement;
use App\Models\Grade;
use App\Models\OrgUnit;
use App\Models\Position;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['employee_id_prefix' => 'ACME']);
    $this->admin = makeTenantUser($this->tenant, RoleEnum::CompanyAdmin->value);
    $this->approver = makeTenantUser($this->tenant, RoleEnum::Manager->value);
    $this->employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
});

function movementFlow(Tenant $tenant, User $approver): void
{
    $flow = ApprovalFlow::create([
        'tenant_id' => $tenant->id,
        'approvable_type' => 'employee_movement',
        'name' => 'Alur Pergerakan',
        'is_active' => true,
    ]);
    $flow->steps()->create(['seq' => 1, 'approver_type' => 'user', 'approver_id' => $approver->id]);
}

it('creates a movement and submits it for approval', function () {
    movementFlow($this->tenant, $this->approver);
    $position = Position::create(['tenant_id' => $this->tenant->id, 'org_unit_id' => OrgUnit::create(['tenant_id' => $this->tenant->id, 'name' => 'Ops', 'type' => 'department'])->id, 'name' => 'Manager Ops']);

    $this->actingAs($this->admin)
        ->post("/employees/{$this->employee->id}/movements", [
            'type' => 'promotion',
            'to_position_id' => $position->id,
            'effective_date' => today()->toDateString(),
        ])
        ->assertRedirect("/employees/{$this->employee->id}");

    $movement = EmployeeMovement::firstOrFail();
    expect($movement->status)->toBe('pending');
    expect($movement->approvals()->where('status', 'pending')->exists())->toBeTrue();
});

it('requires at least one movement target', function () {
    movementFlow($this->tenant, $this->approver);

    $this->actingAs($this->admin)
        ->post("/employees/{$this->employee->id}/movements", [
            'type' => 'mutation',
            'effective_date' => today()->toDateString(),
        ])
        ->assertSessionHasErrors('type');

    expect(EmployeeMovement::count())->toBe(0);
});

it('reports an error and rolls back when no approval flow is active', function () {
    $unit = OrgUnit::create(['tenant_id' => $this->tenant->id, 'name' => 'Ops', 'type' => 'department']);

    $this->actingAs($this->admin)
        ->post("/employees/{$this->employee->id}/movements", [
            'type' => 'mutation',
            'to_org_unit_id' => $unit->id,
            'effective_date' => today()->toDateString(),
        ])
        ->assertSessionHas('error');

    expect(EmployeeMovement::count())->toBe(0);
});

it('QA-0004 applies an approved mutation to the employee on its effective date', function () {
    movementFlow($this->tenant, $this->approver);

    $unitFrom = OrgUnit::create(['tenant_id' => $this->tenant->id, 'name' => 'Lama', 'type' => 'department']);
    $unitTo = OrgUnit::create(['tenant_id' => $this->tenant->id, 'name' => 'Baru', 'type' => 'department']);
    $posFrom = Position::create(['tenant_id' => $this->tenant->id, 'org_unit_id' => $unitFrom->id, 'name' => 'Staff Lama']);
    $posTo = Position::create(['tenant_id' => $this->tenant->id, 'org_unit_id' => $unitTo->id, 'name' => 'Staff Baru']);
    $this->employee->update(['position_id' => $posFrom->id, 'org_unit_id' => $unitFrom->id]);

    $this->actingAs($this->admin)->post("/employees/{$this->employee->id}/movements", [
        'type' => 'mutation',
        'to_position_id' => $posTo->id,
        'to_org_unit_id' => $unitTo->id,
        'effective_date' => today()->toDateString(),
    ]);

    $movement = EmployeeMovement::firstOrFail();
    $approval = $movement->approvals()->firstOrFail();

    $this->actingAs($this->approver)->post("/approvals/{$approval->id}/approve")->assertRedirect();
    expect($movement->fresh()->status)->toBe('approved');
    // Not applied until the scheduler runs.
    expect($this->employee->fresh()->position_id)->toBe($posFrom->id);

    $this->artisan('movements:apply')->assertSuccessful();

    expect($this->employee->fresh()->position_id)->toBe($posTo->id);
    expect($this->employee->fresh()->org_unit_id)->toBe($unitTo->id);
    expect($movement->fresh()->status)->toBe('applied');
});

it('QA-0005 applies an approved promotion grade change', function () {
    $gradeFrom = Grade::create(['tenant_id' => $this->tenant->id, 'code' => 'G2', 'name' => 'Grade 2']);
    $gradeTo = Grade::create(['tenant_id' => $this->tenant->id, 'code' => 'G3', 'name' => 'Grade 3']);
    $this->employee->update(['grade_id' => $gradeFrom->id]);

    EmployeeMovement::factory()->create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'type' => 'promotion',
        'to_grade_id' => $gradeTo->id,
        'effective_date' => today()->toDateString(),
        'status' => 'approved',
    ]);

    $this->artisan('movements:apply')->assertSuccessful();

    expect($this->employee->fresh()->grade_id)->toBe($gradeTo->id);
});

it('does not apply pending or future-dated movements', function () {
    $grade = Grade::create(['tenant_id' => $this->tenant->id, 'code' => 'G9', 'name' => 'Grade 9']);

    EmployeeMovement::factory()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id,
        'to_grade_id' => $grade->id, 'effective_date' => today()->toDateString(), 'status' => 'pending',
    ]);
    EmployeeMovement::factory()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id,
        'to_grade_id' => $grade->id, 'effective_date' => today()->addMonth()->toDateString(), 'status' => 'approved',
    ]);

    $this->artisan('movements:apply')->assertSuccessful();

    expect($this->employee->fresh()->grade_id)->toBeNull();
});

it('forbids an employee-role user from creating movements', function () {
    $user = makeTenantUser($this->tenant, RoleEnum::Employee->value);

    $this->actingAs($user)
        ->post("/employees/{$this->employee->id}/movements", [
            'type' => 'mutation',
            'effective_date' => today()->toDateString(),
        ])
        ->assertForbidden();
});
