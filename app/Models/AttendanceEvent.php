<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\AttendanceEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single clock punch (from mobile, web, kiosk, or an imported fingerprint
 * log). Deduplicated by `event_uuid`.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employee_id
 * @property string $event_uuid
 * @property string $type
 * @property Carbon $occurred_at
 * @property string $channel
 * @property bool $is_suspicious
 */
class AttendanceEvent extends Model
{
    /** @use HasFactory<AttendanceEventFactory> */
    use BelongsToTenant, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'device_captured_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'similarity_score' => 'float',
            'liveness_passed' => 'boolean',
            'is_outside_geofence' => 'boolean',
            'is_suspicious' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
