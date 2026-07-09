<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Admin Tenant presentation settings for a registry menu: visibility, label
 * alias, ordering and re-parenting. Absence of a row means "use defaults".
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $menu_id
 * @property bool $is_visible
 * @property string|null $label_alias
 * @property int|null $sort_order
 * @property int|null $parent_override_id
 */
class TenantMenuSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'menu_id',
        'is_visible',
        'label_alias',
        'sort_order',
        'parent_override_id',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * @return HasMany<TenantMenuRoleVisibility, $this>
     */
    public function roleVisibilities(): HasMany
    {
        return $this->hasMany(TenantMenuRoleVisibility::class);
    }
}
