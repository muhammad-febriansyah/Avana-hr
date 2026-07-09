<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Models\User;
use App\Services\MenuService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private MenuService $menuService) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $isSuperAdmin = $user !== null && $user->tenant_id === null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'isSuperAdmin' => $isSuperAdmin,
                'permissions' => $this->resolvePermissions($user, $isSuperAdmin),
            ],
            'menu' => $user !== null ? $this->menuService->forUser($user) : [],
            'flash' => [
                'importResult' => $request->session()->get('importResult'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The current user's permission names. Super Admins implicitly hold every
     * permission (see the `Gate::before` bypass), so return the full catalog.
     *
     * @return list<string>
     */
    protected function resolvePermissions(?User $user, bool $isSuperAdmin): array
    {
        if ($user === null) {
            return [];
        }

        if ($isSuperAdmin) {
            return Permission::values();
        }

        return array_values(array_map(
            static fn (mixed $name): string => (string) $name,
            $user->getAllPermissions()->pluck('name')->all(),
        ));
    }
}
