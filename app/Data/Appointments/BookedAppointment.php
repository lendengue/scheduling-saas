<?php

namespace App\Data\Appointments;

use App\Models\Appointment;

/**
 * A new appointment and the plain management token, which exists only here:
 * the database keeps its hash.
 */
final readonly class BookedAppointment
{
    public function __construct(
        public Appointment $appointment,
        public string $manageToken,
    ) {}
}
