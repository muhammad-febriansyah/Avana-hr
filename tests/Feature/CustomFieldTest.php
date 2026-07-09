<?php

use App\Enums\Role as RoleEnum;
use App\Models\CustomFieldDefinition;
use App\Models\Employee;
use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = makeTenantUser($this->tenant, RoleEnum::CompanyAdmin->value);
});

it('QA-0120 shows tenant custom fields on the employee form', function () {
    $this->withoutVite();
    CustomFieldDefinition::factory()->create([
        'tenant_id' => $this->tenant->id,
        'label' => 'Golongan Darah',
        'key' => 'gol_darah',
        'field_type' => 'text',
    ]);

    $this->actingAs($this->admin)->get('/employees/create')
        ->assertOk()
        ->assertSee('Golongan Darah');
});

it('saves custom field values with an employee', function () {
    $def = CustomFieldDefinition::factory()->create([
        'tenant_id' => $this->tenant->id, 'key' => 'gol_darah', 'field_type' => 'text',
    ]);

    $this->actingAs($this->admin)->post('/employees', [
        'full_name' => 'Andi',
        'custom_fields' => [$def->id => 'O'],
    ])->assertRedirect();

    $employee = Employee::firstOrFail();
    $this->assertDatabaseHas('custom_field_values', [
        'definition_id' => $def->id,
        'entity_id' => $employee->id,
        'value' => 'O',
    ]);
});

it('enforces a required custom field', function () {
    $def = CustomFieldDefinition::factory()->create([
        'tenant_id' => $this->tenant->id, 'field_type' => 'text', 'is_required' => true,
    ]);

    $this->actingAs($this->admin)->post('/employees', ['full_name' => 'Andi'])
        ->assertSessionHasErrors("custom_fields.{$def->id}");
});

it('validates a select custom field against its options', function () {
    $def = CustomFieldDefinition::factory()->create([
        'tenant_id' => $this->tenant->id, 'field_type' => 'select',
        'options' => ['A', 'B', 'AB', 'O'],
    ]);

    $this->actingAs($this->admin)->post('/employees', [
        'full_name' => 'Andi', 'custom_fields' => [$def->id => 'Z'],
    ])->assertSessionHasErrors("custom_fields.{$def->id}");

    $this->actingAs($this->admin)->post('/employees', [
        'full_name' => 'Budi', 'custom_fields' => [$def->id => 'O'],
    ])->assertRedirect();
});

it('creates a custom field definition and rejects a duplicate key', function () {
    $this->actingAs($this->admin)->post('/employees/custom-fields', [
        'label' => 'Golongan Darah', 'key' => 'gol_darah', 'field_type' => 'text',
        'is_required' => false, 'sort_order' => 0,
    ])->assertRedirect();

    $this->assertDatabaseHas('custom_field_definitions', [
        'tenant_id' => $this->tenant->id, 'key' => 'gol_darah', 'entity' => 'employee',
    ]);

    $this->actingAs($this->admin)->post('/employees/custom-fields', [
        'label' => 'Lain', 'key' => 'gol_darah', 'field_type' => 'text',
        'is_required' => false, 'sort_order' => 0,
    ])->assertSessionHasErrors('key');
});

it('requires options for a select custom field', function () {
    $this->actingAs($this->admin)->post('/employees/custom-fields', [
        'label' => 'Warna', 'key' => 'warna', 'field_type' => 'select',
        'is_required' => false, 'sort_order' => 0,
    ])->assertSessionHasErrors('options');
});

it('requires employees.update to manage custom fields', function () {
    $employee = makeTenantUser($this->tenant, RoleEnum::Employee->value);

    $this->actingAs($employee)->get('/employees/custom-fields')->assertForbidden();
    $this->actingAs($employee)->post('/employees/custom-fields', [
        'label' => 'X', 'key' => 'x', 'field_type' => 'text', 'is_required' => false, 'sort_order' => 0,
    ])->assertForbidden();
});
