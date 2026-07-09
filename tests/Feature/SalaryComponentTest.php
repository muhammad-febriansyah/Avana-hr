<?php

use App\Enums\Role as RoleEnum;
use App\Models\SalaryComponent;
use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = makeTenantUser($this->tenant, RoleEnum::CompanyAdmin->value);
});

function componentPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'BONUS',
        'name' => 'Bonus',
        'type' => 'earning',
        'calc_basis' => 'fixed',
        'fixed_amount' => 500000,
        'is_taxable' => true,
        'bpjs_basis' => false,
        'prorate_enabled' => false,
        'overtime_related' => false,
        'show_on_payslip' => true,
        'sort_order' => 20,
        'is_active' => true,
    ], $overrides);
}

it('seeds the standard components when a tenant is provisioned', function () {
    // makeTenantUser() in beforeEach ran ProvisionTenantDefaults.
    expect(SalaryComponent::where('code', 'POKOK')->exists())->toBeTrue();
    expect(SalaryComponent::where('code', 'PPH21')->where('type', 'deduction')->exists())->toBeTrue();
    expect(SalaryComponent::where('tenant_id', $this->tenant->id)->count())->toBe(8);
});

it('creates a salary component', function () {
    $this->actingAs($this->admin)->post('/salary-components', componentPayload())->assertRedirect();

    $this->assertDatabaseHas('salary_components', [
        'tenant_id' => $this->tenant->id,
        'code' => 'BONUS',
        'type' => 'earning',
        'fixed_amount' => 500000,
    ]);
});

it('rejects a duplicate code within the tenant', function () {
    $this->actingAs($this->admin)->post('/salary-components', componentPayload(['code' => 'POKOK']))
        ->assertSessionHasErrors('code');
});

it('requires a fixed amount when calc basis is fixed', function () {
    $this->actingAs($this->admin)
        ->post('/salary-components', componentPayload(['fixed_amount' => null]))
        ->assertSessionHasErrors('fixed_amount');
});

it('updates and deletes a component', function () {
    $component = SalaryComponent::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->put("/salary-components/{$component->id}", componentPayload(['code' => $component->code, 'name' => 'Diubah']))
        ->assertRedirect();
    expect($component->fresh()->name)->toBe('Diubah');

    $this->actingAs($this->admin)->delete("/salary-components/{$component->id}")->assertRedirect();
    $this->assertSoftDeleted('salary_components', ['id' => $component->id]);
});

it('forbids an employee-role user from managing components', function () {
    $user = makeTenantUser($this->tenant, RoleEnum::Employee->value);

    $this->actingAs($user)->post('/salary-components', componentPayload())->assertForbidden();
});
