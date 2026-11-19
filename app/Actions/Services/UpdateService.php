<?php

namespace App\Actions\Services;

use App\Models\Service;
use App\Services\Availability\AvailabilityCache;
use Illuminate\Support\Facades\DB;

class UpdateService
{
    public function __construct(private readonly AvailabilityCache $cache) {}

    /**
     * @param  array{name: string, description: string|null, duration_minutes: int, buffer_after_minutes: int, price_cents: int, is_active: bool}  $attributes
     * @param  list<int>  $staffProfileIds  professionals who perform it
     */
    public function handle(Service $service, array $attributes, array $staffProfileIds): Service
    {
        DB::transaction(function () use ($service, $attributes, $staffProfileIds): void {
            $service->update($attributes);
            $service->staffProfiles()->sync($staffProfileIds);
        });

        // Pivot changes fire no model events.
        $this->cache->flush($service->tenant_id);

        return $service;
    }
}
