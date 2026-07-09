<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Placement of an employee at a branch (one is flagged primary).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employee_id
 * @property int $branch_id
 * @property bool $is_primary
 */
class EmployeeBranchAssignment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'branch_id',
        'is_primary',
        'effective_date',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'effective_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
