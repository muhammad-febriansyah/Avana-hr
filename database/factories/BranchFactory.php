<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => strtoupper(fake()->unique()->bothify('BR-##')),
            'name' => 'Cabang '.fake()->city(),
            'address' => fake()->address(),
            'latitude' => fake()->latitude(-8, -6),
            'longitude' => fake()->longitude(106, 112),
            'geofence_radius_m' => fake()->randomElement([50, 100, 150, 200]),
            'timezone' => 'Asia/Jakarta',
            'cost_center' => fake()->optional()->bothify('CC-###'),
        ];
    }
}
