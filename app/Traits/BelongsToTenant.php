<?php

namespace App\Traits;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot the trait to attach global scope and auto-assign tenant_id.
     */
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (config('saas.enabled')) {
                $sessionTenantId = session('tenant_id');

                // If caller is an authenticated super-admin explicitly providing a target tenant_id, allow it
                $isSuperAdmin = \Illuminate\Support\Facades\Auth::check()
                    && method_exists(\Illuminate\Support\Facades\Auth::user(), 'isSuperAdmin')
                    && \Illuminate\Support\Facades\Auth::user()->isSuperAdmin();

                if ($isSuperAdmin && !empty($model->tenant_id)) {
                    return;
                }

                // If running in console (e.g. migrations/seeders) without session, allow explicit tenant_id
                if (app()->runningInConsole() && !empty($model->tenant_id)) {
                    return;
                }

                // For all ordinary web/API requests, strictly enforce server-side session tenant context
                if (!empty($sessionTenantId)) {
                    $model->tenant_id = $sessionTenantId;
                }
            }
        });
    }

    /**
     * Tenant relationship.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
