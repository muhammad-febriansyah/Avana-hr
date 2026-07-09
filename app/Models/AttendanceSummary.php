<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\AttendanceSummaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The reconciled daily attendance result for one employee (built from events
 * against their schedule). Frozen once `is_locked` for payroll.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employee_id
 * @property Carbon $date
 * @property int|null $schedule_shift_id
 * @property Carbon|null $clock_in
 * @property Carbon|null $clock_out
 * @property string $status
 * @property int $late_minutes
 * @property int $work_minutes
 * @property int $overtime_minutes
 * @property bool $is_locked
 * @property Carbon|null $locked_at
 */
class AttendanceSummary extends Model
{
    /** @use HasFactory<AttendanceSummaryFactory> */
    use BelongsToTenant, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'late_minutes' => 'integer',
            'work_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
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
        return $this->belongsTo(Shift::class, 'schedule_shift_id');
    }
}
