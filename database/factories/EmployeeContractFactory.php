<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeContract>
 */
class EmployeeContractFactory extends Factory
{
    protected $model = EmployeeContract::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employee_id' => Employee::factory(),
            'contract_no' => strtoupper(fake()->unique()->bothify('CTR-#####')),
            'type' => fake()->randomElement(['pkwt', 'pkwtt', 'magang', 'kemitraan']),
            'start_date' => fake()->dateTimeBetween('-1 year', '-1 month'),
            'end_date' => fake()->dateTimeBetween('+1 month', '+1 year'),
            'file_path' => null,
            'status' => 'active',
        ];
    }
}
