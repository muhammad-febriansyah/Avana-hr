<?php

use App\Enums\Role as RoleEnum;
use App\Models\Employee;
use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['employee_id_prefix' => 'ACME']);
    $this->admin = makeTenantUser($this->tenant, RoleEnum::CompanyAdmin->value);
});

it('QA-0001 auto-generates a unique employee code per tenant', function () {
    $this->actingAs($this->admin)->post('/employees', ['full_name' => 'Andi'])
        ->assertRedirect();
    $this->actingAs($this->admin)->post('/employees', ['full_name' => 'Budi'])
        ->assertRedirect();

    $codes = Employee::orderBy('id')->pluck('employee_code')->all();

    expect($codes)->toHaveCount(2);
    expect($codes[0])->toStartWith('ACME-')->toEndWith('-0001');
    expect($codes[1])->toEndWith('-0002');
    expect($codes[0])->not->toBe($codes[1]);
});

it('QA-0002 rejects duplicate NIK, NPWP and email per field', function () {
    $this->actingAs($this->admin)->post('/employees', [
        'full_name' => 'Andi',
        'nik_ktp' => '3201010101900001',
        'npwp' => '09.254.294.3-407.000',
        'email' => 'andi@acme.test',
    ])->assertRedirect();

    // Duplicate NIK only.
    $this->actingAs($this->admin)->post('/employees', [
        'full_name' => 'Budi', 'nik_ktp' => '3201010101900001',
    ])->assertSessionHasErrors('nik_ktp')->assertSessionDoesntHaveErrors('npwp');

    // Duplicate NPWP only (even with different formatting).
    $this->actingAs($this->admin)->post('/employees', [
        'full_name' => 'Citra', 'npwp' => '092542943407000',
    ])->assertSessionHasErrors('npwp');

    // Duplicate email only.
    $this->actingAs($this->admin)->post('/employees', [
        'full_name' => 'Dewi', 'email' => 'andi@acme.test',
    ])->assertSessionHasErrors('email');
});

it('stores encrypted PII with a blind-index hash', function () {
    $this->actingAs($this->admin)->post('/employees', [
        'full_name' => 'Andi', 'nik_ktp' => '3201010101900001',
    ])->assertRedirect();

    $employee = Employee::firstOrFail();
    expect($employee->nik_ktp)->toBe('3201010101900001');
    expect($employee->nik_ktp_hash)->toBe(Employee::blindHash('3201010101900001'));
    // Raw stored value is encrypted, not the plaintext.
    $raw = DB::table('employees')->where('id', $employee->id)->value('nik_ktp');
    expect($raw)->not->toBe('3201010101900001');
});

it('updates an employee and keeps its code', function () {
    $employee = Employee::factory()->create([
        'tenant_id' => $this->tenant->id, 'full_name' => 'Andi',
    ]);
    $code = $employee->employee_code;

    $this->actingAs($this->admin)->put("/employees/{$employee->id}", [
        'full_name' => 'Andi Wijaya', 'status' => 'inactive',
    ])->assertRedirect();

    $employee->refresh();
    expect($employee->full_name)->toBe('Andi Wijaya');
    expect($employee->status)->toBe('inactive');
    expect($employee->employee_code)->toBe($code);
});

it('shows an employee detail with its audit trail', function () {
    $this->withoutVite();
    $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)->get("/employees/{$employee->id}")->assertOk();
});

it('records an audit log when an employee is created', function () {
    $this->actingAs($this->admin)->post('/employees', ['full_name' => 'Andi'])->assertRedirect();

    $employee = Employee::firstOrFail();
    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $this->tenant->id,
        'auditable_type' => Employee::class,
        'auditable_id' => $employee->id,
        'event' => 'created',
    ]);
});

it('requires employee permissions for the employee role', function () {
    $employee = makeTenantUser($this->tenant, RoleEnum::Employee->value);

    $this->actingAs($employee)->get('/employees')->assertForbidden();
    $this->actingAs($employee)->post('/employees', ['full_name' => 'X'])->assertForbidden();
});
