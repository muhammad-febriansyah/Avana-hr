<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasApprovals;
use App\Contracts\Approvable;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An employee's leave application, routed through the approval engine. Reserves
 * balance as `pending` on submit, moves it to `used` on approval, and releases
 * it on rejection/cancellation (QA-0026, E2E-0150).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property float $total_days
 * @property string $status
 */
class LeaveRequest extends Model implements Approvable
{
    /** @use HasFactory<LeaveRequestFactory> */
    use BelongsToTenant, HasApprovals, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'total_days' => 'float',
        ];
    }

    public function approvalType(): string
    {
        return 'leave_request';
    }

    public function approvalAmount(): ?int
    {
        return null;
    }

    /**
     * Hold the requested days against the balance while the request is pending.
     */
    public function reservePending(): void
    {
        if ($this->deductsBalance()) {
            $this->balance()->increment('pending', $this->total_days);
        }
    }

    public function onApprovalApproved(): void
    {
        if ($this->deductsBalance()) {
            $balance = $this->balance();
            $balance->decrement('pending', $this->total_days);
            $balance->increment('used', $this->total_days);
        }

        $this->update(['status' => 'approved']);
    }

    public function onApprovalRejected(): void
    {
        $this->releasePending();
        $this->update(['status' => 'rejected']);
    }

    /**
     * Return still-pending days to the balance (rejection / cancel while pending).
     */
    public function releasePending(): void
    {
        if ($this->deductsBalance()) {
            $this->balance()->decrement('pending', $this->total_days);
        }
    }

    /**
     * Return already-consumed days to the balance (cancel after approval).
     */
    public function releaseUsed(): void
    {
        if ($this->deductsBalance()) {
            $this->balance()->decrement('used', $this->total_days);
        }
    }

    public function deductsBalance(): bool
    {
        return (bool) $this->leaveType?->deduct_balance;
    }

    /**
     * The balance row for this request's employee/type/year, created with the
     * type's annual quota as the entitlement if it does not exist yet.
     */
    public function balance(): LeaveBalance
    {
        return LeaveBalance::firstOrCreate(
            [
                'employee_id' => $this->employee_id,
                'leave_type_id' => $this->leave_type_id,
                'year' => $this->start_date->year,
            ],
            [
                'tenant_id' => $this->tenant_id,
                'entitled' => $this->leaveType->annual_quota,
            ],
        );
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
