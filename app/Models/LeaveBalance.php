<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\LeaveBalanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An employee's leave balance for one type in one year.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property int $year
 * @property float $entitled
 * @property float $used
 * @property float $pending
 * @property float $carried_over
 * @property float $expired
 */
class LeaveBalance extends Model
{
    /** @use HasFactory<LeaveBalanceFactory> */
    use BelongsToTenant, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'entitled' => 'float',
            'used' => 'float',
            'pending' => 'float',
            'carried_over' => 'float',
            'expired' => 'float',
        ];
    }

    /**
     * Days still available to request.
     */
    public function available(): float
    {
        return $this->entitled + $this->carried_over - $this->used - $this->pending - $this->expired;
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
