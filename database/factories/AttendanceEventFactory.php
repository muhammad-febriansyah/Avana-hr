<?php

namespace Database\Factories;

use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceEvent>
 */
class AttendanceEventFactory extends Factory
{
    protected $model = AttendanceEvent::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employee_id' => Employee::factory(),
            'event_uuid' => fake()->unique()->uuid(),
            'type' => fake()->randomElement(['in', 'out']),
            'occurred_at' => '2026-08-10 08:00:00',
            'channel' => 'import',
            'is_suspicious' => false,
        ];
    }
}
