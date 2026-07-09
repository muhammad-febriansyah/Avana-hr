<?php

namespace App\Http\Controllers\Platform;

use App\Enums\Permission as PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\Tenant;
use App\Models\TenantMenuOverride;
use App\Services\MenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Super Admin management of the platform menu registry and per-tenant
 * availability overrides.
 */
class MenuController extends Controller
{
    /**
     * Icon names the frontend can render (mirror of resources/js/lib/menu-icons.ts).
     *
     * @var list<string>
     */
    private array $iconOptions = [
        'LayoutGrid', 'Users', 'Network', 'Clock', 'CalendarCheck', 'Wallet',
        'Inbox', 'GitBranch', 'Contact', 'Calendar', 'BarChart3', 'ShieldCheck',
        'ScrollText', 'Settings', 'Building2', 'MapPin', 'SlidersHorizontal',
    ];

    public function __construct(private MenuService $menuService, private PermissionRegistrar $registrar) {}

    public function index(Request $request): Response
    {
        $menus = Menu::orderBy('sort_order')->get();

        $overrides = TenantMenuOverride::query()
            ->get()
            ->groupBy('tenant_id')
            ->map(fn ($rows) => $rows->pluck('is_enabled', 'menu_id'));

        [$previewRoles, $preview] = $this->resolvePreview($request);

        return Inertia::render('platform/menus/index', [
            'menus' => $this->registryTree($menus),
            'tenants' => Tenant::orderBy('name')->get(['id', 'name', 'plan_id']),
            'overrides' => $overrides,
            'previewRoles' => $previewRoles,
            'preview' => $preview,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('platform/menus/form', $this->formProps(null));
    }

    public function edit(Menu $menu): Response
    {
        return Inertia::render('platform/menus/form', $this->formProps($menu));
    }

    /**
     * Shared props for the create/edit form page.
     *
     * @return array<string, mixed>
     */
    private function formProps(?Menu $menu): array
    {
        return [
            'menu' => $menu === null ? null : [
                'id' => $menu->id,
                'code' => $menu->code,
                'parent_id' => $menu->parent_id,
                'label_default' => $menu->label_default,
                'icon' => $menu->icon,
                'route_name' => $menu->route_name,
                'permission_code' => $menu->permission_code,
                'feature_code' => $menu->feature_code,
                'sort_order' => $menu->sort_order,
                'is_core' => $menu->is_core,
                'is_active' => $menu->is_active,
            ],
            'parents' => Menu::whereNull('parent_id')
                ->orderBy('sort_order')
                ->get(['id', 'label_default']),
            'permissionOptions' => PermissionEnum::values(),
            'featureOptions' => ['crm', 'calendar', 'ai'],
            'iconOptions' => $this->iconOptions,
        ];
    }

    /**
     * Resolve the "preview as tenant X role Y" panel data from query params.
     *
     * @return array{0: list<array{id: int, name: string}>, 1: list<array<string, mixed>>|null}
     */
    private function resolvePreview(Request $request): array
    {
        $tenantId = $request->integer('preview_tenant_id');

        if ($tenantId === 0) {
            return [[], null];
        }

        $tenant = Tenant::find($tenantId);

        if ($tenant === null) {
            return [[], null];
        }

        $this->registrar->setPermissionsTeamId($tenant->id);

        /** @var list<array{id: int, name: string}> $roles */
        $roles = Role::where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Role $role): array => ['id' => $role->id, 'name' => $role->name])
            ->all();

        $roleId = $request->integer('preview_role_id');
        $role = $roleId !== 0 ? Role::where('tenant_id', $tenant->id)->find($roleId) : null;

        return [$roles, $this->menuService->previewForTenantRole($tenant, $role)];
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateMenu($request);
        Menu::create($validated);

        return to_route('platform.menus.index')->with('success', 'Menu dibuat.');
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $validated = $this->validateMenu($request, $menu->id);

        // Prevent a menu becoming its own ancestor (2-level tree stays shallow).
        if (($validated['parent_id'] ?? null) === $menu->id) {
            return back()->withErrors(['parent_id' => 'Menu tidak boleh menjadi induk dirinya sendiri.']);
        }

        $menu->update($validated);

        return to_route('platform.menus.index')->with('success', 'Menu diperbarui.');
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        $menu->delete();

        return back()->with('success', 'Menu dihapus.');
    }

    /**
     * Bulk reorder / re-parent the registry after a drag-and-drop.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', Rule::exists('menus', 'id')],
            'items.*.parent_id' => ['nullable', Rule::exists('menus', 'id')],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($validated): void {
            foreach ($validated['items'] as $item) {
                Menu::whereKey($item['id'])->update([
                    'parent_id' => $item['parent_id'] ?? null,
                    'sort_order' => $item['sort_order'],
                ]);
            }
        });

        return back()->with('success', 'Urutan menu disimpan.');
    }

    /**
     * Force a menu enabled/disabled for one tenant, or clear the override to
     * fall back to the plan default (is_enabled = null).
     */
    public function availability(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', Rule::exists('tenants', 'id')],
            'menu_id' => ['required', Rule::exists('menus', 'id')],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        if ($validated['is_enabled'] === null) {
            TenantMenuOverride::where('tenant_id', $validated['tenant_id'])
                ->where('menu_id', $validated['menu_id'])
                ->delete();
        } else {
            TenantMenuOverride::updateOrCreate(
                ['tenant_id' => $validated['tenant_id'], 'menu_id' => $validated['menu_id']],
                ['is_enabled' => $validated['is_enabled']],
            );
        }

        return back()->with('success', 'Ketersediaan menu diperbarui.');
    }

    /**
     * @return array{code: string, parent_id: int|null, label_default: string, icon: string|null, route_name: string|null, permission_code: string|null, feature_code: string|null, sort_order: int, is_core: bool, is_active: bool}
     */
    private function validateMenu(Request $request, ?int $ignoreId = null): array
    {
        /** @var array{code: string, parent_id: int|null, label_default: string, icon: string|null, route_name: string|null, permission_code: string|null, feature_code: string|null, sort_order: int, is_core: bool, is_active: bool} $validated */
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('menus', 'code')->ignore($ignoreId)],
            'parent_id' => ['nullable', Rule::exists('menus', 'id')],
            'label_default' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'route_name' => ['nullable', 'string', 'max:255'],
            'permission_code' => ['nullable', Rule::in(PermissionEnum::values())],
            'feature_code' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_core' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ], [
            'code.unique' => 'Kode menu sudah digunakan.',
        ]);

        return $validated;
    }

    /**
     * Two-level registry tree for the editor.
     *
     * @param  Collection<int, Menu>  $menus
     * @return list<array<string, mixed>>
     */
    private function registryTree($menus): array
    {
        $byParent = $menus->groupBy('parent_id');

        $map = fn (Menu $menu): array => [
            'id' => $menu->id,
            'code' => $menu->code,
            'parent_id' => $menu->parent_id,
            'label_default' => $menu->label_default,
            'icon' => $menu->icon,
            'route_name' => $menu->route_name,
            'permission_code' => $menu->permission_code,
            'feature_code' => $menu->feature_code,
            'sort_order' => $menu->sort_order,
            'is_core' => $menu->is_core,
            'is_active' => $menu->is_active,
        ];

        return array_values(
            $byParent->get(null, collect())
                ->map(fn (Menu $parent): array => [
                    ...$map($parent),
                    'children' => array_values($byParent->get($parent->id, collect())->map($map)->all()),
                ])
                ->all(),
        );
    }
}
