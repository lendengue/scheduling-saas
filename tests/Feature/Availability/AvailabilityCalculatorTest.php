<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\TimeOff;
use App\Models\User;
use App\Services\Availability\AvailabilityCalculator;
use App\Services\Availability\Slot;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;

/*
| The calculator works on wall-clock days in the tenant's timezone. These
| tests use America/New_York, which observes DST, with minimum notice
| disabled unless a test says otherwise.
*/

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'timezone' => 'America/New_York',
        'booking_settings' => [...Tenant::defaultBookingSettings(), 'min_notice_minutes' => 0],
    ]);
    $this->current = app(CurrentTenant::class);
    $this->current->set($this->tenant);

    // Monday 1 March 2027, 08:00 in New York.
    $this->travelTo(CarbonImmutable::parse('2027-03-01 08:00', 'America/New_York'));

    $this->service = Service::factory()->duration(30)->create();
    $this->calculator = app(AvailabilityCalculator::class);
});

afterEach(fn () => $this->current->forget());

/**
 * A bookable professional performing the service on the given hours.
 *
 * @param  list<int>  $weekdays
 */
function professional(Service $service, string $from = '09:00', string $to = '12:00', array $weekdays = [0, 1, 2, 3, 4, 5, 6]): StaffProfile
{
    return StaffProfile::factory()
        ->for(User::factory()->staff())
        ->performing($service)
        ->working($from, $to, $weekdays)
        ->create();
}

/**
 * @param  iterable<Slot>  $slots
 * @return list<string>
 */
function labels(iterable $slots): array
{
    return collect($slots)->map(fn (Slot $slot) => $slot->label)->values()->all();
}

function ny(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'America/New_York');
}

test('slots are generated every slot interval within working hours', function () {
    professional($this->service, '09:00', '10:30');

    $slots = $this->calculator->slotsFor($this->service, null, ny('2027-03-02'));

    expect(labels($slots))->toBe(['09:00', '09:15', '09:30', '09:45', '10:00'])
        ->and($slots->first()->startsAt->toIso8601ZuluString())->toBe('2027-03-02T14:00:00Z')
        ->and($slots->first()->endsAt->toIso8601ZuluString())->toBe('2027-03-02T14:30:00Z');
});

test('the slot interval comes from the booking settings', function () {
    $this->tenant->update(['booking_settings' => [...$this->tenant->booking_settings, 'slot_interval' => 30]]);
    professional($this->service, '09:00', '11:00');

    expect(labels($this->calculator->slotsFor($this->service, null, ny('2027-03-02'))))
        ->toBe(['09:00', '09:30', '10:00', '10:30']);
});

test('several intervals in a day (lunch break) are respected', function () {
    $profile = professional($this->service, '09:00', '10:00');
    $profile->workingHours()->create(['weekday' => 2, 'starts_at' => '13:00', 'ends_at' => '14:00']);

    expect(labels($this->calculator->slotsFor($this->service, null, ny('2027-03-02'))))
        ->toBe(['09:00', '09:15', '09:30', '13:00', '13:15', '13:30']);
});

describe('daylight saving time', function () {
    test('on the spring-forward day the skipped hour offers no slots', function () {
        // 14 March 2027: clocks jump from 02:00 to 03:00 in New York.
        $service = Service::factory()->duration(60)->create();
        professional($service, '00:00', '05:00', [0]);

        $slots = $this->calculator->slotsFor($service, null, ny('2027-03-14'));

        // 00:00-05:00 lasts only 4 real hours on that day.
        expect($slots->first()->label)->toBe('00:00')
            ->and($slots->last()->label)->toBe('04:00')
            ->and(labels($slots))->not->toContain('02:00', '02:15', '02:30', '02:45')
            ->and($slots)->toHaveCount(13);
    });

    test('UTC offsets change across the spring-forward weekend', function () {
        professional($this->service, '09:00', '09:30');

        $saturday = $this->calculator->slotsFor($this->service, null, ny('2027-03-13'))->first();
        $sunday = $this->calculator->slotsFor($this->service, null, ny('2027-03-14'))->first();

        expect($saturday->label)->toBe('09:00')
            ->and($saturday->startsAt->toIso8601ZuluString())->toBe('2027-03-13T14:00:00Z')
            ->and($sunday->label)->toBe('09:00')
            ->and($sunday->startsAt->toIso8601ZuluString())->toBe('2027-03-14T13:00:00Z');
    });

    test('on the fall-back day the repeated hour is offered twice', function () {
        // 7 November 2027: clocks go back from 02:00 to 01:00.
        $this->travelTo(ny('2027-11-01 08:00'));
        $service = Service::factory()->duration(60)->create();
        professional($service, '00:00', '03:00', [0]);

        $slots = $this->calculator->slotsFor($service, null, ny('2027-11-07'));

        // 3 wall-clock hours last 4 real hours: 13 starts every 15 minutes.
        expect($slots)->toHaveCount(13)
            ->and(collect(labels($slots))->filter(fn ($label) => $label === '01:00'))->toHaveCount(2)
            ->and($slots->last()->label)->toBe('02:00')
            ->and($slots->last()->endsAt->tz('America/New_York')->format('H:i'))->toBe('03:00');
    });
});

test('a service longer than the working day has no slots', function () {
    $long = Service::factory()->duration(240)->create();
    professional($long, '09:00', '12:00');

    expect($this->calculator->slotsFor($long, null, ny('2027-03-02')))->toBeEmpty();
});

test('the buffer must fit before the end of the working day', function () {
    $withBuffer = Service::factory()->duration(30, buffer: 15)->create();
    professional($withBuffer, '09:00', '10:00');

    $slots = $this->calculator->slotsFor($withBuffer, null, ny('2027-03-02'));

    // 09:15 + 30 min + 15 min buffer ends exactly at 10:00.
    expect(labels($slots))->toBe(['09:00', '09:15'])
        ->and($slots->last()->endsAt->tz('America/New_York')->format('H:i'))->toBe('10:00');
});

test('partial time off in the middle of the day is removed', function () {
    $profile = professional($this->service, '09:00', '12:00');
    TimeOff::factory()->for($profile)->create([
        'starts_at' => ny('2027-03-02 10:00'),
        'ends_at' => ny('2027-03-02 11:00'),
    ]);

    expect(labels($this->calculator->slotsFor($this->service, null, ny('2027-03-02'))))
        ->toBe(['09:00', '09:15', '09:30', '11:00', '11:15', '11:30']);
});

test('time off spanning several days blocks them entirely', function () {
    $profile = professional($this->service);
    TimeOff::factory()->for($profile)->create([
        'starts_at' => ny('2027-03-01 18:00'),
        'ends_at' => ny('2027-03-04 00:00'),
    ]);

    expect($this->calculator->slotsFor($this->service, null, ny('2027-03-02')))->toBeEmpty()
        ->and($this->calculator->slotsFor($this->service, null, ny('2027-03-03')))->toBeEmpty()
        ->and($this->calculator->slotsFor($this->service, null, ny('2027-03-04')))->not->toBeEmpty();
});

test('a day without working hours has no slots', function () {
    professional($this->service, '09:00', '12:00', [1, 2, 3, 4, 5]);

    // Saturday 6 March 2027.
    expect($this->calculator->slotsFor($this->service, null, ny('2027-03-06')))->toBeEmpty();
});

test('dates in the past have no slots', function () {
    professional($this->service);

    expect($this->calculator->slotsFor($this->service, null, ny('2027-02-28')))->toBeEmpty();
});

test('today only offers slots after the minimum notice', function () {
    $this->tenant->update(['booking_settings' => [...$this->tenant->booking_settings, 'min_notice_minutes' => 120]]);
    $this->travelTo(ny('2027-03-02 09:10'));
    professional($this->service, '09:00', '12:00');

    // Now + 2 h = 11:10, so the first start on the 15-minute grid is 11:15.
    expect(labels($this->calculator->slotsFor($this->service, null, ny('2027-03-02'))))
        ->toBe(['11:15', '11:30']);
});

test('days beyond the maximum advance have no slots', function () {
    $this->tenant->update(['booking_settings' => [...$this->tenant->booking_settings, 'max_advance_days' => 10]]);
    professional($this->service);

    expect($this->calculator->slotsFor($this->service, null, ny('2027-03-11')))->not->toBeEmpty()
        ->and($this->calculator->slotsFor($this->service, null, ny('2027-03-12')))->toBeEmpty();
});

test('active appointments block their time including the buffer', function () {
    $profile = professional($this->service, '09:00', '11:00');
    Appointment::factory()->for($profile)->at(ny('2027-03-02 09:30'), blockedMinutes: 40)->create();

    // Busy 09:30-10:10: the next start on the grid is 10:15.
    expect(labels($this->calculator->slotsFor($this->service, null, ny('2027-03-02'))))
        ->toBe(['09:00', '10:15', '10:30']);
});

test('cancelled and finished appointments free their time', function (AppointmentStatus $status) {
    $profile = professional($this->service, '09:00', '10:00');
    Appointment::factory()->for($profile)->at(ny('2027-03-02 09:00'), 60)->status($status)->create();

    expect($this->calculator->slotsFor($this->service, null, ny('2027-03-02')))->toHaveCount(3);
})->with([AppointmentStatus::Cancelled, AppointmentStatus::NoShow, AppointmentStatus::Completed]);

test('an appointment being rescheduled does not block itself', function () {
    $profile = professional($this->service, '09:00', '10:00');
    $appointment = Appointment::factory()->for($profile)->at(ny('2027-03-02 09:00'), 60)->create();

    expect($this->calculator->slotsFor($this->service, null, ny('2027-03-02')))->toBeEmpty()
        ->and($this->calculator->slotsFor($this->service, null, ny('2027-03-02'), $appointment->id))->toHaveCount(3);
});

describe('professionals', function () {
    test('without a choice, slots of every eligible professional are merged', function () {
        $early = professional($this->service, '09:00', '10:00');
        $late = professional($this->service, '09:30', '10:30');

        $slots = $this->calculator->slotsFor($this->service, null, ny('2027-03-02'))->keyBy('label');

        expect($slots->keys()->all())->toBe(['09:00', '09:15', '09:30', '09:45', '10:00'])
            ->and($slots['09:00']->staffProfileIds)->toBe([$early->id])
            ->and($slots['09:30']->staffProfileIds)->toBe([$early->id, $late->id])
            ->and($slots['10:00']->staffProfileIds)->toBe([$late->id]);
    });

    test('a chosen professional only offers their own slots', function () {
        professional($this->service, '09:00', '10:00');
        $late = professional($this->service, '11:00', '11:30');

        expect(labels($this->calculator->slotsFor($this->service, $late, ny('2027-03-02'))))->toBe(['11:00']);
    });

    test('professionals who are not bookable or do not perform the service are ignored', function () {
        professional($this->service)->update(['is_bookable' => false]);
        professional(Service::factory()->create());

        expect($this->calculator->slotsFor($this->service, null, ny('2027-03-02')))->toBeEmpty();
    });

    test('inactive services have no slots', function () {
        professional($this->service);
        $this->service->update(['is_active' => false]);

        expect($this->calculator->slotsFor($this->service, null, ny('2027-03-02')))->toBeEmpty();
    });
});

describe('cache', function () {
    test('slots are cached and invalidated when an appointment changes', function () {
        $profile = professional($this->service, '09:00', '10:00');

        expect($this->calculator->slotsFor($this->service, null, ny('2027-03-02')))->toHaveCount(3);

        Appointment::factory()->for($profile)->at(ny('2027-03-02 09:00'), 60)->create();

        expect($this->calculator->slotsFor($this->service, null, ny('2027-03-02')))->toBeEmpty();
    });

    test('slots are invalidated when time off changes', function () {
        $profile = professional($this->service, '09:00', '10:00');
        expect($this->calculator->slotsFor($this->service, null, ny('2027-03-02')))->toHaveCount(3);

        $timeOff = TimeOff::factory()->for($profile)->create(['starts_at' => ny('2027-03-02 00:00'), 'ends_at' => ny('2027-03-03 00:00')]);
        expect($this->calculator->slotsFor($this->service, null, ny('2027-03-02')))->toBeEmpty();

        $timeOff->delete();
        expect($this->calculator->slotsFor($this->service, null, ny('2027-03-02')))->toHaveCount(3);
    });

    test('cached slots still honour the minimum notice as time passes', function () {
        $this->tenant->update(['booking_settings' => [...$this->tenant->booking_settings, 'min_notice_minutes' => 60]]);
        $this->travelTo(ny('2027-03-02 07:50'));
        professional($this->service, '09:00', '10:00');

        expect($this->calculator->slotsFor($this->service, null, ny('2027-03-02')))->toHaveCount(3);

        $this->travelTo(ny('2027-03-02 08:20'));
        expect(labels($this->calculator->slotsFor($this->service, null, ny('2027-03-02'))))->toBe(['09:30']);
    });
});

test('available days of a month are listed for the calendar', function () {
    professional($this->service, '09:00', '12:00', [2, 4]);

    expect($this->calculator->availableDaysInMonth($this->service, null, 2027, 3))
        ->toBe(['2027-03-02', '2027-03-04', '2027-03-09', '2027-03-11', '2027-03-16', '2027-03-18', '2027-03-23', '2027-03-25', '2027-03-30']);
});
