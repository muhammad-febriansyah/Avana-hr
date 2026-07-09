<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeSchedule>
 */
class EmployeeScheduleFactory extends Factory
{
    protected $model = EmployeeSchedule::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employee_id' => Employee::factory(),
            'date' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'shift_id' => null,
            'is_day_off' => false,
            'source' => 'generated',
        ];
    }
}
