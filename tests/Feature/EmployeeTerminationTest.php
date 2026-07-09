<?php

use App\Enums\Role as RoleEnum;
use App\Models\Employee;
use App\Models\EmployeeTermination;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['employee_id_prefix' => 'ACME']);
    $this->admin = makeTenantUser($this->tenant, RoleEnum::CompanyAdmin->value);
    $this->employee = Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'active',
        'email' => 'karyawan@contoh.com',
    ]);
});

it('QA-0006 deactivates the employee and disables ESS access on the effective date', function () {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->post("/employees/{$this->employee->id}/terminations", [
            'type' => 'resign',
            'effective_date' => today()->toDateString(),
            'reason' => 'Mengundurkan diri',
        ])
        ->assertRedirect("/employees/{$this->employee->id}");

    // Not applied until the scheduler runs.
    expect($this->employee->fresh()->status)->toBe('active');

    $this->artisan('terminations:apply')->assertSuccessful();

    $fresh = $this->employee->fresh();
    expect($fresh->status)->toBe('inactive');
    expect($fresh->inactive_date->toDateString())->toBe(today()->toDateString());
    expect($user->fresh()->is_active)->toBeFalse();
    expect(EmployeeTermination::firstOrFail()->status)->toBe('completed');

    // Historical data is preserved (not deleted).
    $this->assertDatabaseHas('employees', ['id' => $this->employee->id]);
});

it('does not apply a future-dated termination', function () {
    EmployeeTermination::factory()->create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'effective_date' => today()->addMonth()->toDateString(),
        'status' => 'pending',
    ]);

    $this->artisan('terminations:apply')->assertSuccessful();

    expect($this->employee->fresh()->status)->toBe('active');
});

it('marks exit clearance complete', function () {
    EmployeeTermination::factory()->create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    $this->actingAs($this->admin)
        ->patch("/employees/{$this->employee->id}/terminations/clearance")
        ->assertRedirect();

    $termination = EmployeeTermination::firstOrFail();
    expect($termination->status)->toBe('cleared');
    expect($termination->clearance_completed_at)->not->toBeNull();
});

it('rejects a second termination for the same employee', function () {
    EmployeeTermination::factory()->create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
    ]);

    $this->actingAs($this->admin)
        ->post("/employees/{$this->employee->id}/terminations", [
            'type' => 'phk',
            'effective_date' => today()->toDateString(),
        ])
        ->assertSessionHas('error');

    expect(EmployeeTermination::count())->toBe(1);
});

it('forbids an employee-role user from terminating', function () {
    $user = makeTenantUser($this->tenant, RoleEnum::Employee->value);

    $this->actingAs($user)
        ->post("/employees/{$this->employee->id}/terminations", [
            'type' => 'resign',
            'effective_date' => today()->toDateString(),
        ])
        ->assertForbidden();
});
