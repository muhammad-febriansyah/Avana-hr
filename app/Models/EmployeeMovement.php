<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasApprovals;
use App\Contracts\Approvable;
use Database\Factories\EmployeeMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Mutation / promotion / demotion routed through the approval engine and
 * applied to the employee on its effective date (PRD M02.3, QA-0004/0005).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employee_id
 * @property string $type
 * @property int|null $to_position_id
 * @property int|null $to_org_unit_id
 * @property int|null $to_grade_id
 * @property int|null $to_branch_id
 * @property Carbon $effective_date
 * @property string $status
 */
class EmployeeMovement extends Model implements Approvable
{
    /** @use HasFactory<EmployeeMovementFactory> */
    use BelongsToTenant, HasApprovals, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
        ];
    }

    public function approvalType(): string
    {
        return 'employee_movement';
    }

    public function approvalAmount(): ?int
    {
        return null;
    }

    public function onApprovalApproved(): void
    {
        $this->update(['status' => 'approved']);
    }

    public function onApprovalRejected(): void
    {
        $this->update(['status' => 'rejected']);
    }

    /**
     * Write the target structure onto the employee and mark the movement applied.
     */
    public function apply(): void
    {
        $employee = $this->employee()->first();

        if ($employee === null) {
            return;
        }

        $changes = array_filter([
            'position_id' => $this->to_position_id,
            'org_unit_id' => $this->to_org_unit_id,
            'grade_id' => $this->to_grade_id,
        ], fn (?int $value): bool => $value !== null);

        if ($changes !== []) {
            $employee->update($changes);
        }

        if ($this->to_branch_id !== null) {
            $employee->setPrimaryBranch($this->to_branch_id);
        }

        $this->update(['status' => 'applied']);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
