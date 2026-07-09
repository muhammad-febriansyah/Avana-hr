<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $employee_code
 * @property string $full_name
 * @property string $status
 */
class Employee extends Model
{
    use Auditable, BelongsToTenant, SoftDeletes;

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

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }
}
