<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeMovement;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeMovement>
 */
class EmployeeMovementFactory extends Factory
{
    protected $model = EmployeeMovement::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employee_id' => Employee::factory(),
            'type' => fake()->randomElement(['mutation', 'promotion', 'demotion']),
            'effective_date' => fake()->dateTimeBetween('now', '+2 months'),
            'status' => 'pending',
        ];
    }
}
