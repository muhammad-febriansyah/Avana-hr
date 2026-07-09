<?php

namespace Database\Factories;

use App\Models\SalaryComponent;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryComponent>
 */
class SalaryComponentFactory extends Factory
{
    protected $model = SalaryComponent::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => strtoupper(fake()->unique()->lexify('SC-????')),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(['earning', 'deduction']),
            'is_taxable' => false,
            'calc_basis' => 'fixed',
            'fixed_amount' => fake()->numberBetween(0, 5_000_000),
            'is_active' => true,
        ];
    }
}
