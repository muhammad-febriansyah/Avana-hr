<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A job position within an org unit, optionally graded and reporting to
 * another position (the reporting line drives the org chart / approvals).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $org_unit_id
 * @property string $name
 * @property int|null $grade_id
 * @property int|null $reports_to_position_id
 */
class Position extends Model
{
    /** @use HasFactory<PositionFactory> */
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'org_unit_id',
        'name',
        'grade_id',
        'reports_to_position_id',
    ];

    /**
     * @return BelongsTo<OrgUnit, $this>
     */
    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    /**
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reports_to_position_id');
    }

    /**
     * @return HasMany<Position, $this>
     */
    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'reports_to_position_id');
    }
}
