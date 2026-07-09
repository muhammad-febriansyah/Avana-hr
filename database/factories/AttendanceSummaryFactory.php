<?php

namespace Database\Factories;

use App\Models\AttendanceSummary;
use App\Models\Employee;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceSummary>
 */
class AttendanceSummaryFactory extends Factory
{
    protected $model = AttendanceSummary::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employee_id' => Employee::factory(),
            'date' => '2026-08-10',
            'status' => 'present',
            'late_minutes' => 0,
            'work_minutes' => 480,
            'overtime_minutes' => 0,
            'is_locked' => false,
        ];
    }
}
