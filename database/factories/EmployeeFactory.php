<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employee_code' => strtoupper(fake()->unique()->bothify('EMP-2026-####')),
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'gender' => fake()->randomElement(['male', 'female']),
            'employment_status' => fake()->randomElement(['pkwt', 'pkwtt', 'magang', 'kemitraan']),
            'join_date' => fake()->dateTimeBetween('-5 years')->format('Y-m-d'),
            'status' => 'active',
        ];
    }
}
