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
                $authUser = auth()->check() ? auth()->user() : null;
                $sessionTenantId = $authUser?->tenant_id ?? session('tenant_id');


                // If running in console or tests with explicit tenant_id, allow it
                if ((app()->runningInConsole() || app()->environment('testing')) && !empty($model->tenant_id)) {
                    return;
                }

                // For all ordinary web/API requests, strictly enforce server-side session tenant context
                if (!empty($sessionTenantId)) {
                    $model->tenant_id = $sessionTenantId;
                }

                // If this is a child financial record without session, inherit from parent record
                if (empty($model->tenant_id)) {
                    if (isset($model->saleId) && !empty($model->saleId)) {
                        $parentSale = \App\Models\Sale::withoutGlobalScopes()->find($model->saleId);
                        if ($parentSale && !empty($parentSale->tenant_id)) {
                            $model->tenant_id = $parentSale->tenant_id;
                        }
                    } elseif (isset($model->transfer_id) && !empty($model->transfer_id)) {
                        $parentTransfer = \App\Models\Transfer::withoutGlobalScopes()->find($model->transfer_id);
                        if ($parentTransfer && !empty($parentTransfer->tenant_id)) {
                            $model->tenant_id = $parentTransfer->tenant_id;
                        }
                    }
                }

                // In testing, permit explicitly-null User creation specifically for orphan authentication rejection tests
                if (app()->environment('testing') && $model instanceof \App\Models\User && array_key_exists('tenant_id', $model->getAttributes()) && is_null($model->getAttributes()['tenant_id'])) {
                    return;
                }

                // Fail-closed invariant: Reject persisting model without tenant_id when SaaS is active
                if (empty($model->tenant_id)) {
                    throw new \RuntimeException("Multi-Tenant Integrity Violation: Entity " . get_class($model) . " cannot be persisted without an authoritative tenant_id.");
                }
            }
        });

        static::updating(function ($model) {
            if (config('saas.enabled')) {
                if ($model->isDirty('tenant_id')) {
                    $originalTenantId = $model->getOriginal('tenant_id');
                    // Invariant: Once an authoritative tenant_id is established, it is strictly immutable.
                    if (!empty($originalTenantId) && $originalTenantId !== $model->tenant_id) {
                        throw new \App\Exceptions\SecurityException(
                            "Cross-Tenant Security Violation: Immutable attribute 'tenant_id' cannot be altered on persisted record " . get_class($model) . " [{$model->getKey()}]."
                        );
                    }
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

    /**
     * Create a new Eloquent query builder for the model that intercepts bulk updates on tenant_id.
     */
    public function newEloquentBuilder($query)
    {
        return new class($query) extends \Illuminate\Database\Eloquent\Builder {
            public function update(array $values)
            {
                if (config('saas.enabled') && array_key_exists('tenant_id', $values)) {
                    if ($this->model && $this->model->exists) {
                        $originalTenantId = $this->model->getOriginal('tenant_id');
                        if (!empty($originalTenantId) && $originalTenantId !== $values['tenant_id']) {
                            throw new \App\Exceptions\SecurityException(
                                "Cross-Tenant Security Violation: Immutable attribute 'tenant_id' cannot be altered on persisted record " . get_class($this->model) . " [{$this->model->getKey()}]."
                            );
                        }
                    } else {
                        throw new \App\Exceptions\SecurityException(
                            "Cross-Tenant Security Violation: Bulk query mutation of immutable attribute 'tenant_id' is strictly forbidden."
                        );
                    }
                }
                return parent::update($values);
            }
        };
    }
}
