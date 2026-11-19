<?php

namespace App\Actions\Services;

use App\Models\Service;
use Illuminate\Support\Facades\DB;

/**
 * Persists the drag-and-drop order of the catalog.
 */
class ReorderServices
{
    /**
     * @param  list<int>  $orderedIds  every service id of the tenant, in the new order
     */
    public function handle(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $position => $id) {
                // The tenant scope keeps ids of other tenants out of reach.
                Service::query()->whereKey($id)->update(['position' => $position + 1]);
            }
        });
    }
}
