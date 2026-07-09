<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement(['Pagi', 'Siang', 'Malam']),
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_overnight' => false,
            'late_tolerance_min' => 15,
            'break_minutes' => 60,
        ];
    }
}
