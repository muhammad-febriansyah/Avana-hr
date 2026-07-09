<?php

namespace Database\Factories;

use App\Models\PayrollGroup;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollGroup>
 */
class PayrollGroupFactory extends Factory
{
    protected $model = PayrollGroup::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => strtoupper(fake()->unique()->lexify('PG-???')),
            'name' => 'Payroll '.fake()->word(),
            'frequency' => 'monthly',
            'period_start_day' => 1,
            'cutoff_day' => 25,
            'attendance_source' => 'current',
            'overtime_source' => 'current',
            'prorate_method' => 'calendar',
            'is_active' => true,
        ];
    }
}
