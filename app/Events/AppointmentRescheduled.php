<?php

namespace App\Events;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class AppointmentRescheduled implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly CarbonImmutable $previousStartsAt,
        public readonly string $manageToken,
    ) {}
}
