<?php

namespace Database\Factories;

use App\Models\ShiftPattern;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftPattern>
 */
class ShiftPatternFactory extends Factory
{
    protected $model = ShiftPattern::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement(['Rotasi 2-2-3', 'Non-shift', 'Shift 3']),
            'cycle_days' => 4,
        ];
    }
}
