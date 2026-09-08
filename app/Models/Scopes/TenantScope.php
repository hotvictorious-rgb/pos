<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // 1. If SaaS module is disabled in config/env, bypass tenant scoping cleanly
        if (!config('saas.enabled')) {
            return;
        }

        // Authoritative identity chain: check if auth already has a loaded user instance without triggering re-entrant database retrieval
        $authUser = Auth::hasUser() ? Auth::user() : null;
        $tenantId = $authUser?->tenant_id ?? (session()->has('tenant_id') && !empty(session('tenant_id')) ? session('tenant_id') : null);

        if (!empty($tenantId)) {
            $tableName = $model->getTable();
            $builder->where("{$tableName}.tenant_id", $tenantId);
        } else {
            // 2. FAIL-CLOSED GUARD: When SaaS is enabled and tenant context is missing or null, return 0 rows
            $builder->whereRaw('1 = 0');
        }
    }
}
