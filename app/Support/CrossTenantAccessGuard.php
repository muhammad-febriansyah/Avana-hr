<?php

namespace App\Support;

use App\Concerns\BelongsToTenant;
use App\Models\SecurityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Records attempts by a tenant user to reach a record that belongs to another
 * tenant. Row-level tenancy already hides such records (route-model binding
 * fails with a 404); this adds the security-log trail required by QA-0111.
 */
class CrossTenantAccessGuard
{
    /**
     * Log a cross-tenant access attempt, if that's what caused the 404.
     * A genuine "record does not exist anywhere" 404 is left untouched.
     *
     * @param  ModelNotFoundException<Model>  $exception
     */
    public static function log(ModelNotFoundException $exception, Request $request): void
    {
        $user = Auth::user();

        // Guests and Super Admins (tenant_id null) are not tenant-scoped.
        if ($user === null || $user->tenant_id === null) {
            return;
        }

        /** @var class-string|null $modelClass */
        $modelClass = $exception->getModel();

        if ($modelClass === null || ! class_exists($modelClass)) {
            return;
        }

        if (! in_array(BelongsToTenant::class, class_uses_recursive($modelClass), true)) {
            return;
        }

        $ids = $exception->getIds();

        if ($ids === []) {
            return;
        }

        $existsInAnotherTenant = $modelClass::query()
            ->withoutTenancy()
            ->whereKey($ids)
            ->where('tenant_id', '!=', $user->tenant_id)
            ->exists();

        if (! $existsInAnotherTenant) {
            return;
        }

        SecurityLog::create([
            'user_id' => $user->id,
            'event' => 'cross_tenant_access_denied',
            'context' => [
                'model' => class_basename($modelClass),
                'ids' => $ids,
                'path' => $request->path(),
                'method' => $request->method(),
            ],
            'ip_address' => $request->ip(),
        ]);
    }
}
