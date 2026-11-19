<?php

namespace App\Listeners;

use App\Events\AppointmentBooked;
use App\Events\AppointmentCancelled;
use App\Events\AppointmentRescheduled;
use App\Models\Appointment;
use App\Notifications\Appointments\AppointmentBookedNotification;
use App\Notifications\Appointments\AppointmentCancelledNotification;
use App\Notifications\Appointments\AppointmentNotification;
use App\Notifications\Appointments\AppointmentRescheduledNotification;

/**
 * Emails the customer and the professional about appointment changes. The
 * notifications themselves are queued (see AppointmentNotification).
 */
class SendAppointmentNotifications
{
    public function handleBooked(AppointmentBooked $event): void
    {
        $this->notify($event->appointment, new AppointmentBookedNotification(
            $event->appointment,
            $event->appointment->manageUrl($event->manageToken),
        ));
    }

    public function handleCancelled(AppointmentCancelled $event): void
    {
        $this->notify($event->appointment, new AppointmentCancelledNotification($event->appointment, $event->byCustomer));
    }

    public function handleRescheduled(AppointmentRescheduled $event): void
    {
        $this->notify($event->appointment, new AppointmentRescheduledNotification(
            $event->appointment,
            $event->previousStartsAt,
            $event->appointment->manageUrl($event->manageToken),
        ));
    }

    private function notify(Appointment $appointment, AppointmentNotification $notification): void
    {
        $appointment->customer->notify($notification);
        $appointment->staffProfile->user->notify(clone $notification);
    }
}
