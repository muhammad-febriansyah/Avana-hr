<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    protected $model = Grade::class;

    public function definition(): array
    {
        $min = fake()->numberBetween(4_000_000, 8_000_000);

        return [
            'tenant_id' => Tenant::factory(),
            'code' => strtoupper(fake()->unique()->bothify('G-##')),
            'name' => 'Grade '.fake()->unique()->numberBetween(1, 20),
            'salary_min' => $min,
            'salary_max' => $min + fake()->numberBetween(2_000_000, 6_000_000),
        ];
    }
}
