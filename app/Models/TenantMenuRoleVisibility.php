<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Restricts a tenant menu setting to specific roles. No rows for a setting
 * means it shows for every role that holds the required permission.
 *
 * @property int $id
 * @property int $tenant_menu_setting_id
 * @property int $role_id
 */
class TenantMenuRoleVisibility extends Model
{
    protected $table = 'tenant_menu_role_visibility';

    protected $fillable = ['tenant_menu_setting_id', 'role_id'];

    protected function casts(): array
    {
        return ['role_id' => 'integer'];
    }

    /**
     * @return BelongsTo<TenantMenuSetting, $this>
     */
    public function setting(): BelongsTo
    {
        return $this->belongsTo(TenantMenuSetting::class, 'tenant_menu_setting_id');
    }
}
