<?php

namespace App\Actions\Appointments;

use App\Exceptions\AppointmentCannotBeChanged;
use App\Models\Appointment;
use Carbon\CarbonImmutable;

/**
 * Customers may cancel or reschedule an active appointment online until
 * cancellation_notice_hours (default 24) before it starts.
 */
final class CustomerChangeWindow
{
    public static function deadline(Appointment $appointment): CarbonImmutable
    {
        return $appointment->starts_at->subHours($appointment->tenant->bookingSettings()->cancellationNoticeHours);
    }

    public static function isOpen(Appointment $appointment): bool
    {
        return $appointment->status->isBlocking() && CarbonImmutable::now()->lt(self::deadline($appointment));
    }

    public static function ensureOpen(Appointment $appointment): void
    {
        if (! $appointment->status->isBlocking()) {
            throw new AppointmentCannotBeChanged('This appointment can no longer be changed.');
        }

        if (! self::isOpen($appointment)) {
            $hours = $appointment->tenant->bookingSettings()->cancellationNoticeHours;

            throw new AppointmentCannotBeChanged("Appointments can only be changed online up to {$hours} hours before they start. Please contact {$appointment->tenant->name}.");
        }
    }
}
