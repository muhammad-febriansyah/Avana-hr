<?php

use App\Enums\Role as RoleEnum;
use App\Models\Branch;
use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = makeTenantUser($this->tenant, RoleEnum::CompanyAdmin->value);
});

/**
 * @return array<string, mixed>
 */
function branchPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'BR-01',
        'name' => 'Kantor Pusat',
        'address' => 'Jl. Sudirman No. 1',
        'latitude' => -6.2088,
        'longitude' => 106.8456,
        'geofence_radius_m' => 150,
        'timezone' => 'Asia/Jakarta',
        'cost_center' => 'CC-001',
    ], $overrides);
}

it('creates a branch and stores its geofence radius', function () {
    $this->actingAs($this->admin)->post('/branches', branchPayload())
        ->assertRedirect('/branches');

    $this->assertDatabaseHas('branches', [
        'tenant_id' => $this->tenant->id,
        'code' => 'BR-01',
        'geofence_radius_m' => 150,
        'timezone' => 'Asia/Jakarta',
    ]);

    $branch = Branch::where('code', 'BR-01')->firstOrFail();
    expect($branch->latitude)->toBe(-6.2088);
    expect($branch->longitude)->toBe(106.8456);
});

it('rejects a duplicate branch code within the tenant', function () {
    Branch::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'BR-01']);

    $this->actingAs($this->admin)->post('/branches', branchPayload(['code' => 'BR-01']))
        ->assertSessionHasErrors('code');
});

it('validates coordinate ranges and radius bounds', function () {
    $this->actingAs($this->admin)->post('/branches', branchPayload([
        'latitude' => 200, 'longitude' => -500, 'geofence_radius_m' => 0,
    ]))->assertSessionHasErrors(['latitude', 'longitude', 'geofence_radius_m']);
});

it('updates a branch radius', function () {
    $branch = Branch::factory()->create([
        'tenant_id' => $this->tenant->id, 'geofence_radius_m' => 100,
    ]);

    $this->actingAs($this->admin)->put("/branches/{$branch->id}", branchPayload([
        'code' => $branch->code, 'geofence_radius_m' => 250,
    ]))->assertRedirect('/branches');

    expect($branch->fresh()->geofence_radius_m)->toBe(250);
});

it('filters the branch list by name or code', function () {
    $this->withoutVite();

    Branch::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'BR-JKT', 'name' => 'Cabang Jakarta']);
    Branch::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'BR-BDG', 'name' => 'Cabang Bandung']);

    $this->actingAs($this->admin)->get('/branches?q=Jakarta')
        ->assertOk()
        ->assertSee('Cabang Jakarta')
        ->assertDontSee('Cabang Bandung');
});

it('deletes a branch', function () {
    $branch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)->delete("/branches/{$branch->id}")
        ->assertRedirect();

    $this->assertSoftDeleted('branches', ['id' => $branch->id]);
});

it('requires branch permissions for employees', function () {
    $employee = makeTenantUser($this->tenant, RoleEnum::Employee->value);

    $this->actingAs($employee)->get('/branches')->assertForbidden();
    $this->actingAs($employee)->post('/branches', branchPayload())->assertForbidden();
});

it('records an audit log when a branch is created', function () {
    $this->actingAs($this->admin)->post('/branches', branchPayload())->assertRedirect();

    $branch = Branch::where('code', 'BR-01')->firstOrFail();
    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $this->tenant->id,
        'auditable_type' => Branch::class,
        'auditable_id' => $branch->id,
        'event' => 'created',
    ]);
});
