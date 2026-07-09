<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $employee_code
 * @property string $full_name
 * @property string|null $nik_ktp
 * @property string|null $nik_ktp_hash
 * @property string|null $npwp
 * @property string|null $npwp_hash
 * @property string|null $email
 * @property int|null $position_id
 * @property int|null $grade_id
 * @property int|null $org_unit_id
 * @property int|null $direct_manager_employee_id
 * @property Carbon|null $birth_date
 * @property Carbon|null $join_date
 * @property Carbon|null $inactive_date
 * @property string $status
 */
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'nik_ktp' => 'encrypted',
            'npwp' => 'encrypted',
            'bank_account' => 'encrypted',
            'birth_date' => 'date',
            'join_date' => 'date',
            'inactive_date' => 'date',
            'face_enrolled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Maintain blind-index hashes for the encrypted PII so duplicates can
        // be detected without decrypting every row.
        static::saving(function (Employee $employee): void {
            $employee->nik_ktp_hash = self::blindHash($employee->nik_ktp);
            $employee->npwp_hash = self::blindHash($employee->npwp);
        });
    }

    /**
     * Normalized SHA-256 hash used as a blind index for a PII value.
     */
    public static function blindHash(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return hash('sha256', preg_replace('/[^A-Za-z0-9]/', '', $value) ?? '');
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /**
     * @return BelongsTo<OrgUnit, $this>
     */
    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function directManager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'direct_manager_employee_id');
    }

    /**
     * @return HasMany<EmployeeBranchAssignment, $this>
     */
    public function branchAssignments(): HasMany
    {
        return $this->hasMany(EmployeeBranchAssignment::class);
    }

    /**
     * @return HasMany<EmployeeContract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class)->latest('start_date');
    }

    /**
     * @return HasMany<EmployeeMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(EmployeeMovement::class)->latest('effective_date');
    }

    /**
     * @return HasMany<EmployeeChangeRequest, $this>
     */
    public function changeRequests(): HasMany
    {
        return $this->hasMany(EmployeeChangeRequest::class)->latest('id');
    }

    /**
     * @return HasOne<EmployeeTermination, $this>
     */
    public function termination(): HasOne
    {
        return $this->hasOne(EmployeeTermination::class);
    }

    /**
     * Flag one branch assignment as primary, demoting any other.
     */
    public function setPrimaryBranch(int $branchId): void
    {
        $this->branchAssignments()->where('branch_id', '!=', $branchId)->update(['is_primary' => false]);
        $this->branchAssignments()->updateOrCreate(
            ['branch_id' => $branchId],
            ['is_primary' => true],
        );
    }

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }
}
