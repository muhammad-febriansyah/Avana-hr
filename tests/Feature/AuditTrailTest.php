<?php

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function actingTenantUser(): User
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    test()->actingAs($user);

    return $user;
}

it('QA-0114 records new values, actor and timestamp on create', function () {
    $user = actingTenantUser();

    $employee = Employee::create([
        'employee_code' => 'E-001',
        'full_name' => 'Rina Anggraini',
        'nik_ktp' => '3201010101010001',
        'status' => 'active',
    ]);

    $log = AuditLog::where('auditable_id', $employee->id)
        ->where('event', 'created')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($user->id);
    expect($log->tenant_id)->toBe($user->tenant_id);
    expect($log->created_at)->not->toBeNull();
    expect($log->new_values)->toHaveKey('full_name');
    // Sensitive field never written to the audit trail.
    expect($log->new_values)->not->toHaveKey('nik_ktp');
});

it('QA-0114 records old and new values on update', function () {
    actingTenantUser();

    $employee = Employee::create([
        'employee_code' => 'E-002',
        'full_name' => 'Budi',
        'status' => 'active',
    ]);

    $employee->update(['full_name' => 'Budi Santoso']);

    $log = AuditLog::where('auditable_id', $employee->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->new_values)->toMatchArray(['full_name' => 'Budi Santoso']);
    expect($log->old_values)->toMatchArray(['full_name' => 'Budi']);
});

it('records a delete event', function () {
    actingTenantUser();

    $employee = Employee::create([
        'employee_code' => 'E-003',
        'full_name' => 'Dewi',
        'status' => 'active',
    ]);

    $employee->delete();

    expect(
        AuditLog::where('auditable_id', $employee->id)
            ->where('event', 'deleted')
            ->exists(),
    )->toBeTrue();
});
