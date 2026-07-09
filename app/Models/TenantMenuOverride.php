<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Super Admin per-tenant availability override for a registry menu. Forces a
 * menu enabled/disabled for one tenant regardless of its plan features.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $menu_id
 * @property bool $is_enabled
 */
class TenantMenuOverride extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'menu_id', 'is_enabled'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
