<?php

use App\Enums\Role as RoleEnum;
use App\Models\Shift;
use App\Models\ShiftPattern;
use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = makeTenantUser($this->tenant, RoleEnum::CompanyAdmin->value);
    $this->shift = Shift::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('creates a pattern with its per-day items', function () {
    $this->actingAs($this->admin)
        ->post('/shift-patterns', [
            'name' => 'Rotasi 2-2',
            'cycle_days' => 4,
            'days' => [$this->shift->id, $this->shift->id, null, null],
        ])
        ->assertRedirect();

    $pattern = ShiftPattern::firstOrFail();
    expect($pattern->cycle_days)->toBe(4);
    expect($pattern->items)->toHaveCount(4);
    expect($pattern->items->pluck('shift_id')->all())->toBe([$this->shift->id, $this->shift->id, null, null]);
});

it('rejects a day count that differs from the cycle length', function () {
    $this->actingAs($this->admin)
        ->post('/shift-patterns', [
            'name' => 'Salah',
            'cycle_days' => 3,
            'days' => [$this->shift->id, null],
        ])
        ->assertSessionHasErrors('days');

    expect(ShiftPattern::count())->toBe(0);
});

it('resyncs items on update', function () {
    $pattern = ShiftPattern::factory()->create(['tenant_id' => $this->tenant->id, 'cycle_days' => 4]);
    $pattern->items()->createMany([
        ['day_seq' => 1, 'shift_id' => $this->shift->id],
        ['day_seq' => 2, 'shift_id' => null],
        ['day_seq' => 3, 'shift_id' => null],
        ['day_seq' => 4, 'shift_id' => null],
    ]);

    $this->actingAs($this->admin)
        ->put("/shift-patterns/{$pattern->id}", [
            'name' => 'Diubah',
            'cycle_days' => 2,
            'days' => [$this->shift->id, null],
        ])
        ->assertRedirect();

    expect($pattern->fresh()->items)->toHaveCount(2);
});

it('forbids an employee-role user from managing patterns', function () {
    $user = makeTenantUser($this->tenant, RoleEnum::Employee->value);

    $this->actingAs($user)
        ->post('/shift-patterns', ['name' => 'X', 'cycle_days' => 1, 'days' => [null]])
        ->assertForbidden();
});
