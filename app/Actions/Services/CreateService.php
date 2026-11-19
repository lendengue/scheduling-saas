<?php

namespace App\Actions\Services;

use App\Models\Service;
use App\Services\Availability\AvailabilityCache;
use Illuminate\Support\Facades\DB;

/**
 * Adds a service to the end of the current tenant's catalog.
 */
class CreateService
{
    public function __construct(private readonly AvailabilityCache $cache) {}

    /**
     * @param  array{name: string, description: string|null, duration_minutes: int, buffer_after_minutes: int, price_cents: int, is_active: bool}  $attributes
     * @param  list<int>  $staffProfileIds  professionals who perform it
     */
    public function handle(array $attributes, array $staffProfileIds): Service
    {
        $service = DB::transaction(function () use ($attributes, $staffProfileIds): Service {
            $service = new Service($attributes);
            $service->position = (int) Service::withTrashed()->max('position') + 1;
            $service->save();
            $service->staffProfiles()->sync($staffProfileIds);

            return $service;
        });

        $this->cache->flush($service->tenant_id);

        return $service;
    }
}
