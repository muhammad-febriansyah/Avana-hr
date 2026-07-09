<?php

namespace Database\Seeders;

use App\Actions\Tenant\ProvisionTenantDefaults;
use App\Enums\Role as RoleEnum;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds a demo tenant with default roles plus a Super Admin, so the app is
 * immediately usable after `migrate:fresh --seed`.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);

        $plans = [
            'essential' => 'Essential (HC Starter)',
            'professional' => 'Professional (HC Growth)',
            'enterprise360' => 'Enterprise 360 (HC Strategic)',
        ];
        foreach ($plans as $code => $name) {
            Plan::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        // Feature gates per plan (drives menu availability). Essential is the
        // baseline; higher tiers unlock CRM, Calendar and AI.
        $planFeatures = [
            'professional' => ['crm', 'calendar'],
            'enterprise360' => ['crm', 'calendar', 'ai'],
        ];
        foreach ($planFeatures as $code => $features) {
            $plan = Plan::where('code', $code)->first();
            foreach ($features as $feature) {
                $plan->features()->firstOrCreate(['feature_code' => $feature]);
            }
        }

        // Platform Super Admin (tenant_id null → full access via Gate::before, no spatie role).
        User::firstOrCreate(
            ['tenant_id' => null, 'email' => 'super@avana.test'],
            ['name' => 'Super Admin', 'password' => Hash::make('password'), 'is_active' => true],
        );

        // Demo tenant + default roles.
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo'],
            [
                'plan_id' => Plan::where('code', 'essential')->value('id'),
                'name' => 'Avana Demo Company',
                'employee_id_prefix' => 'DEMO',
                'is_active' => true,
            ],
        );

        app(ProvisionTenantDefaults::class)->handle($tenant);

        // Tenant Company Admin.
        $registrar->setPermissionsTeamId($tenant->id);
        $admin = User::firstOrCreate(
            ['tenant_id' => $tenant->id, 'email' => 'admin@avana.test'],
            ['name' => 'Company Admin', 'password' => Hash::make('password'), 'is_active' => true],
        );
        $admin->assignRole(RoleEnum::CompanyAdmin->value);

        $registrar->setPermissionsTeamId(null);
    }
}
