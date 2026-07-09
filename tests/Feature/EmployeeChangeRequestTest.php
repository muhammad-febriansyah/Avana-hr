<?php

use App\Enums\Role as RoleEnum;
use App\Models\ApprovalFlow;
use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['employee_id_prefix' => 'ACME']);
    $this->admin = makeTenantUser($this->tenant, RoleEnum::CompanyAdmin->value);
    $this->approver = makeTenantUser($this->tenant, RoleEnum::Manager->value);
    $this->employee = Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'full_name' => 'Nama Lama',
        'phone' => '0811',
    ]);
});

function changeRequestFlow(Tenant $tenant, User $approver): void
{
    $flow = ApprovalFlow::create([
        'tenant_id' => $tenant->id,
        'approvable_type' => 'employee_change_request',
        'name' => 'Perubahan Data',
        'is_active' => true,
    ]);
    $flow->steps()->create(['seq' => 1, 'approver_type' => 'user', 'approver_id' => $approver->id]);
}

it('QA-0003 applies employee data changes only after approval', function () {
    changeRequestFlow($this->tenant, $this->approver);

    $this->actingAs($this->admin)
        ->post("/employees/{$this->employee->id}/change-requests", [
            'full_name' => 'Nama Baru',
            'phone' => '0822',
        ])
        ->assertRedirect("/employees/{$this->employee->id}");

    $request = EmployeeChangeRequest::firstOrFail();
    expect($request->status)->toBe('pending');
    // Change is NOT applied while pending.
    expect($this->employee->fresh()->full_name)->toBe('Nama Lama');

    $approval = $request->approvals()->firstOrFail();
    $this->actingAs($this->approver)->post("/approvals/{$approval->id}/approve")->assertRedirect();

    expect($this->employee->fresh()->full_name)->toBe('Nama Baru');
    expect($this->employee->fresh()->phone)->toBe('0822');
    expect($request->fresh()->status)->toBe('approved');
    expect($request->fresh()->applied_at)->not->toBeNull();
});

it('reverts the change when the request is rejected', function () {
    changeRequestFlow($this->tenant, $this->approver);

    $this->actingAs($this->admin)->post("/employees/{$this->employee->id}/change-requests", [
        'full_name' => 'Tak Jadi',
    ]);
    $approval = EmployeeChangeRequest::firstOrFail()->approvals()->firstOrFail();

    $this->actingAs($this->approver)->post("/approvals/{$approval->id}/reject")->assertRedirect();

    expect($this->employee->fresh()->full_name)->toBe('Nama Lama');
    expect(EmployeeChangeRequest::firstOrFail()->status)->toBe('rejected');
});

it('rejects a request with no actual change', function () {
    changeRequestFlow($this->tenant, $this->approver);

    $this->actingAs($this->admin)
        ->post("/employees/{$this->employee->id}/change-requests", [
            'full_name' => 'Nama Lama',
        ])
        ->assertSessionHasErrors('full_name');

    expect(EmployeeChangeRequest::count())->toBe(0);
});

it('reports an error and rolls back when no approval flow is active', function () {
    $this->actingAs($this->admin)
        ->post("/employees/{$this->employee->id}/change-requests", [
            'full_name' => 'Nama Baru',
        ])
        ->assertSessionHas('error');

    expect(EmployeeChangeRequest::count())->toBe(0);
});

it('forbids an employee-role user from submitting change requests', function () {
    $user = makeTenantUser($this->tenant, RoleEnum::Employee->value);

    $this->actingAs($user)
        ->post("/employees/{$this->employee->id}/change-requests", ['full_name' => 'X'])
        ->assertForbidden();
});
