<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Tenant;
use Illuminate\Support\Facades\Date;

/**
 * Generates the tenant-configurable employee code `{prefix}-{year}-{seq}`.
 */
class EmployeeCode
{
    public static function generate(Tenant $tenant): string
    {
        $prefix = $tenant->employee_id_prefix ?: 'EMP';
        $year = Date::now()->year;
        $pattern = "{$prefix}-{$year}-";

        // Count within the year (including soft-deleted) so codes are not reused.
        $count = Employee::withTrashed()
            ->where('tenant_id', $tenant->id)
            ->where('employee_code', 'like', $pattern.'%')
            ->count();

        $seq = str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);

        return $pattern.$seq;
    }
}
