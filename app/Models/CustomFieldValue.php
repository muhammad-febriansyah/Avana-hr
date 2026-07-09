<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stored value for a custom field definition on one entity record. Tenant
 * scoping is inherited through the (tenant-scoped) definition.
 *
 * @property int $id
 * @property int $definition_id
 * @property int $entity_id
 * @property string|null $value
 */
class CustomFieldValue extends Model
{
    protected $fillable = [
        'definition_id',
        'entity_id',
        'value',
    ];

    /**
     * @return BelongsTo<CustomFieldDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class);
    }
}
