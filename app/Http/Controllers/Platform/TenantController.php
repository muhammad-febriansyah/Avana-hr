<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Tenant\ProvisionTenantDefaults;
use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\PermissionRegistrar;

/**
 * Super Admin management of tenants across the whole platform. Tenant models
 * carry no tenant scope, so all queries here are naturally cross-tenant.
 */
class TenantController extends Controller
{
    public function __construct(private PermissionRegistrar $registrar) {}

    public function index(): Response
    {
        $tenants = Tenant::query()
            ->with('plan:id,name')
            ->withCount(['users', 'employees'])
            ->orderBy('name')
            ->get()
            ->map(fn (Tenant $tenant): array => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'plan' => $tenant->plan?->name,
                'employee_id_prefix' => $tenant->employee_id_prefix,
                'is_active' => $tenant->is_active,
                'users_count' => $tenant->users_count,
                'employees_count' => $tenant->employees_count,
            ]);

        return Inertia::render('platform/tenants/index', [
            'tenants' => $tenants,
            'plans' => Plan::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Provision a brand-new, fully isolated tenant: default roles plus its
     * first Company Admin. No data is shared with existing tenants.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:tenants,slug'],
            'plan_id' => ['nullable', Rule::exists('plans', 'id')],
            'employee_id_prefix' => ['nullable', 'string', 'max:10'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8'],
        ], [
            'slug.unique' => 'Slug sudah digunakan.',
            'admin_email.unique' => 'Email admin sudah digunakan.',
        ]);

        DB::transaction(function () use ($validated): void {
            $tenant = Tenant::create([
                'plan_id' => $validated['plan_id'] ?? null,
                'name' => $validated['name'],
                'slug' => Str::lower($validated['slug']),
                'employee_id_prefix' => $validated['employee_id_prefix'] ?? null,
                'is_active' => true,
            ]);

            app(ProvisionTenantDefaults::class)->handle($tenant);

            $previousTeam = $this->registrar->getPermissionsTeamId();
            $this->registrar->setPermissionsTeamId($tenant->id);

            $admin = User::create([
                'tenant_id' => $tenant->id,
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => Hash::make($validated['admin_password']),
                'is_active' => true,
            ]);
            $admin->assignRole(RoleEnum::CompanyAdmin->value);

            $this->registrar->setPermissionsTeamId($previousTeam);
        });

        return back()->with('success', 'Tenant baru dibuat.');
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plan_id' => ['nullable', Rule::exists('plans', 'id')],
            'employee_id_prefix' => ['nullable', 'string', 'max:10'],
            'is_active' => ['required', 'boolean'],
        ]);

        $tenant->update($validated);

        return back()->with('success', 'Tenant diperbarui.');
    }
}
