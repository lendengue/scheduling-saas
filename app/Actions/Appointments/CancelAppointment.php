<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentStatus;
use App\Exceptions\AppointmentCannotBeChanged;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;

/**
 * Cancels an active appointment, freeing its time.
 */
class CancelAppointment
{
    /**
     * @param  bool  $byCustomer  customers must respect the cancellation notice
     */
    public function handle(Appointment $appointment, ?string $reason, bool $byCustomer): Appointment
    {
        return DB::transaction(function () use ($appointment, $reason, $byCustomer): Appointment {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);

            if ($byCustomer) {
                CustomerChangeWindow::ensureOpen($appointment);
            } elseif (! $appointment->status->canTransitionTo(AppointmentStatus::Cancelled)) {
                throw new AppointmentCannotBeChanged('This appointment can no longer be cancelled.');
            }

            $appointment->forceFill([
                'status' => AppointmentStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ])->save();

            return $appointment;
        });
    }
}
