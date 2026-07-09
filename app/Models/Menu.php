<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Platform-level menu registry (no tenant_id). Nested up to 2 levels via
 * `parent_id`. Tenants adjust presentation through the tenant_menu_* tables.
 *
 * @property int $id
 * @property string $code
 * @property int|null $parent_id
 * @property string $label_default
 * @property string|null $icon
 * @property string|null $route_name
 * @property string|null $permission_code
 * @property string|null $feature_code
 * @property int $sort_order
 * @property bool $is_core
 * @property bool $is_active
 */
class Menu extends Model
{
    protected $fillable = [
        'code',
        'parent_id',
        'label_default',
        'icon',
        'route_name',
        'permission_code',
        'feature_code',
        'sort_order',
        'is_core',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_core' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Menu, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }
}
