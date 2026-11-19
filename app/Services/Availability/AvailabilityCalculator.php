<?php

namespace App\Services\Availability;

use App\Data\Tenants\BookingSettings;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\TimeOff;
use App\Models\WorkingHour;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Computes the start times a service can be booked at on a given day.
 *
 * 1. The day is taken in the tenant's timezone (a DST day lasts 23 or 25 h).
 * 2. For each eligible professional (bookable, performs the service, and the
 *    chosen one if any), the working hours of that weekday are turned into
 *    UTC windows, minus overlapping time off and active appointments (whose
 *    ends_at already includes the buffer).
 * 3. Candidates are generated every slot_interval minutes from the start of
 *    each working window; a slot needs duration + buffer to fit entirely in
 *    a free interval.
 * 4. booking_settings apply: min_notice_minutes and max_advance_days.
 * 5. Without a chosen professional, the slots of everyone are merged; the
 *    professional is assigned at booking time (see BookAppointment).
 * 6. Slots are returned in UTC with a label in the tenant's timezone.
 */
class AvailabilityCalculator
{
    public function __construct(
        private readonly CurrentTenant $currentTenant,
        private readonly AvailabilityCache $cache,
    ) {}

    /**
     * @param  CarbonImmutable  $date  the calendar day, as seen by the tenant: only its Y-m-d is used
     * @param  string|null  $ignoringAppointmentId  an appointment being rescheduled, whose time counts as free
     * @return Collection<int, Slot>
     */
    public function slotsFor(Service $service, ?StaffProfile $staff, CarbonImmutable $date, ?string $ignoringAppointmentId = null): Collection
    {
        $tenant = $this->currentTenant->getOrFail();
        $day = $date->format('Y-m-d');

        if ($ignoringAppointmentId !== null) {
            return collect($this->calculate($tenant, $service, $staff, $day, $ignoringAppointmentId));
        }

        $cached = $this->cache->remember(
            $tenant->id,
            [$service->id, $staff->id ?? 'any', $day],
            fn () => array_map(fn (Slot $slot) => $slot->toArray(), $this->calculate($tenant, $service, $staff, $day)),
        );

        /** @var list<array{starts_at: string, ends_at: string, staff_profile_ids: list<int>, label: string}> $cached */
        return collect($cached)
            // The cache may be up to a minute old: re-apply the minimum notice.
            ->filter(fn (array $slot) => CarbonImmutable::parse($slot['starts_at'])->gte($this->earliestStart($tenant->bookingSettings())))
            ->map(fn (array $slot) => Slot::fromArray($slot))
            ->values();
    }

    /**
     * Days of the given month that have at least one slot, for the booking
     * calendar.
     *
     * @return list<string> dates as Y-m-d
     */
    public function availableDaysInMonth(Service $service, ?StaffProfile $staff, int $year, int $month): array
    {
        $timezone = $this->currentTenant->getOrFail()->timezone;
        $day = CarbonImmutable::createFromFormat('!Y-n-j', "{$year}-{$month}-1", $timezone)
            ?? throw new InvalidArgumentException('Invalid month.');
        $days = [];

        for (; $day->month === $month; $day = $day->addDay()) {
            if ($this->slotsFor($service, $staff, $day)->isNotEmpty()) {
                $days[] = $day->format('Y-m-d');
            }
        }

        return $days;
    }

    /**
     * @return list<Slot>
     */
    private function calculate(Tenant $tenant, Service $service, ?StaffProfile $staff, string $day, ?string $ignoringAppointmentId = null): array
    {
        $timezone = $tenant->timezone;
        $settings = $tenant->bookingSettings();

        $dayStart = CarbonImmutable::createFromFormat('!Y-m-d', $day, $timezone);

        if ($dayStart === null || ! $service->is_active || ! $this->isWithinBookingWindow($dayStart, $settings)) {
            return [];
        }

        $dayEnd = $dayStart->addDay();

        $profiles = StaffProfile::query()
            ->bookable()
            ->whereHas('services', fn ($query) => $query->whereKey($service->id))
            ->when($staff !== null, fn ($query) => $query->whereKey($staff?->id))
            ->with(['workingHours' => fn ($query) => $query->where('weekday', $dayStart->dayOfWeek)])
            ->get();

        if ($profiles->isEmpty()) {
            return [];
        }

        $busy = $this->busyIntervals($profiles->map(fn (StaffProfile $profile) => $profile->id)->all(), $dayStart->utc(), $dayEnd->utc(), $ignoringAppointmentId);
        $earliest = $this->earliestStart($settings);
        $blockedMinutes = $service->blockedMinutes();
        $step = $settings->slotInterval;

        /** @var array<int, list<int>> $staffByStart unix timestamp => professional ids */
        $staffByStart = [];

        foreach ($profiles as $profile) {
            foreach ($profile->workingHours as $hours) {
                [$windowStart, $windowEnd] = $this->window($hours, $day, $timezone);

                foreach ($this->subtract($windowStart, $windowEnd, $busy[$profile->id] ?? []) as [$freeStart, $freeEnd]) {
                    // Keep the grid aligned to the start of the working window.
                    $offsetMinutes = (int) (ceil($windowStart->diffInSeconds($freeStart) / ($step * 60)) * $step);

                    for ($start = $windowStart->addMinutes($offsetMinutes); $start->addMinutes($blockedMinutes)->lte($freeEnd); $start = $start->addMinutes($step)) {
                        if ($start->gte($earliest)) {
                            $staffByStart[$start->getTimestamp()][] = $profile->id;
                        }
                    }
                }
            }
        }

        ksort($staffByStart);

        $slots = [];

        foreach ($staffByStart as $timestamp => $staffIds) {
            $startsAt = CarbonImmutable::createFromTimestampUTC($timestamp);
            sort($staffIds);

            $slots[] = new Slot(
                $startsAt,
                $startsAt->addMinutes($blockedMinutes),
                array_values(array_unique($staffIds)),
                $startsAt->tz($timezone)->format('H:i'),
            );
        }

        return $slots;
    }

    private function isWithinBookingWindow(CarbonImmutable $dayStart, BookingSettings $settings): bool
    {
        $today = CarbonImmutable::now($dayStart->getTimezone())->startOfDay();

        return $dayStart->gte($today) && $dayStart->lte($today->addDays($settings->maxAdvanceDays));
    }

    private function earliestStart(BookingSettings $settings): CarbonImmutable
    {
        return CarbonImmutable::now('UTC')->addMinutes($settings->minNoticeMinutes);
    }

    /**
     * A working interval on the given day, from wall-clock time to UTC.
     * Wall-clock times skipped by a DST change move forward, like PHP does.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function window(WorkingHour $hours, string $day, string $timezone): array
    {
        return [
            CarbonImmutable::parse("{$day} {$hours->starts_at}", $timezone)->utc(),
            CarbonImmutable::parse("{$day} {$hours->ends_at}", $timezone)->utc(),
        ];
    }

    /**
     * Time off and active appointments per professional that touch the day.
     *
     * @param  array<int, int>  $staffIds
     * @return array<int, list<array{CarbonImmutable, CarbonImmutable}>>
     */
    private function busyIntervals(array $staffIds, CarbonImmutable $from, CarbonImmutable $to, ?string $ignoringAppointmentId): array
    {
        $busy = [];

        $timeOffs = TimeOff::query()
            ->whereIn('staff_profile_id', $staffIds)
            ->overlapping($from, $to)
            ->get(['staff_profile_id', 'starts_at', 'ends_at']);

        foreach ($timeOffs as $timeOff) {
            $busy[$timeOff->staff_profile_id][] = [$timeOff->starts_at, $timeOff->ends_at];
        }

        $appointments = Appointment::query()
            ->whereIn('staff_profile_id', $staffIds)
            ->blocking()
            ->overlapping($from, $to)
            ->when($ignoringAppointmentId, fn ($query) => $query->whereKeyNot($ignoringAppointmentId))
            ->get(['staff_profile_id', 'starts_at', 'ends_at']);

        foreach ($appointments as $appointment) {
            $busy[$appointment->staff_profile_id][] = [$appointment->starts_at, $appointment->ends_at];
        }

        return $busy;
    }

    /**
     * The parts of [start, end) not covered by any busy interval.
     *
     * @param  list<array{CarbonImmutable, CarbonImmutable}>  $busy
     * @return list<array{CarbonImmutable, CarbonImmutable}>
     */
    private function subtract(CarbonImmutable $start, CarbonImmutable $end, array $busy): array
    {
        usort($busy, fn (array $a, array $b) => $a[0] <=> $b[0]);

        $free = [];
        $cursor = $start;

        foreach ($busy as [$busyStart, $busyEnd]) {
            if ($busyEnd->lte($cursor) || $busyStart->gte($end)) {
                continue;
            }

            if ($busyStart->gt($cursor)) {
                $free[] = [$cursor, $busyStart];
            }

            if ($busyEnd->gt($cursor)) {
                $cursor = $busyEnd;
            }
        }

        if ($cursor->lt($end)) {
            $free[] = [$cursor, $end];
        }

        return $free;
    }
}
