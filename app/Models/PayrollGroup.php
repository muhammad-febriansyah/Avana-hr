<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Database\Factories\PayrollGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A payroll run configuration (period, cut-off, sources) with its component set.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property string $frequency
 * @property int $period_start_day
 * @property int $cutoff_day
 * @property bool $is_active
 */
class PayrollGroup extends Model
{
    /** @use HasFactory<PayrollGroupFactory> */
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period_start_day' => 'integer',
            'cutoff_day' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<SalaryComponent, $this>
     */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(
            SalaryComponent::class,
            'payroll_group_components',
            'payroll_group_id',
            'salary_component_id',
        )->withPivot('is_prorated', 'is_overtime_base')->withTimestamps();
    }

    /**
     * Seed the default monthly group (with all standard components attached).
     */
    public static function seedDefault(Tenant $tenant): void
    {
        $group = self::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'MONTHLY'],
            ['name' => 'Payroll Bulanan', 'frequency' => 'monthly', 'period_start_day' => 1, 'cutoff_day' => 25, 'is_active' => true],
        );

        $componentIds = SalaryComponent::query()->where('tenant_id', $tenant->id)->pluck('id');
        $group->components()->syncWithoutDetaching($componentIds);
    }
}
