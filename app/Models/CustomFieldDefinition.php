<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Database\Factories\CustomFieldDefinitionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A tenant-defined extra field shown on an entity's form/detail (Wave 1:
 * employee only). Values live in custom_field_values keyed by entity id.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $entity
 * @property string $label
 * @property string $key
 * @property string $field_type
 * @property array<int, string>|null $options
 * @property bool $is_required
 * @property int $sort_order
 */
class CustomFieldDefinition extends Model
{
    /** @use HasFactory<CustomFieldDefinitionFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'entity',
        'label',
        'key',
        'field_type',
        'options',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
