<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeTermination;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeTermination>
 */
class EmployeeTerminationFactory extends Factory
{
    protected $model = EmployeeTermination::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employee_id' => Employee::factory(),
            'type' => fake()->randomElement(['resign', 'phk', 'pensiun', 'meninggal']),
            'effective_date' => fake()->dateTimeBetween('now', '+1 month'),
            'reason' => fake()->optional()->sentence(),
            'status' => 'pending',
        ];
    }
}
