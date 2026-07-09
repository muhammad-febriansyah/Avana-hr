<?php

namespace Database\Factories;

use App\Models\LeaveType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Cuti Tahunan',
            'code' => strtoupper(fake()->unique()->lexify('LT-???')),
            'annual_quota' => 12,
            'deduct_balance' => true,
            'allow_carry_over' => false,
            'carry_over_max' => 0,
            'requires_attachment' => false,
            'min_notice_days' => 0,
        ];
    }
}
