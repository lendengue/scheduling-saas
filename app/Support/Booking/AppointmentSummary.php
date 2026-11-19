<?php

namespace App\Support\Booking;

use App\Models\Appointment;
use App\Models\Tenant;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * An appointment as shown to its customer, in the business's timezone.
 */
final class AppointmentSummary
{
    /**
     * @return array{id: string, service: string, staff: string, date: string, time: string, ends_at_time: string, duration_minutes: int, price: string, status: string, status_label: string, starts_at: string}
     */
    public static function from(Appointment $appointment, Tenant $tenant): array
    {
        $appointment->loadMissing(['service', 'staffProfile.user']);
        /** @var CarbonImmutable $local */
        $local = $appointment->starts_at->tz($tenant->timezone)->locale($tenant->locale);

        return [
            'id' => $appointment->id,
            'service' => $appointment->service->name,
            'staff' => $appointment->staffProfile->user->name,
            'date' => $local->isoFormat('dddd, LL'),
            'time' => $local->format('H:i'),
            'ends_at_time' => $appointment->serviceEndsAt()->tz($tenant->timezone)->format('H:i'),
            'duration_minutes' => $appointment->duration_minutes,
            'price' => Money::format($appointment->price_cents, $tenant),
            'status' => $appointment->status->value,
            'status_label' => $appointment->status->label(),
            'starts_at' => $appointment->starts_at->toIso8601ZuluString(),
        ];
    }
}
