<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\ShiftPatternFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A repeating shift rotation (e.g. 2-2-3) of `cycle_days` days, each mapped to
 * a shift or a day off via its items.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property int $cycle_days
 */
class ShiftPattern extends Model
{
    /** @use HasFactory<ShiftPatternFactory> */
    use BelongsToTenant, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cycle_days' => 'integer',
        ];
    }

    /**
     * @return HasMany<ShiftPatternItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShiftPatternItem::class, 'pattern_id')->orderBy('day_seq');
    }
}
