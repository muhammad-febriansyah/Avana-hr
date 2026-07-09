<?php

namespace Database\Factories;

use App\Enums\OrgUnitType;
use App\Models\OrgUnit;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgUnit>
 */
class OrgUnitFactory extends Factory
{
    protected $model = OrgUnit::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'parent_id' => null,
            'name' => fake()->unique()->words(2, true),
            'type' => fake()->randomElement(OrgUnitType::cases()),
            'cost_center' => fake()->optional()->bothify('CC-###'),
            'effective_date' => now()->toDateString(),
        ];
    }
}
