<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'date' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'name' => fake()->randomElement(['Hari Libur Nasional', 'Cuti Bersama']),
        ];
    }
}
