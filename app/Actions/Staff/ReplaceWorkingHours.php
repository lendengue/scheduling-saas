<?php

namespace App\Actions\Staff;

use App\Models\StaffProfile;
use App\Services\Availability\AvailabilityCache;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a professional's weekly schedule.
 */
class ReplaceWorkingHours
{
    public function __construct(private readonly AvailabilityCache $cache) {}

    /**
     * @param  list<array{weekday: int, starts_at: string, ends_at: string}>  $intervals
     */
    public function handle(StaffProfile $profile, array $intervals): void
    {
        DB::transaction(function () use ($profile, $intervals): void {
            $profile->workingHours()->delete();
            $profile->workingHours()->createMany($intervals);
        });

        $this->cache->flush($profile->tenant_id);
    }
}
