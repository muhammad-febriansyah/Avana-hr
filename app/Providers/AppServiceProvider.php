<?php

namespace App\Providers;

use App\Models\Menu;
use App\Models\TenantMenuOverride;
use App\Models\TenantMenuRoleVisibility;
use App\Models\TenantMenuSetting;
use App\Models\User;
use App\Services\MenuService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureMenuCache();
    }

    /**
     * Invalidate the resolved-menu cache when the registry or a tenant's menu
     * configuration changes (versioned keys — see MenuService).
     */
    protected function configureMenuCache(): void
    {
        Menu::saved(function (): void {
            MenuService::flushGlobal();
        });
        Menu::deleted(function (): void {
            MenuService::flushGlobal();
        });

        $flushOverride = function (TenantMenuOverride $record): void {
            MenuService::flushTenant($record->tenant_id);
        };
        TenantMenuOverride::saved($flushOverride);
        TenantMenuOverride::deleted($flushOverride);

        $flushSetting = function (TenantMenuSetting $record): void {
            MenuService::flushTenant($record->tenant_id);
        };
        TenantMenuSetting::saved($flushSetting);
        TenantMenuSetting::deleted($flushSetting);

        $flushViaSetting = function (TenantMenuRoleVisibility $visibility): void {
            $tenantId = TenantMenuSetting::whereKey($visibility->tenant_menu_setting_id)->value('tenant_id');

            if (is_int($tenantId)) {
                MenuService::flushTenant($tenantId);
            }
        };
        TenantMenuRoleVisibility::saved($flushViaSetting);
        TenantMenuRoleVisibility::deleted($flushViaSetting);
    }

    /**
     * Super Admins (platform users, tenant_id null) bypass all permission checks.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(function (User $user): ?bool {
            return $user->tenant_id === null ? true : null;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
