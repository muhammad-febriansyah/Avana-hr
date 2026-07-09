<?php

use App\Enums\Role as RoleEnum;
use App\Models\Shift;
use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = makeTenantUser($this->tenant, RoleEnum::CompanyAdmin->value);
});

function shiftPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Pagi',
        'start_time' => '08:00',
        'end_time' => '17:00',
        'is_overnight' => false,
        'late_tolerance_min' => 15,
        'break_minutes' => 60,
    ], $overrides);
}

it('creates a shift', function () {
    $this->actingAs($this->admin)->post('/shifts', shiftPayload())->assertRedirect();

    $this->assertDatabaseHas('shifts', [
        'tenant_id' => $this->tenant->id,
        'name' => 'Pagi',
        'is_overnight' => false,
    ]);
});

it('validates shift times', function () {
    $this->actingAs($this->admin)
        ->post('/shifts', shiftPayload(['start_time' => '25:00', 'name' => '']))
        ->assertSessionHasErrors(['start_time', 'name']);
});

it('updates and deletes a shift', function () {
    $shift = Shift::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->put("/shifts/{$shift->id}", shiftPayload(['name' => 'Malam', 'is_overnight' => true]))
        ->assertRedirect();
    expect($shift->fresh()->name)->toBe('Malam');
    expect($shift->fresh()->is_overnight)->toBeTrue();

    $this->actingAs($this->admin)->delete("/shifts/{$shift->id}")->assertRedirect();
    $this->assertSoftDeleted('shifts', ['id' => $shift->id]);
});

it('forbids an employee-role user from managing shifts', function () {
    $user = makeTenantUser($this->tenant, RoleEnum::Employee->value);

    $this->actingAs($user)->post('/shifts', shiftPayload())->assertForbidden();
});
