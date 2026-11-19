<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Observers\FlushAvailabilityCache;
use Database\Factories\WorkingHourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A weekly working interval in the tenant's wall-clock time ("HH:MM:SS").
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $staff_profile_id
 * @property int $weekday 0 = Sunday ... 6 = Saturday (Carbon's dayOfWeek)
 * @property string $starts_at
 * @property string $ends_at
 * @property-read StaffProfile $staffProfile
 */
#[ObservedBy(FlushAvailabilityCache::class)]
#[Fillable(['staff_profile_id', 'weekday', 'starts_at', 'ends_at'])]
class WorkingHour extends Model
{
    /** @use HasFactory<WorkingHourFactory> */
    use BelongsToTenant, HasFactory;

    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<StaffProfile, $this>
     */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }
}
