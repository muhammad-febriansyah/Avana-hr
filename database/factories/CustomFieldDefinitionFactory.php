<?php

namespace Database\Factories;

use App\Models\CustomFieldDefinition;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomFieldDefinition>
 */
class CustomFieldDefinitionFactory extends Factory
{
    protected $model = CustomFieldDefinition::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'entity' => 'employee',
            'label' => 'Field '.fake()->unique()->numberBetween(1, 999999),
            'key' => 'cf_'.fake()->unique()->numberBetween(1, 999999),
            'field_type' => 'text',
            'options' => null,
            'is_required' => false,
            'sort_order' => 0,
        ];
    }
}
