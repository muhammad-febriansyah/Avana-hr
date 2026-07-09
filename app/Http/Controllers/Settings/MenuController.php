<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\TenantMenuSetting;
use App\Services\MenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Admin Tenant management of the sidebar: show/hide, reorder (drag & drop),
 * re-group, rename, and per-role visibility — bounded by what Super Admin has
 * made available to the tenant's plan.
 */
class MenuController extends Controller
{
    public function __construct(private MenuService $menuService) {}

    public function index(Request $request): Response
    {
        $tenant = $request->user()->tenant;

        $available = $this->menuService->availableForTenant($tenant);

        /** @var array<int, TenantMenuSetting> $settings */
        $settings = TenantMenuSetting::with('roleVisibilities')
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('menu_id')
            ->all();

        $roles = Role::where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('menus/manage', [
            'menus' => $this->editorTree($available, $settings),
            'roles' => $roles,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $availableIds = $this->menuService->availableForTenant($tenant)->pluck('id')->all();
        $roleIds = Role::where('tenant_id', $tenant->id)->pluck('id')->all();
        $coreIds = Menu::where('is_core', true)->pluck('id')->all();

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.menu_id' => ['required', Rule::in($availableIds)],
            'items.*.is_visible' => ['required', 'boolean'],
            'items.*.label_alias' => ['nullable', 'string', 'max:255'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
            'items.*.parent_id' => ['nullable', Rule::in($availableIds)],
            'items.*.role_ids' => ['array'],
            'items.*.role_ids.*' => [Rule::in($roleIds)],
        ]);

        DB::transaction(function () use ($validated, $tenant, $coreIds): void {
            foreach ($validated['items'] as $item) {
                $setting = TenantMenuSetting::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'menu_id' => $item['menu_id']],
                    [
                        // Core menus can never be hidden.
                        'is_visible' => in_array($item['menu_id'], $coreIds, true) ? true : $item['is_visible'],
                        'label_alias' => $item['label_alias'] ?? null,
                        'sort_order' => $item['sort_order'],
                        'parent_override_id' => $item['parent_id'] ?? null,
                    ],
                );

                $setting->roleVisibilities()->delete();
                foreach ($item['role_ids'] ?? [] as $roleId) {
                    $setting->roleVisibilities()->create(['role_id' => $roleId]);
                }
            }
        });

        return back()->with('success', 'Menu diperbarui.');
    }

    /**
     * Reset every tenant menu customization back to registry defaults.
     */
    public function reset(Request $request): RedirectResponse
    {
        TenantMenuSetting::where('tenant_id', $request->user()->tenant->id)->delete();

        return back()->with('success', 'Menu dikembalikan ke default.');
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
     * Build the two-level editor tree with each menu's current settings.
     *
     * @param  Collection<int, Menu>  $available
     * @param  array<int, TenantMenuSetting>  $settings  keyed by menu_id
     * @return list<array<string, mixed>>
     */
    private function editorTree(Collection $available, array $settings): array
    {
        $node = function (Menu $menu) use ($settings): array {
            $setting = $this->settingFor($settings, $menu->id);

            return [
                'id' => $menu->id,
                'code' => $menu->code,
                'label_default' => $menu->label_default,
                'label_alias' => $setting?->label_alias,
                'icon' => $menu->icon,
                'is_core' => $menu->is_core,
                'is_visible' => $setting !== null ? $setting->is_visible : true,
                'sort_order' => $setting !== null && $setting->sort_order !== null
                    ? $setting->sort_order
                    : $menu->sort_order,
                'parent_id' => $setting !== null && $setting->parent_override_id !== null
                    ? $setting->parent_override_id
                    : $menu->parent_id,
                'role_ids' => $setting?->roleVisibilities->pluck('role_id')->values()->all() ?? [],
            ];
        };

        $nodes = $available->map($node)->values();
        $byParent = $nodes->groupBy('parent_id');

        $sortNodes = fn (Collection $items): array => $items
            ->sortBy('sort_order')
            ->values()
            ->all();

        return array_values(
            collect($sortNodes($byParent->get(null, collect())))
                ->map(fn (array $parent): array => [
                    ...$parent,
                    'children' => $sortNodes($byParent->get($parent['id'], collect())),
                ])
                ->all(),
        );
    }
}
