<?php

use App\Actions\Appointments\BookAppointment;
use App\Data\Appointments\BookingData;
use App\Enums\AppointmentStatus;
use App\Enums\BookingChannel;
use App\Exceptions\SlotNoLongerAvailable;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Availability\AvailabilityCalculator;
use App\Services\Availability\Slot;
use App\Support\CurrentTenant;
use App\Support\SecureToken;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'timezone' => 'America/Sao_Paulo',
        'booking_settings' => [...Tenant::defaultBookingSettings(), 'min_notice_minutes' => 0],
    ]);
    $this->current = app(CurrentTenant::class);
    $this->current->set($this->tenant);
    $this->travelTo(CarbonImmutable::parse('2027-03-01 08:00', 'America/Sao_Paulo'));

    $this->service = Service::factory()->duration(30, buffer: 10)->create(['price_cents' => 4500]);
    $this->staff = StaffProfile::factory()->for(User::factory()->staff())->performing($this->service)->working('09:00', '12:00')->create();
});

afterEach(fn () => $this->current->forget());

function sp(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'America/Sao_Paulo');
}

function booking(Service $service, ?StaffProfile $staff, CarbonImmutable $startsAt, string $email = 'ana@example.com', BookingChannel $channel = BookingChannel::Public): BookingData
{
    return new BookingData($service, $staff, $startsAt, 'Ana Souza', $email, '+5511987654321', 'First visit', $channel);
}

test('a public booking creates a confirmed appointment with snapshots', function () {
    $booked = app(BookAppointment::class)->handle(booking($this->service, null, sp('2027-03-02 09:00')));
    $appointment = $booked->appointment->fresh();

    expect($appointment->status)->toBe(AppointmentStatus::Confirmed)
        ->and($appointment->tenant_id)->toBe($this->tenant->id)
        ->and($appointment->staff_profile_id)->toBe($this->staff->id)
        ->and($appointment->starts_at->toIso8601ZuluString())->toBe('2027-03-02T12:00:00Z')
        // ends_at includes the 10-minute buffer; the service itself ends at 09:30.
        ->and($appointment->ends_at->toIso8601ZuluString())->toBe('2027-03-02T12:40:00Z')
        ->and($appointment->serviceEndsAt()->tz('America/Sao_Paulo')->format('H:i'))->toBe('09:30')
        ->and($appointment->price_cents)->toBe(4500)
        ->and($appointment->duration_minutes)->toBe(30)
        ->and($appointment->notes)->toBe('First visit')
        ->and(SecureToken::matches($booked->manageToken, $appointment->manage_token_hash))->toBeTrue()
        ->and($appointment->customer->only(['name', 'email', 'phone']))->toBe([
            'name' => 'Ana Souza', 'email' => 'ana@example.com', 'phone' => '+5511987654321',
        ]);
});

test('later price changes do not affect existing appointments', function () {
    $booked = app(BookAppointment::class)->handle(booking($this->service, null, sp('2027-03-02 09:00')));
    $this->service->update(['price_cents' => 9900, 'duration_minutes' => 60]);

    expect($booked->appointment->fresh()->price_cents)->toBe(4500)
        ->and($booked->appointment->fresh()->duration_minutes)->toBe(30);
});

test('bookings start as pending when the business approves them', function () {
    $this->tenant->update(['booking_settings' => [...$this->tenant->booking_settings, 'requires_approval' => true]]);

    $booked = app(BookAppointment::class)->handle(booking($this->service, null, sp('2027-03-02 09:00')));

    expect($booked->appointment->status)->toBe(AppointmentStatus::Pending);
});

test('returning customers are matched by email', function () {
    $existing = Customer::factory()->create(['email' => 'ana@example.com', 'name' => 'Ana', 'phone' => null]);

    app(BookAppointment::class)->handle(booking($this->service, null, sp('2027-03-02 09:00'), 'ANA@example.com '));

    expect(Customer::count())->toBe(1)
        ->and($existing->fresh()->name)->toBe('Ana')
        ->and($existing->fresh()->phone)->toBe('+5511987654321');
});

test('a time that is not offered cannot be booked', function (string $time) {
    app(BookAppointment::class)->handle(booking($this->service, null, sp($time)));
})->throws(SlotNoLongerAvailable::class)->with([
    'outside working hours' => '2027-03-02 13:00',
    'off the slot grid' => '2027-03-02 09:05',
    'in the past' => '2027-02-27 09:00',
]);

test('a booked time is no longer offered', function () {
    app(BookAppointment::class)->handle(booking($this->service, null, sp('2027-03-02 09:00')));
    app(BookAppointment::class)->handle(booking($this->service, null, sp('2027-03-02 09:15'), 'bob@example.com'));
})->throws(SlotNoLongerAvailable::class);

describe('any professional', function () {
    beforeEach(function () {
        $this->other = StaffProfile::factory()->for(User::factory()->staff())->performing($this->service)->working('09:00', '12:00')->create();
    });

    test('the professional with fewest appointments that day is assigned', function () {
        Appointment::factory()->for($this->staff)->at(sp('2027-03-02 11:00'), 40)->create();

        $booked = app(BookAppointment::class)->handle(booking($this->service, null, sp('2027-03-02 09:00')));

        expect($booked->appointment->staff_profile_id)->toBe($this->other->id);
    });

    test('ties go to the lowest id', function () {
        $booked = app(BookAppointment::class)->handle(booking($this->service, null, sp('2027-03-02 09:00')));

        expect($booked->appointment->staff_profile_id)->toBe(min($this->staff->id, $this->other->id));
    });

    test('appointments on other days do not count', function () {
        Appointment::factory()->for($this->staff)->at(sp('2027-03-03 09:00'))->create();
        Appointment::factory()->for($this->staff)->at(sp('2027-03-03 10:00'))->create();
        Appointment::factory()->for($this->other)->at(sp('2027-03-02 11:00'))->create();

        $booked = app(BookAppointment::class)->handle(booking($this->service, null, sp('2027-03-02 09:00')));

        expect($booked->appointment->staff_profile_id)->toBe($this->staff->id);
    });
});

describe('concurrency', function () {
    test('of two concurrent bookings for the same time, only one succeeds', function () {
        // Both customers loaded the page and saw 09:00 free. The second
        // request passes the availability check with that stale view, as if
        // it ran before the first one committed: the database decides.
        $staleView = collect([new Slot(sp('2027-03-02 09:00')->utc(), sp('2027-03-02 09:40')->utc(), [$this->staff->id], '09:00')]);
        $calculator = Mockery::mock(AvailabilityCalculator::class);
        $calculator->shouldReceive('slotsFor')->andReturn($staleView);

        $first = app(BookAppointment::class)->handle(booking($this->service, null, sp('2027-03-02 09:00')));

        $second = new BookAppointment($calculator, $this->current);

        expect(fn () => $second->handle(booking($this->service, null, sp('2027-03-02 09:00'), 'bob@example.com')))
            ->toThrow(SlotNoLongerAvailable::class);

        expect(Appointment::count())->toBe(1)
            ->and(Appointment::sole()->id)->toBe($first->appointment->id)
            // The failed booking was rolled back entirely.
            ->and(Customer::where('email', 'bob@example.com')->exists())->toBeFalse();
    });

    test('the exclusion constraint rejects overlapping active appointments', function () {
        Appointment::factory()->for($this->staff)->at(sp('2027-03-02 09:00'), 60)->create();

        try {
            DB::transaction(fn () => Appointment::factory()->for($this->staff)->at(sp('2027-03-02 09:30'), 60)->create());
            $this->fail('The overlapping appointment was saved.');
        } catch (QueryException $exception) {
            expect(BookAppointment::isOverlap($exception))->toBeTrue();
        }
    });

    test('the exclusion constraint allows back-to-back, other professionals and inactive overlaps', function () {
        $other = StaffProfile::factory()->for(User::factory()->staff())->create();
        Appointment::factory()->for($this->staff)->at(sp('2027-03-02 09:00'), 60)->create();

        Appointment::factory()->for($this->staff)->at(sp('2027-03-02 10:00'), 60)->create();
        Appointment::factory()->for($other)->at(sp('2027-03-02 09:00'), 60)->create();
        Appointment::factory()->for($this->staff)->at(sp('2027-03-02 09:30'), 60)->status(AppointmentStatus::Cancelled)->create();

        expect(Appointment::count())->toBe(4);
    });
});

describe('panel bookings', function () {
    test('the team can book outside the offered slots, but never on top of another appointment', function () {
        $booked = app(BookAppointment::class)->handle(booking($this->service, $this->staff, sp('2027-03-02 18:05'), channel: BookingChannel::Panel));

        expect($booked->appointment->status)->toBe(AppointmentStatus::Confirmed);

        app(BookAppointment::class)->handle(booking($this->service, $this->staff, sp('2027-03-02 18:20'), 'bob@example.com', BookingChannel::Panel));
    })->throws(SlotNoLongerAvailable::class);

    test('panel bookings need a professional', function () {
        app(BookAppointment::class)->handle(booking($this->service, null, sp('2027-03-02 09:00'), channel: BookingChannel::Panel));
    })->throws(InvalidArgumentException::class);
});
