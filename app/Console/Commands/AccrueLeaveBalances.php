<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

class AccrueLeaveBalances extends Command
{
    protected $signature = 'leave:accrue {year?}';

    protected $description = 'Create/refresh yearly leave entitlements per employee (prorated for mid-year joiners)';

    public function handle(): int
    {
        $year = (int) ($this->argument('year') ?? Date::now()->year);
        $accrued = 0;

        Tenant::query()->chunkById(50, function (Collection $tenants) use ($year, &$accrued): void {
            foreach ($tenants as $tenant) {
                $types = LeaveType::query()->withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('annual_quota', '>', 0)
                    ->get();

                if ($types->isEmpty()) {
                    continue;
                }

                $employees = Employee::query()->withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('status', 'active')
                    ->get(['id', 'join_date']);

                foreach ($employees as $employee) {
                    foreach ($types as $type) {
                        LeaveBalance::query()->withoutGlobalScopes()->updateOrCreate(
                            ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year],
                            ['tenant_id' => $tenant->id, 'entitled' => $this->prorate($type->annual_quota, $employee->join_date, $year)],
                        );
                        $accrued++;
                    }
                }
            }
        });

        $this->info("Accrued {$accrued} balance(s) for {$year}.");

        return self::SUCCESS;
    }

    /**
     * Full quota, or prorated by remaining months for employees who joined
     * during the accrual year.
     */
    private function prorate(int $quota, ?CarbonInterface $joinDate, int $year): float
    {
        if ($joinDate === null || $joinDate->year < $year) {
            return (float) $quota;
        }

        if ($joinDate->year > $year) {
            return 0.0;
        }

        $monthsRemaining = 13 - $joinDate->month;

        return round($quota * $monthsRemaining / 12, 1);
    }
}
