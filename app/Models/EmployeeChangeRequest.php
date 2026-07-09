<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasApprovals;
use App\Contracts\Approvable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maker-checker request for sensitive employee data changes (PRD M02.2).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employee_id
 * @property int $requested_by
 * @property array<string, mixed> $changes
 * @property string $status
 */
class EmployeeChangeRequest extends Model implements Approvable
{
    use BelongsToTenant, HasApprovals;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'requested_by',
        'changes',
        'status',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    public function approvalType(): string
    {
        return 'employee_change_request';
    }

    public function approvalAmount(): ?int
    {
        return null;
    }

    public function onApprovalApproved(): void
    {
        $this->update(['status' => 'approved', 'applied_at' => now()]);
    }

    public function onApprovalRejected(): void
    {
        $this->update(['status' => 'rejected']);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
