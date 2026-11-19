<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\UsesTimestampTz;
use App\Observers\FlushAvailabilityCache;
use Carbon\CarbonImmutable;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A booking of a service with a professional.
 *
 * starts_at/ends_at are UTC; ends_at includes the service buffer (the end of
 * the time blocked in the agenda). The customer-facing end is
 * starts_at + duration_minutes.
 *
 * @property string $id
 * @property string $tenant_id
 * @property int $staff_profile_id
 * @property int $service_id
 * @property int $customer_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property AppointmentStatus $status
 * @property int $duration_minutes
 * @property int $price_cents
 * @property string|null $notes
 * @property string $manage_token_hash
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property CarbonImmutable|null $reminder_sent_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Tenant $tenant
 * @property-read StaffProfile $staffProfile
 * @property-read Service $service
 * @property-read Customer $customer
 */
#[ObservedBy(FlushAvailabilityCache::class)]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use BelongsToTenant, HasFactory, HasUlids, UsesTimestampTz;

    /**
     * Appointments are written only by the appointment actions, which set
     * every attribute explicitly.
     *
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @var list<string>
     */
    protected $hidden = ['manage_token_hash'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'status' => AppointmentStatus::class,
            'duration_minutes' => 'integer',
            'price_cents' => 'integer',
            'cancelled_at' => 'immutable_datetime',
            'reminder_sent_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<StaffProfile, $this>
     */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    /**
     * Soft-deleted services still show on past appointments.
     *
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * End of the service itself, without the buffer.
     */
    public function serviceEndsAt(): CarbonImmutable
    {
        return $this->starts_at->addMinutes($this->duration_minutes);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function blocking(Builder $query): void
    {
        $query->whereIn('status', AppointmentStatus::blockingValues());
    }

    /**
     * Appointments that overlap the half-open interval [from, to).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function overlapping(Builder $query, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $query->where('starts_at', '<', $to->utc())->where('ends_at', '>', $from->utc());
    }
}
