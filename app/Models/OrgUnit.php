<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use App\Enums\OrgUnitType;
use Database\Factories\OrgUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A node in the organisation hierarchy (company → division → department →
 * unit), self-referencing via `parent_id` and effective-dated.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $parent_id
 * @property string $name
 * @property OrgUnitType $type
 * @property string|null $cost_center
 * @property Carbon|null $effective_date
 */
class OrgUnit extends Model
{
    /** @use HasFactory<OrgUnitFactory> */
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'name',
        'type',
        'cost_center',
        'effective_date',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrgUnitType::class,
            'effective_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<OrgUnit, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<OrgUnit, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Position, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }
}
