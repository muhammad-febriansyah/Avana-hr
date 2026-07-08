<?php

namespace App\Actions\Tenant;

use App\Enums\Role as RoleEnum;
use App\Models\Tenant;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Provisions the default set of roles (with their permissions) for a tenant.
 *
 * Assumes global permissions are already seeded (see PermissionSeeder). Runs
 * within the tenant's team context so roles carry the correct `tenant_id`.
 */
class ProvisionTenantDefaults
{
    public function __construct(private PermissionRegistrar $registrar) {}

    public function handle(Tenant $tenant): void
    {
        $previousTeam = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId($tenant->id);

        foreach (RoleEnum::tenantRoles() as $role) {
            Role::findOrCreate($role->value, 'web')
                ->syncPermissions($role->permissions());
        }

        $this->registrar->setPermissionsTeamId($previousTeam);
        $this->registrar->forgetCachedPermissions();
    }
}
