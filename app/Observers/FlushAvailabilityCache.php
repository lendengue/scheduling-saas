<?php

namespace App\Observers;

use App\Services\Availability\AvailabilityCache;
use Illuminate\Database\Eloquent\Model;

/**
 * Invalidates the tenant's availability cache when a model that shapes
 * availability (appointments, time off, working hours, professionals,
 * services) changes.
 */
class FlushAvailabilityCache
{
    public function __construct(private readonly AvailabilityCache $cache) {}

    public function saved(Model $model): void
    {
        $this->flush($model);
    }

    public function deleted(Model $model): void
    {
        $this->flush($model);
    }

    private function flush(Model $model): void
    {
        $tenantId = $model->getAttribute('tenant_id');

        $this->cache->flush(is_string($tenantId) ? $tenantId : null);
    }
}
