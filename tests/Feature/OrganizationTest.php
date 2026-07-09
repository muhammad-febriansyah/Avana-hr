<?php

use App\Enums\Role as RoleEnum;
use App\Models\OrgUnit;
use App\Models\Position;
use App\Models\Tenant;
use App\Support\ReportingLineGuard;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = makeTenantUser($this->tenant, RoleEnum::CompanyAdmin->value);
});

it('QA-0008 builds an effective-dated org hierarchy', function () {
    $this->actingAs($this->admin)->post('/org-units', [
        'name' => 'PT Avana', 'type' => 'company', 'parent_id' => null,
        'cost_center' => null, 'effective_date' => '2026-01-01',
    ])->assertRedirect();

    $company = OrgUnit::where('name', 'PT Avana')->firstOrFail();
    expect($company->effective_date->toDateString())->toBe('2026-01-01');

    $this->actingAs($this->admin)->post('/org-units', [
        'name' => 'Divisi Operasi', 'type' => 'division', 'parent_id' => $company->id,
        'cost_center' => 'CC-01', 'effective_date' => null,
    ])->assertRedirect();

    $this->assertDatabaseHas('org_units', [
        'name' => 'Divisi Operasi', 'parent_id' => $company->id, 'type' => 'division',
    ]);
});

it('QA-0007 lets a position be assigned to an org unit', function () {
    $unit = OrgUnit::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)->post('/positions', [
        'name' => 'Manajer Operasi', 'org_unit_id' => $unit->id,
        'grade_id' => null, 'reports_to_position_id' => null,
    ])->assertRedirect();

    $this->assertDatabaseHas('positions', [
        'name' => 'Manajer Operasi', 'org_unit_id' => $unit->id, 'tenant_id' => $this->tenant->id,
    ]);
});

it('QA-0009 rejects a circular reporting line with a clear message', function () {
    $unit = OrgUnit::factory()->create(['tenant_id' => $this->tenant->id]);
    $a = Position::factory()->create(['tenant_id' => $this->tenant->id, 'org_unit_id' => $unit->id]);
    $b = Position::factory()->create([
        'tenant_id' => $this->tenant->id, 'org_unit_id' => $unit->id,
        'reports_to_position_id' => $a->id,
    ]);

    // A -> B while B -> A would close a cycle.
    $this->actingAs($this->admin)->put("/positions/{$a->id}", [
        'name' => $a->name, 'org_unit_id' => $unit->id,
        'grade_id' => null, 'reports_to_position_id' => $b->id,
    ])->assertSessionHasErrors(['reports_to_position_id' => 'Struktur pelaporan melingkar terdeteksi.']);

    expect($a->fresh()->reports_to_position_id)->toBeNull();
});

it('rejects a position reporting to itself', function () {
    $unit = OrgUnit::factory()->create(['tenant_id' => $this->tenant->id]);
    $p = Position::factory()->create(['tenant_id' => $this->tenant->id, 'org_unit_id' => $unit->id]);

    $this->actingAs($this->admin)->put("/positions/{$p->id}", [
        'name' => $p->name, 'org_unit_id' => $unit->id,
        'grade_id' => null, 'reports_to_position_id' => $p->id,
    ])->assertSessionHasErrors('reports_to_position_id');
});

it('detects cycles directly in the reporting-line guard', function () {
    $unit = OrgUnit::factory()->create(['tenant_id' => $this->tenant->id]);
    $a = Position::factory()->create(['tenant_id' => $this->tenant->id, 'org_unit_id' => $unit->id]);
    $b = Position::factory()->create([
        'tenant_id' => $this->tenant->id, 'org_unit_id' => $unit->id, 'reports_to_position_id' => $a->id,
    ]);
    $c = Position::factory()->create([
        'tenant_id' => $this->tenant->id, 'org_unit_id' => $unit->id, 'reports_to_position_id' => $b->id,
    ]);

    // A reporting to C (its own grandchild) closes the loop.
    expect(ReportingLineGuard::wouldCycle($a->id, $c->id))->toBeTrue();
    expect(ReportingLineGuard::wouldCycle($c->id, null))->toBeFalse();
});

it('rejects an org unit that would parent itself', function () {
    $unit = OrgUnit::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)->put("/org-units/{$unit->id}", [
        'name' => $unit->name, 'type' => $unit->type->value,
        'parent_id' => $unit->id, 'cost_center' => null, 'effective_date' => null,
    ])->assertSessionHasErrors('parent_id');
});

it('validates the grade salary band', function () {
    $this->actingAs($this->admin)->post('/grades', [
        'code' => 'G-01', 'name' => 'Staff', 'salary_min' => 8_000_000, 'salary_max' => 5_000_000,
    ])->assertSessionHasErrors('salary_max');

    $this->actingAs($this->admin)->post('/grades', [
        'code' => 'G-01', 'name' => 'Staff', 'salary_min' => 5_000_000, 'salary_max' => 8_000_000,
    ])->assertRedirect();

    $this->assertDatabaseHas('grades', ['code' => 'G-01', 'tenant_id' => $this->tenant->id]);
});

it('blocks deleting an org unit that still has children', function () {
    $parent = OrgUnit::factory()->create(['tenant_id' => $this->tenant->id]);
    OrgUnit::factory()->create(['tenant_id' => $this->tenant->id, 'parent_id' => $parent->id]);

    $this->actingAs($this->admin)->delete("/org-units/{$parent->id}")
        ->assertSessionHas('error');

    $this->assertDatabaseHas('org_units', ['id' => $parent->id, 'deleted_at' => null]);
});

it('requires organization permissions for employees', function () {
    $employee = makeTenantUser($this->tenant, RoleEnum::Employee->value);

    $this->actingAs($employee)->get('/organization')->assertForbidden();
    $this->actingAs($employee)->post('/org-units', [
        'name' => 'X', 'type' => 'unit', 'parent_id' => null,
        'cost_center' => null, 'effective_date' => null,
    ])->assertForbidden();
});

it('records an audit log when an org unit is created', function () {
    $this->actingAs($this->admin)->post('/org-units', [
        'name' => 'Audit Unit', 'type' => 'company', 'parent_id' => null,
        'cost_center' => null, 'effective_date' => null,
    ])->assertRedirect();

    $unit = OrgUnit::where('name', 'Audit Unit')->firstOrFail();
    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $this->tenant->id,
        'auditable_type' => OrgUnit::class,
        'auditable_id' => $unit->id,
        'event' => 'created',
    ]);
});
