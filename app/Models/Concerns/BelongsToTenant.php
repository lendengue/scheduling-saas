<?php

namespace App\Models\Concerns;

use App\Exceptions\TenantMismatchException;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $currentId = app(CurrentTenant::class)->id();
            $tenantId = $model->getAttribute('tenant_id');

            if ($tenantId === null) {
                $model->setAttribute('tenant_id', $currentId);

                return;
            }

            if ($currentId !== null && $tenantId !== $currentId) {
                throw new TenantMismatchException(sprintf(
                    'Cannot create a [%s] for tenant [%s] while tenant [%s] is active.',
                    $model::class, $tenantId, $currentId,
                ));
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id') && $model->getOriginal('tenant_id') !== null) {
                throw new TenantMismatchException(sprintf('A [%s] cannot be moved to another tenant.', $model::class));
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
}
