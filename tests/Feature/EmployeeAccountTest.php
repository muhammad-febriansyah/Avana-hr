<?php

use App\Enums\Role as RoleEnum;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['employee_id_prefix' => 'ACME']);
    $this->admin = makeTenantUser($this->tenant, RoleEnum::CompanyAdmin->value);
});

it('provisions an ESS account with the Employee role', function () {
    $employee = Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'full_name' => 'Budi Santoso',
        'email' => 'budi@contoh.com',
    ]);

    $this->actingAs($this->admin)
        ->post("/employees/{$employee->id}/account")
        ->assertRedirect("/employees/{$employee->id}");

    $user = User::where('employee_id', $employee->id)->firstOrFail();
    expect($user->email)->toBe('budi@contoh.com');
    expect($user->name)->toBe('Budi Santoso');
    expect($user->is_active)->toBeTrue();
    expect($user->hasRole(RoleEnum::Employee->value))->toBeTrue();
});

it('refuses to provision an account when the employee has no email', function () {
    $employee = Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => null,
    ]);

    $this->actingAs($this->admin)
        ->post("/employees/{$employee->id}/account")
        ->assertSessionHas('error');

    expect(User::where('employee_id', $employee->id)->exists())->toBeFalse();
});

it('refuses to provision a second account for the same employee', function () {
    $employee = Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'once@contoh.com',
    ]);
    User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $employee->id,
    ]);

    $this->actingAs($this->admin)
        ->post("/employees/{$employee->id}/account")
        ->assertSessionHas('error');
});

it('forbids an employee-role user from provisioning accounts', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'x@contoh.com']);
    $user = makeTenantUser($this->tenant, RoleEnum::Employee->value);

    $this->actingAs($user)
        ->post("/employees/{$employee->id}/account")
        ->assertForbidden();
});
