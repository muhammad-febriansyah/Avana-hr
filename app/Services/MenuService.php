<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\Tenant;
use App\Models\TenantMenuOverride;
use App\Models\TenantMenuSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

/**
 * Resolves the sidebar menu for a user through four layers:
 * registry → plan/override availability → tenant settings → runtime
 * (role visibility + permission). Results are cached per tenant + role set
 * and invalidated via versioned keys (see flushGlobal / flushTenant).
 */
class MenuService
{
    private const CACHE_TTL = 3600;

    /**
     * The resolved, filtered menu tree for the given user (empty for guests
     * and platform Super Admins, who use the hard-coded platform nav).
     *
     * @return list<array<string, mixed>>
     */
    public function forUser(User $user): array
    {
        if ($user->tenant_id === null) {
            return [];
        }

        $roleIds = array_values(
            $user->roles()->pluck('id')->map(fn ($id): int => (int) $id)->sort()->all(),
        );
        $roleHash = md5(implode(',', $roleIds));

        $key = sprintf(
            'menu:gv%d:tenant:%d:v%d:role:%s',
            (int) Cache::get('menu:global:ver', 0),
            $user->tenant_id,
            (int) Cache::get("menu:tenant:{$user->tenant_id}:ver", 0),
            $roleHash,
        );

        return Cache::remember(
            $key,
            self::CACHE_TTL,
            fn (): array => $this->resolveTree($user->tenant, $roleIds, fn (string $p): bool => $user->can($p)),
        );
    }

    /**
     * Resolve the sidebar as it would appear for a given tenant + role — used
     * by the Super Admin "preview as tenant X role Y" panel. Not cached.
     *
     * @return list<array<string, mixed>>
     */
    public function previewForTenantRole(Tenant $tenant, ?Role $role): array
    {
        $roleIds = $role !== null ? [(int) $role->id] : [];
        $checker = $role !== null
            ? fn (string $permission): bool => $role->hasPermissionTo($permission, 'web')
            : fn (string $permission): bool => true;

        return $this->resolveTree($tenant, $roleIds, $checker);
    }

    /**
     * Menus available to a tenant (registry filtered by plan features and
     * Super Admin per-tenant overrides). Used by the tenant settings editor.
     *
     * @return Collection<int, Menu>
     */
    public function availableForTenant(Tenant $tenant): Collection
    {
        $features = $this->planFeatures($tenant);
        $overrides = $this->overrides($tenant->id);

        return Menu::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Menu $menu): bool => $this->isAvailable($menu, $features, $overrides))
            ->values();
    }

    public static function flushGlobal(): void
    {
        self::bumpVersion('menu:global:ver');
    }

    public static function flushTenant(?int $tenantId): void
    {
        if ($tenantId !== null) {
            self::bumpVersion("menu:tenant:{$tenantId}:ver");
        }
    }

    /**
     * Bump a cache-version counter. Uses get+forever rather than
     * Cache::increment so it also works on stores (e.g. database) where
     * incrementing a missing key is a no-op.
     */
    private static function bumpVersion(string $key): void
    {
        Cache::forever($key, (int) Cache::get($key, 0) + 1);
    }

    /**
     * Resolve the filtered, nested menu tree for a tenant.
     *
     * @param  list<int>  $roleIds
     * @param  callable(string): bool  $can  permission checker
     * @return list<array<string, mixed>>
     */
    private function resolveTree(?Tenant $tenant, array $roleIds, callable $can): array
    {
        if ($tenant === null) {
            return [];
        }

        $features = $this->planFeatures($tenant);
        $overrides = $this->overrides($tenant->id);

        /** @var array<int, TenantMenuSetting> $settings keyed by menu_id */
        $settings = TenantMenuSetting::with('roleVisibilities')
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('menu_id')
            ->all();

        $menus = Menu::where('is_active', true)->orderBy('sort_order')->get();

        // Resolve every menu to a normalized node, keeping only those that pass
        // all filters. Effective parent/order/label come from tenant settings.
        $nodes = [];
        foreach ($menus as $menu) {
            if (! $this->isAvailable($menu, $features, $overrides)) {
                continue;
            }

            $setting = $this->settingFor($settings, $menu->id);

            if (! $this->isVisible($menu, $setting, $roleIds)) {
                continue;
            }

            if ($menu->permission_code !== null && ! $can($menu->permission_code)) {
                continue;
            }

            $nodes[$menu->id] = [
                'id' => $menu->id,
                'code' => $menu->code,
                'parent_id' => $setting !== null && $setting->parent_override_id !== null
                    ? $setting->parent_override_id
                    : $menu->parent_id,
                'label' => $setting !== null && $setting->label_alias !== null
                    ? $setting->label_alias
                    : $menu->label_default,
                'icon' => $menu->icon,
                'url' => $this->resolveUrl($menu->route_name),
                'sort_order' => $setting !== null && $setting->sort_order !== null
                    ? $setting->sort_order
                    : $menu->sort_order,
                'has_registry_children' => false,
            ];
        }

        return $this->assembleTree($nodes, $menus);
    }

    /**
     * Nest passing nodes by effective parent, sort, and prune empty groups.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  Collection<int, Menu>  $menus
     * @return list<array<string, mixed>>
     */
    private function assembleTree(array $nodes, Collection $menus): array
    {
        // Mark which menus have registry children so childless placeholders are
        // kept while emptied group parents are pruned.
        $childCount = [];
        foreach ($menus as $menu) {
            if ($menu->parent_id !== null) {
                $childCount[$menu->parent_id] = ($childCount[$menu->parent_id] ?? 0) + 1;
            }
        }

        $byParent = [];
        foreach ($nodes as $node) {
            $node['has_registry_children'] = ($childCount[$node['id']] ?? 0) > 0;
            $byParent[$node['parent_id'] ?? 0][] = $node;
        }

        $render = function (?int $parentId) use (&$render, $byParent): array {
            $items = $byParent[$parentId ?? 0] ?? [];
            usort($items, fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);

            $out = [];
            foreach ($items as $item) {
                $children = $render($item['id']);

                // Drop a group parent (no own url) that has no visible children.
                if ($item['url'] === null && $item['has_registry_children'] && $children === []) {
                    continue;
                }

                $out[] = [
                    'code' => $item['code'],
                    'label' => $item['label'],
                    'icon' => $item['icon'],
                    'url' => $item['url'],
                    'children' => $children,
                ];
            }

            return $out;
        };

        return $render(null);
    }

    /**
     * @param  array<int, TenantMenuSetting>  $settings
     */
    private function settingFor(array $settings, int $menuId): ?TenantMenuSetting
    {
        if (! array_key_exists($menuId, $settings)) {
            return null;
        }

        return $settings[$menuId];
    }

    /**
     * A menu is visible unless a tenant setting hides it (core menus can never
     * be hidden) or role-visibility rows exclude the user's roles.
     *
     * @param  list<int>  $roleIds
     */
    private function isVisible(Menu $menu, ?TenantMenuSetting $setting, array $roleIds): bool
    {
        if (! $menu->is_core && $setting !== null && ! $setting->is_visible) {
            return false;
        }

        $allowedRoleIds = $setting?->roleVisibilities->pluck('role_id')->all() ?? [];

        if ($allowedRoleIds === []) {
            return true;
        }

        return array_intersect($allowedRoleIds, $roleIds) !== [];
    }

    /**
     * @param  list<string>  $features
     * @param  array<int, bool>  $overrides  menu_id => is_enabled
     */
    private function isAvailable(Menu $menu, array $features, array $overrides): bool
    {
        if (array_key_exists($menu->id, $overrides)) {
            return $overrides[$menu->id];
        }

        return $menu->feature_code === null || in_array($menu->feature_code, $features, true);
    }

    /**
     * @return list<string>
     */
    private function planFeatures(Tenant $tenant): array
    {
        $plan = $tenant->plan;

        if ($plan === null) {
            return [];
        }

        return array_values(
            $plan->features()->pluck('feature_code')->map(fn ($f): string => (string) $f)->all(),
        );
    }

    /**
     * @return array<int, bool> menu_id => is_enabled
     */
    private function overrides(int $tenantId): array
    {
        return TenantMenuOverride::where('tenant_id', $tenantId)
            ->pluck('is_enabled', 'menu_id')
            ->map(fn ($enabled): bool => (bool) $enabled)
            ->all();
    }

    private function resolveUrl(?string $routeName): ?string
    {
        if ($routeName !== null && Route::has($routeName)) {
            return route($routeName);
        }

        return null;
    }
}
