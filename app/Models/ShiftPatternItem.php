<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One day within a shift pattern's cycle. A null shift_id means a day off.
 *
 * @property int $id
 * @property int $pattern_id
 * @property int $day_seq
 * @property int|null $shift_id
 */
class ShiftPatternItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'day_seq' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ShiftPattern, $this>
     */
    public function pattern(): BelongsTo
    {
        return $this->belongsTo(ShiftPattern::class, 'pattern_id');
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
