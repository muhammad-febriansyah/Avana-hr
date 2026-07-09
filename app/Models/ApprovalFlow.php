<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $approvable_type
 * @property string $name
 * @property bool $is_active
 */
class ApprovalFlow extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'approvable_type', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<ApprovalStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class, 'flow_id')->orderBy('seq');
    }
}
