<?php

use App\Enums\Role as RoleEnum;
use App\Models\Holiday;
use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = makeTenantUser($this->tenant, RoleEnum::CompanyAdmin->value);
});

it('adds a tenant-specific holiday', function () {
    $this->actingAs($this->admin)
        ->post('/holidays', ['date' => '2026-08-17', 'name' => 'HUT RI'])
        ->assertRedirect();

    $this->assertDatabaseHas('holidays', [
        'tenant_id' => $this->tenant->id,
        'name' => 'HUT RI',
    ]);
    expect(Holiday::where('name', 'HUT RI')->firstOrFail()->date->toDateString())->toBe('2026-08-17');
});

it('shows national holidays alongside tenant holidays', function () {
    $this->withoutVite();
    Holiday::factory()->create(['tenant_id' => null, 'date' => '2026-01-01', 'name' => 'Tahun Baru']);
    Holiday::factory()->create(['tenant_id' => $this->tenant->id, 'date' => '2026-08-17', 'name' => 'HUT RI']);

    $this->actingAs($this->admin)->get('/holidays')
        ->assertOk()
        ->assertSee('Tahun Baru')
        ->assertSee('HUT RI');
});

it('does not leak another tenant holiday', function () {
    $this->withoutVite();
    $other = Tenant::factory()->create();
    Holiday::factory()->create(['tenant_id' => $other->id, 'date' => '2026-03-01', 'name' => 'Libur Tetangga']);

    $this->actingAs($this->admin)->get('/holidays')
        ->assertOk()
        ->assertDontSee('Libur Tetangga');
});

it('refuses to delete a national holiday', function () {
    $national = Holiday::factory()->create(['tenant_id' => null]);

    $this->actingAs($this->admin)->delete("/holidays/{$national->id}")->assertForbidden();
    $this->assertDatabaseHas('holidays', ['id' => $national->id]);
});

it('deletes an own holiday', function () {
    $holiday = Holiday::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)->delete("/holidays/{$holiday->id}")->assertRedirect();
    $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
});

it('forbids an employee-role user from managing holidays', function () {
    $user = makeTenantUser($this->tenant, RoleEnum::Employee->value);

    $this->actingAs($user)->post('/holidays', ['date' => '2026-08-17', 'name' => 'X'])->assertForbidden();
});
