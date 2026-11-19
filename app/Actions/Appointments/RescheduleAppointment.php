<?php

namespace App\Actions\Appointments;

use App\Enums\BookingChannel;
use App\Events\AppointmentRescheduled;
use App\Exceptions\AppointmentCannotBeChanged;
use App\Exceptions\SlotNoLongerAvailable;
use App\Models\Appointment;
use App\Models\StaffProfile;
use App\Services\Availability\AvailabilityCalculator;
use App\Support\CurrentTenant;
use App\Support\SecureToken;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Moves an active appointment to another time (and, from the panel, possibly
 * to another professional). The blocked duration booked originally is kept.
 *
 * Customers must pick a slot offered by the availability calculator and
 * respect the cancellation notice; the team may move it anywhere free. In
 * both cases the exclusion constraint rejects overlaps.
 */
class RescheduleAppointment
{
    public function __construct(
        private readonly AvailabilityCalculator $calculator,
        private readonly CurrentTenant $currentTenant,
    ) {}

    /**
     * @param  string|null  $manageToken  the customer's current link token; without it (panel moves) the link is rotated so the email can carry a working one
     * @return array{appointment: Appointment, previous_starts_at: CarbonImmutable}
     */
    public function handle(Appointment $appointment, CarbonImmutable $startsAt, BookingChannel $channel, ?StaffProfile $staff = null, ?string $manageToken = null): array
    {
        $token = $manageToken === null ? SecureToken::generate() : null;
        $startsAt = $startsAt->utc();
        $previous = $appointment->starts_at;
        $blockedMinutes = (int) $appointment->starts_at->diffInMinutes($appointment->ends_at);

        if (! $appointment->status->isBlocking()) {
            throw new AppointmentCannotBeChanged('This appointment can no longer be changed.');
        }

        if ($channel === BookingChannel::Public) {
            CustomerChangeWindow::ensureOpen($appointment);
            $this->ensureOffered($appointment, $startsAt);
        }

        try {
            DB::transaction(function () use ($appointment, $startsAt, $blockedMinutes, $staff, $token): void {
                $appointment->forceFill([
                    'staff_profile_id' => $staff->id ?? $appointment->staff_profile_id,
                    'starts_at' => $startsAt,
                    'ends_at' => $startsAt->addMinutes($blockedMinutes),
                    // A new time deserves a new reminder.
                    'reminder_sent_at' => null,
                    ...($token === null ? [] : ['manage_token_hash' => $token->hash]),
                ])->save();
            });
        } catch (QueryException $exception) {
            $appointment->refresh();

            throw BookAppointment::isOverlap($exception) ? new SlotNoLongerAvailable($exception) : $exception;
        }

        AppointmentRescheduled::dispatch($appointment, $previous, $manageToken ?? $token->plain ?? '');

        return ['appointment' => $appointment, 'previous_starts_at' => $previous];
    }

    private function ensureOffered(Appointment $appointment, CarbonImmutable $startsAt): void
    {
        $timezone = $this->currentTenant->getOrFail()->timezone;

        $offered = $this->calculator
            ->slotsFor($appointment->service, $appointment->staffProfile, $startsAt->tz($timezone), $appointment->id)
            ->contains(fn ($slot) => $slot->startsAt->equalTo($startsAt));

        if (! $offered) {
            throw new SlotNoLongerAvailable;
        }
    }
}
