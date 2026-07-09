<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\EmployeeTerminationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Offboarding record. On its effective date the employee is deactivated and
 * their ESS login disabled, while the historical row is preserved (QA-0006).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employee_id
 * @property string $type
 * @property Carbon $effective_date
 * @property string|null $reason
 * @property Carbon|null $clearance_completed_at
 * @property string $status
 */
class EmployeeTermination extends Model
{
    /** @use HasFactory<EmployeeTerminationFactory> */
    use BelongsToTenant, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'clearance_completed_at' => 'datetime',
        ];
    }

    /**
     * Deactivate the employee and their ESS login, then mark the record complete.
     */
    public function apply(): void
    {
        $employee = $this->employee()->first();

        if ($employee !== null) {
            $employee->update([
                'status' => 'inactive',
                'inactive_date' => $this->effective_date,
            ]);

            $employee->user()->first()?->update(['is_active' => false]);
        }

        $this->update(['status' => 'completed']);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
