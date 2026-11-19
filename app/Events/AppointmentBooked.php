<?php

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class AppointmentBooked implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  string  $manageToken  plain token for the customer's link (never stored)
     */
    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $manageToken,
    ) {}
}
