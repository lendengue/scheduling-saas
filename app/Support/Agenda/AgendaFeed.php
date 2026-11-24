<?php

namespace App\Support\Agenda;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Tenant;
use App\Models\TimeOff;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Appointments and time off of a period, shaped as FullCalendar events.
 *
 * Appointment events span the whole blocked time (service + buffer), so a
 * slot that looks free in the agenda is really free.
 */
final class AgendaFeed
{
    public function __construct(
        private readonly Tenant $tenant,
        private readonly User $user,
    ) {}

    /**
     * @param  list<int>|null  $staffProfileIds  null = every professional
     * @return list<array<string, mixed>>
     */
    public function events(CarbonImmutable $from, CarbonImmutable $to, ?array $staffProfileIds, bool $includeCancelled): array
    {
        $appointments = Appointment::query()
            ->with(['service:id,name', 'customer:id,name,email,phone', 'staffProfile:id,user_id,color', 'staffProfile.user:id,name'])
            ->overlapping($from, $to)
            ->when($staffProfileIds !== null, fn ($query) => $query->whereIn('staff_profile_id', $staffProfileIds))
            ->unless($includeCancelled, fn ($query) => $query->where('status', '!=', AppointmentStatus::Cancelled))
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Appointment $appointment) => $this->appointmentEvent($appointment));

        // Time off is drawn behind the appointments when looking at specific people.
        $timeOffs = $staffProfileIds === null ? collect() : TimeOff::query()
            ->whereIn('staff_profile_id', $staffProfileIds)
            ->where('starts_at', '<', $to->utc())
            ->where('ends_at', '>', $from->utc())
            ->get()
            ->map(fn (TimeOff $timeOff) => [
                'id' => "time-off-{$timeOff->id}",
                'start' => $timeOff->starts_at->toIso8601ZuluString(),
                'end' => $timeOff->ends_at->toIso8601ZuluString(),
                'display' => 'background',
                'title' => $timeOff->reason ?? __('Time off'),
                'extendedProps' => ['kind' => 'time_off'],
            ]);

        return array_values([...$appointments->all(), ...$timeOffs->all()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function appointmentEvent(Appointment $appointment): array
    {
        $staff = $appointment->staffProfile;
        $editable = $appointment->status->isBlocking() && $this->user->can('update', $appointment);

        return [
            'id' => $appointment->id,
            'title' => "{$appointment->customer->name} · {$appointment->service->name}",
            'start' => $appointment->starts_at->toIso8601ZuluString(),
            'end' => $appointment->ends_at->toIso8601ZuluString(),
            'color' => $staff->color,
            'editable' => $editable,
            'durationEditable' => false,
            'className' => "appointment-{$appointment->status->value}",
            'extendedProps' => [
                'kind' => 'appointment',
                'status' => $appointment->status->value,
                'status_label' => $appointment->status->label(),
                'service' => $appointment->service->name,
                'staff_profile_id' => $staff->id,
                'staff' => $staff->user->name,
                'customer' => [
                    'name' => $appointment->customer->name,
                    'email' => $appointment->customer->email,
                    'phone' => $appointment->customer->phone,
                ],
                'service_ends_at' => $appointment->serviceEndsAt()->toIso8601ZuluString(),
                'duration_minutes' => $appointment->duration_minutes,
                'price' => Money::format($appointment->price_cents, $this->tenant),
                'notes' => $appointment->notes,
                'cancellation_reason' => $appointment->cancellation_reason,
                'has_started' => $appointment->starts_at->isPast(),
                'can' => [
                    'update' => $editable,
                    'confirm' => $editable && $appointment->status->canTransitionTo(AppointmentStatus::Confirmed),
                ],
            ],
        ];
    }
}
