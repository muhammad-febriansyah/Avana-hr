<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\EmployeeScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single employee's assignment for one calendar day (a shift, or a day off).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employee_id
 * @property Carbon $date
 * @property int|null $shift_id
 * @property bool $is_day_off
 * @property string $source
 */
class EmployeeSchedule extends Model
{
    /** @use HasFactory<EmployeeScheduleFactory> */
    use BelongsToTenant, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_day_off' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
