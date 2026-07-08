<?php

namespace App\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Scopes a model to the current tenant (row-level multi-tenancy).
 *
 * Adds a global scope filtering by the authenticated user's `tenant_id`
 * and auto-fills `tenant_id` on create. Super Admins (`tenant_id = null`)
 * bypass the scope and must use `withoutTenancy()` explicitly.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::creating(function ($model): void {
            if ($model->tenant_id === null && Auth::check() && Auth::user()->tenant_id !== null) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder): void {
            if (Auth::check() && Auth::user()->tenant_id !== null) {
                $builder->where(
                    $builder->getModel()->getTable().'.tenant_id',
                    Auth::user()->tenant_id,
                );
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Remove the tenant global scope (platform / Super Admin layer only).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutTenancy(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }
}
