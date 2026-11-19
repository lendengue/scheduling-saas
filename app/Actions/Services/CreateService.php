<?php

namespace App\Actions\Services;

use App\Models\Service;

/**
 * Adds a service to the end of the current tenant's catalog.
 */
class CreateService
{
    /**
     * @param  array{name: string, description: string|null, duration_minutes: int, buffer_after_minutes: int, price_cents: int, is_active: bool}  $attributes
     */
    public function handle(array $attributes): Service
    {
        $service = new Service($attributes);
        $service->position = (int) Service::withTrashed()->max('position') + 1;
        $service->save();

        return $service;
    }
}
