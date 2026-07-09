<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A work shift (start/end time, tolerances) assignable to schedules.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $start_time
 * @property string $end_time
 * @property bool $is_overnight
 * @property int $late_tolerance_min
 * @property int $break_minutes
 */
class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_overnight' => 'boolean',
            'late_tolerance_min' => 'integer',
            'break_minutes' => 'integer',
        ];
    }
}
