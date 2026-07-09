<?php

namespace App\Http\Controllers;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function __construct(private PermissionRegistrar $registrar) {}

    /**
     * List the tenant's roles.
     */
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;

        $roles = Role::query()
            ->where('tenant_id', $tenantId)
            ->withCount('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'is_default' => $this->isDefaultRole($role->name),
                'permissions_count' => $role->permissions_count,
            ]);

        return Inertia::render('roles/index', [
            'roles' => $roles,
        ]);
    }

    /**
     * Full-page create form (the permission catalog is too large for a modal).
     */
    public function create(): Response
    {
        return Inertia::render('roles/form', [
            'role' => null,
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function edit(Request $request, Role $role): Response
    {
        $this->authorizeTenantRole($request, $role);
        $role->load('permissions:id,name');

        return Inertia::render('roles/form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'is_default' => $this->isDefaultRole($role->name),
                'permissions' => $role->permissions->pluck('name')->all(),
            ],
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $this->validatePayload($request, $tenantId);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'tenant_id' => $tenantId,
        ]);
        $role->syncPermissions($validated['permissions']);

        $this->registrar->forgetCachedPermissions();

        return to_route('roles.index')->with('success', 'Peran dibuat.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeTenantRole($request, $role);

        $isDefault = $this->isDefaultRole($role->name);
        $validated = $this->validatePayload($request, $request->user()->tenant_id, $isDefault ? $role->id : null);

        // Default roles keep their name; only permissions are editable.
        if (! $isDefault) {
            $role->update(['name' => $validated['name']]);
        }
        $role->syncPermissions($validated['permissions']);

        $this->registrar->forgetCachedPermissions();

        return to_route('roles.index')->with('success', 'Peran diperbarui.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeTenantRole($request, $role);

        if ($this->isDefaultRole($role->name)) {
            return back()->with('error', 'Peran bawaan tidak dapat dihapus.');
        }

        $role->delete();
        $this->registrar->forgetCachedPermissions();

        return back()->with('success', 'Peran dihapus.');
    }

    /**
     * Validate the role payload; permissions must be tenant-scoped (no platform.*).
     *
     * @return array{name: string, permissions: list<string>}
     */
    private function validatePayload(Request $request, ?int $tenantId, int|string|null $ignoreId = null): array
    {
        /** @var array{name: string, permissions: list<string>} $validated */
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')
                    ->where('tenant_id', $tenantId)
                    ->where('guard_name', 'web')
                    ->ignore($ignoreId),
            ],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(PermissionEnum::tenantValues())],
        ], [
            'name.required' => 'Nama peran wajib diisi.',
            'name.unique' => 'Nama peran sudah digunakan.',
        ]);

        return $validated;
    }

    /**
     * Reject roles belonging to another tenant (defence in depth beyond the scope).
     */
    private function authorizeTenantRole(Request $request, Role $role): void
    {
        abort_unless($role->getAttribute('tenant_id') === $request->user()->tenant_id, 404);
    }

    private function isDefaultRole(string $name): bool
    {
        return in_array($name, array_map(
            fn (RoleEnum $r): string => $r->value,
            RoleEnum::tenantRoles(),
        ), true);
    }

    /**
     * Permission catalog grouped by module prefix, for the checklist UI.
     *
     * @return array<string, list<string>>
     */
    private function permissionGroups(): array
    {
        $groups = [];
        foreach (PermissionEnum::tenantValues() as $permission) {
            $module = explode('.', $permission)[0];
            $groups[$module][] = $permission;
        }

        return $groups;
    }
}
