<?php

namespace Database\Factories;

use App\Models\OrgUnit;
use App\Models\Position;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'org_unit_id' => OrgUnit::factory(),
            'name' => fake()->jobTitle(),
            'grade_id' => null,
            'reports_to_position_id' => null,
        ];
    }
}
