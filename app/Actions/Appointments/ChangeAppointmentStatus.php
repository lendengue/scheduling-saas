<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentStatus;
use App\Exceptions\AppointmentCannotBeChanged;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Moves an appointment along its lifecycle from the panel: confirm a pending
 * booking, or record how a confirmed one went (completed or no-show).
 * Cancelling has its own action, because it notifies the customer.
 */
class ChangeAppointmentStatus
{
    public function handle(Appointment $appointment, AppointmentStatus $status): Appointment
    {
        if (! in_array($status, [AppointmentStatus::Confirmed, AppointmentStatus::Completed, AppointmentStatus::NoShow], true)) {
            throw new InvalidArgumentException("Use the dedicated action to set the status to {$status->value}.");
        }

        return DB::transaction(function () use ($appointment, $status): Appointment {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);

            if (! $appointment->status->canTransitionTo($status)) {
                throw new AppointmentCannotBeChanged("A {$appointment->status->label()} appointment cannot be marked as {$status->label()}.");
            }

            // How it went is only known once it has started.
            if ($status !== AppointmentStatus::Confirmed && $appointment->starts_at->isAfter(CarbonImmutable::now())) {
                throw new AppointmentCannotBeChanged("An appointment can only be marked as {$status->label()} once it has started.");
            }

            $appointment->forceFill(['status' => $status])->save();

            return $appointment;
        });
    }
}
