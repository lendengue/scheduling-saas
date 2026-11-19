<?php

namespace App\Actions\Appointments;

use App\Data\Appointments\BookedAppointment;
use App\Data\Appointments\BookingData;
use App\Enums\AppointmentStatus;
use App\Enums\BookingChannel;
use App\Events\AppointmentBooked;
use App\Exceptions\SlotNoLongerAvailable;
use App\Models\Appointment;
use App\Models\Customer;
use App\Services\Availability\AvailabilityCalculator;
use App\Support\CurrentTenant;
use App\Support\SecureToken;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Books an appointment for the current tenant.
 *
 * Public bookings must match a slot offered by the availability calculator;
 * with "any professional", the least busy one that day is assigned. The
 * insert runs in a transaction and the database exclusion constraint has the
 * final word: a concurrent booking of the same time raises
 * SlotNoLongerAvailable instead of a double booking.
 */
class BookAppointment
{
    /**
     * PostgreSQL exclusion_violation.
     */
    public const string EXCLUSION_VIOLATION = '23P01';

    public function __construct(
        private readonly AvailabilityCalculator $calculator,
        private readonly CurrentTenant $currentTenant,
    ) {}

    public function handle(BookingData $data): BookedAppointment
    {
        $tenant = $this->currentTenant->getOrFail();
        $startsAt = $data->startsAt->utc();
        $staffId = $this->resolveStaff($data, $startsAt);
        $status = $data->channel === BookingChannel::Public && $tenant->bookingSettings()->requiresApproval
            ? AppointmentStatus::Pending
            : AppointmentStatus::Confirmed;
        $token = SecureToken::generate();

        try {
            $appointment = DB::transaction(function () use ($data, $startsAt, $staffId, $status, $token): Appointment {
                $appointment = new Appointment;
                $appointment->forceFill([
                    'staff_profile_id' => $staffId,
                    'service_id' => $data->service->id,
                    'customer_id' => $this->findOrCreateCustomer($data)->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $startsAt->addMinutes($data->service->blockedMinutes()),
                    'status' => $status,
                    'duration_minutes' => $data->service->duration_minutes,
                    'price_cents' => $data->service->price_cents,
                    'notes' => $data->notes,
                    'manage_token_hash' => $token->hash,
                ])->save();

                return $appointment;
            });
        } catch (QueryException $exception) {
            throw self::isOverlap($exception) ? new SlotNoLongerAvailable($exception) : $exception;
        }

        AppointmentBooked::dispatch($appointment, $token->plain);

        return new BookedAppointment($appointment, $token->plain);
    }

    public static function isOverlap(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? $exception->getCode()) === self::EXCLUSION_VIOLATION;
    }

    private function resolveStaff(BookingData $data, CarbonImmutable $startsAt): int
    {
        if ($data->channel === BookingChannel::Panel) {
            return $data->staff->id ?? throw new InvalidArgumentException('Bookings from the panel need a professional.');
        }

        $timezone = $this->currentTenant->getOrFail()->timezone;
        $slot = $this->calculator
            ->slotsFor($data->service, $data->staff, $startsAt->tz($timezone))
            ->first(fn ($slot) => $slot->startsAt->equalTo($startsAt));

        if ($slot === null) {
            throw new SlotNoLongerAvailable;
        }

        return count($slot->staffProfileIds) === 1
            ? $slot->staffProfileIds[0]
            : $this->leastBusy($slot->staffProfileIds, $startsAt->tz($timezone));
    }

    /**
     * The professional with the fewest active appointments on that day
     * (in the tenant's timezone); ties go to the lowest id.
     *
     * @param  list<int>  $staffIds
     */
    private function leastBusy(array $staffIds, CarbonImmutable $localStart): int
    {
        $dayStart = $localStart->startOfDay();

        $counts = Appointment::query()
            ->whereIn('staff_profile_id', $staffIds)
            ->blocking()
            ->where('starts_at', '>=', $dayStart->utc())
            ->where('starts_at', '<', $dayStart->addDay()->utc())
            ->groupBy('staff_profile_id')
            ->selectRaw('staff_profile_id, count(*) as total')
            ->pluck('total', 'staff_profile_id');

        usort($staffIds, fn (int $a, int $b) => [(int) ($counts[$a] ?? 0), $a] <=> [(int) ($counts[$b] ?? 0), $b]);

        return $staffIds[0];
    }

    /**
     * Customers are matched by email within the tenant. A returning customer
     * keeps their record; a missing phone is filled in.
     */
    private function findOrCreateCustomer(BookingData $data): Customer
    {
        $email = Str::lower(trim($data->customerEmail));
        $customer = Customer::query()->where('email', $email)->first();

        if ($customer === null) {
            return Customer::create([
                'name' => $data->customerName,
                'email' => $email,
                'phone' => $data->customerPhone,
            ]);
        }

        if ($customer->phone === null && $data->customerPhone !== null) {
            $customer->update(['phone' => $data->customerPhone]);
        }

        return $customer;
    }
}
