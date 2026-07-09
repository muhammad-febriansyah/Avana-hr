<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Database\Factories\LeaveTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A category of leave (annual, sick, unpaid, …) with its balance policy.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $code
 * @property int $annual_quota
 * @property bool $deduct_balance
 * @property bool $allow_carry_over
 * @property int $carry_over_max
 * @property int|null $carry_over_expiry_month
 * @property bool $requires_attachment
 * @property int $min_notice_days
 * @property int|null $max_consecutive_days
 */
class LeaveType extends Model
{
    /** @use HasFactory<LeaveTypeFactory> */
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'annual_quota' => 'integer',
            'deduct_balance' => 'boolean',
            'allow_carry_over' => 'boolean',
            'carry_over_max' => 'integer',
            'carry_over_expiry_month' => 'integer',
            'requires_attachment' => 'boolean',
            'min_notice_days' => 'integer',
            'max_consecutive_days' => 'integer',
        ];
    }
}
