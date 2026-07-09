<?php

namespace App\Models;

use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A non-working calendar day. Rows with a null tenant_id are national holidays
 * shared across all tenants; a tenant may also add its own. Intentionally does
 * NOT use BelongsToTenant so the global (null) rows remain visible.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property Carbon $date
 * @property string $name
 */
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * Limit to holidays a tenant can see: its own plus national (null) rows.
     *
     * @param  Builder<Holiday>  $query
     * @return Builder<Holiday>
     */
    public function scopeVisibleTo(Builder $query, ?int $tenantId): Builder
    {
        return $query->where(function (Builder $q) use ($tenantId): void {
            $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
        });
    }
}
