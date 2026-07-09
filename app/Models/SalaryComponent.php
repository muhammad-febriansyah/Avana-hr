<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Database\Factories\SalaryComponentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A salary component (earning or deduction) in a tenant's payroll structure.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property bool $is_taxable
 * @property string $calc_basis
 * @property int|null $fixed_amount
 * @property bool $is_active
 */
class SalaryComponent extends Model
{
    /** @use HasFactory<SalaryComponentFactory> */
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'sort_order' => 'integer',
            'is_taxable' => 'boolean',
            'show_on_payslip' => 'boolean',
            'show_on_contract' => 'boolean',
            'pay_after_inactive' => 'boolean',
            'fixed_amount' => 'integer',
            'min_amount' => 'integer',
            'max_amount' => 'integer',
            'prorate_enabled' => 'boolean',
            'overtime_related' => 'boolean',
            'bpjs_basis' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The standard components every tenant starts with (Payroll DoD 3.2).
     *
     * @return list<array<string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            ['code' => 'POKOK', 'name' => 'Gaji Pokok', 'type' => 'earning', 'is_taxable' => true, 'bpjs_basis' => true, 'calc_basis' => 'table', 'prorate_enabled' => true, 'sort_order' => 1],
            ['code' => 'TJAB', 'name' => 'Tunjangan Jabatan', 'type' => 'earning', 'is_taxable' => true, 'calc_basis' => 'table', 'sort_order' => 2],
            ['code' => 'TMAKAN', 'name' => 'Tunjangan Makan', 'type' => 'earning', 'is_taxable' => true, 'calc_basis' => 'fixed', 'fixed_amount' => 0, 'prorate_enabled' => true, 'sort_order' => 3],
            ['code' => 'TTRANS', 'name' => 'Tunjangan Transport', 'type' => 'earning', 'is_taxable' => true, 'calc_basis' => 'fixed', 'fixed_amount' => 0, 'prorate_enabled' => true, 'sort_order' => 4],
            ['code' => 'LEMBUR', 'name' => 'Lembur', 'type' => 'earning', 'is_taxable' => true, 'overtime_related' => true, 'calc_basis' => 'formula', 'sort_order' => 5],
            ['code' => 'POT_BPJSKES', 'name' => 'Potongan BPJS Kesehatan', 'type' => 'deduction', 'calc_basis' => 'formula', 'sort_order' => 10],
            ['code' => 'POT_BPJSTK', 'name' => 'Potongan BPJS Ketenagakerjaan', 'type' => 'deduction', 'calc_basis' => 'formula', 'sort_order' => 11],
            ['code' => 'PPH21', 'name' => 'PPh 21', 'type' => 'deduction', 'calc_basis' => 'formula', 'sort_order' => 12],
        ];
    }

    /**
     * Idempotently seed the default components for a tenant.
     */
    public static function seedDefaults(Tenant $tenant): void
    {
        foreach (self::defaults() as $component) {
            self::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $component['code']],
                $component,
            );
        }
    }
}
