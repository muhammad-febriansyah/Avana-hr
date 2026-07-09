<?php

use App\Enums\Role as RoleEnum;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\Tenant;
use App\Notifications\ContractExpiring;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['employee_id_prefix' => 'ACME']);
    $this->admin = makeTenantUser($this->tenant, RoleEnum::CompanyAdmin->value);
    $this->employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
});

/**
 * @return array<string, mixed>
 */
function contractPayload(array $overrides = []): array
{
    return array_merge([
        'contract_no' => 'CTR-001',
        'type' => 'pkwt',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'status' => 'active',
    ], $overrides);
}

it('creates a contract for an employee', function () {
    $this->actingAs($this->admin)
        ->post("/employees/{$this->employee->id}/contracts", contractPayload())
        ->assertRedirect("/employees/{$this->employee->id}");

    $this->assertDatabaseHas('employee_contracts', [
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'contract_no' => 'CTR-001',
        'type' => 'pkwt',
        'status' => 'active',
    ]);
});

it('rejects a duplicate contract number within the tenant', function () {
    EmployeeContract::factory()->create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'contract_no' => 'CTR-001',
    ]);

    $this->actingAs($this->admin)
        ->post("/employees/{$this->employee->id}/contracts", contractPayload(['contract_no' => 'CTR-001']))
        ->assertSessionHasErrors('contract_no');
});

it('rejects an end date before the start date', function () {
    $this->actingAs($this->admin)
        ->post("/employees/{$this->employee->id}/contracts", contractPayload([
            'start_date' => '2026-06-01',
            'end_date' => '2026-01-01',
        ]))
        ->assertSessionHasErrors('end_date');
});

it('allows an open-ended contract without an end date', function () {
    $this->actingAs($this->admin)
        ->post("/employees/{$this->employee->id}/contracts", contractPayload(['type' => 'pkwtt', 'end_date' => null]))
        ->assertRedirect();

    $this->assertDatabaseHas('employee_contracts', [
        'contract_no' => 'CTR-001',
        'end_date' => null,
    ]);
});

it('updates a contract', function () {
    $contract = EmployeeContract::factory()->create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'contract_no' => 'CTR-OLD',
        'status' => 'active',
    ]);

    $this->actingAs($this->admin)
        ->put("/employees/{$this->employee->id}/contracts/{$contract->id}", contractPayload([
            'contract_no' => 'CTR-OLD',
            'status' => 'terminated',
        ]))
        ->assertRedirect("/employees/{$this->employee->id}");

    expect($contract->fresh()->status)->toBe('terminated');
});

it('deletes a contract and its stored file', function () {
    Storage::fake('local');
    $path = UploadedFile::fake()->create('doc.pdf', 20, 'application/pdf')->store('contracts', 'local');

    $contract = EmployeeContract::factory()->create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'file_path' => $path,
    ]);

    $this->actingAs($this->admin)
        ->delete("/employees/{$this->employee->id}/contracts/{$contract->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('employee_contracts', ['id' => $contract->id]);
    Storage::disk('local')->assertMissing($path);
});

it('stores and downloads an uploaded document', function () {
    Storage::fake('local');

    $this->actingAs($this->admin)
        ->post("/employees/{$this->employee->id}/contracts", contractPayload([
            'contract_no' => 'CTR-DL',
            'file' => UploadedFile::fake()->create('kontrak.pdf', 40, 'application/pdf'),
        ]))
        ->assertRedirect();

    $contract = EmployeeContract::where('contract_no', 'CTR-DL')->firstOrFail();
    expect($contract->file_path)->not->toBeNull();
    Storage::disk('local')->assertExists($contract->file_path);

    $this->actingAs($this->admin)
        ->get("/employees/{$this->employee->id}/contracts/{$contract->id}/download")
        ->assertOk()
        ->assertDownload('CTR-DL.pdf');
});

it('rejects a non-document file upload', function () {
    Storage::fake('local');

    $this->actingAs($this->admin)
        ->post("/employees/{$this->employee->id}/contracts", contractPayload([
            'file' => UploadedFile::fake()->create('virus.exe', 10),
        ]))
        ->assertSessionHasErrors('file');
});

it('scopes a contract to its own employee', function () {
    $other = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $contract = EmployeeContract::factory()->create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
    ]);

    $this->actingAs($this->admin)
        ->put("/employees/{$other->id}/contracts/{$contract->id}", contractPayload([
            'contract_no' => $contract->contract_no,
        ]))
        ->assertNotFound();
});

it('forbids an employee-role user from managing contracts', function () {
    $user = makeTenantUser($this->tenant, RoleEnum::Employee->value);

    $this->actingAs($user)
        ->post("/employees/{$this->employee->id}/contracts", contractPayload())
        ->assertForbidden();
});

it('QA-0012 reminds HR of contracts expiring at H-30/14/7 and expires past-due ones', function () {
    Notification::fake();

    foreach ([30, 14, 7] as $index => $daysLeft) {
        EmployeeContract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee->id,
            'contract_no' => "CTR-H{$daysLeft}",
            'status' => 'active',
            'end_date' => Carbon::today()->addDays($daysLeft),
        ]);
    }

    // A contract nearing expiry but outside the thresholds — must NOT notify.
    EmployeeContract::factory()->create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'contract_no' => 'CTR-H20',
        'status' => 'active',
        'end_date' => Carbon::today()->addDays(20),
    ]);

    // A past-due contract — must be flipped to expired.
    $pastDue = EmployeeContract::factory()->create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'contract_no' => 'CTR-PAST',
        'status' => 'active',
        'end_date' => Carbon::today()->subDay(),
    ]);

    $this->artisan('contracts:remind')->assertSuccessful();

    Notification::assertSentTo($this->admin, ContractExpiring::class);
    Notification::assertSentTimes(ContractExpiring::class, 3);

    expect($pastDue->fresh()->status)->toBe('expired');
});
