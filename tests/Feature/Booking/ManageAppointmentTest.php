<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\AppointmentFactory;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['slug' => 'barber', 'timezone' => 'America/Sao_Paulo']);
    $this->travelTo(CarbonImmutable::parse('2027-03-01 08:00', 'America/Sao_Paulo'));

    app(CurrentTenant::class)->runAs($this->tenant, function () {
        $this->service = Service::factory()->duration(30)->create(['name' => 'Haircut']);
        $this->staff = StaffProfile::factory()->for(User::factory()->staff())->performing($this->service)->working('09:00', '12:00')->create();
        // Thursday 4 March 2027, 10:00 in São Paulo: three days ahead.
        $this->appointment = Appointment::factory()->for($this->staff)->for($this->service)
            ->at(CarbonImmutable::parse('2027-03-04 10:00', 'America/Sao_Paulo'))
            ->create();
    });

    $this->token = AppointmentFactory::$lastPlainToken;
});

function manageRoute(string $name, array $parameters = []): string
{
    return tenantRoute(test()->tenant, $name, ['appointment' => test()->appointment->id, ...$parameters]);
}

test('the management link shows the appointment', function () {
    $this->get(manageRoute('appointments.manage', ['token' => $this->token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Booking/Manage')
            ->where('appointment.service', 'Haircut')
            ->where('appointment.time', '10:00')
            ->where('canChange', true)
            ->where('changeDeadline', '2027-03-03 10:00'),
        );
});

test('a wrong or missing token reveals nothing', function (string $token) {
    $this->get(manageRoute('appointments.manage', ['token' => $token]))->assertNotFound();
    $this->post(manageRoute('appointments.cancel'), ['token' => $token])->assertNotFound();
    $this->post(manageRoute('appointments.reschedule'), ['token' => $token, 'starts_at' => '2027-03-05T13:00:00Z'])->assertNotFound();

    expect($this->appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed);
})->with(['wrong' => 'not-the-token', 'missing' => '']);

test('the link does not work on another tenant', function () {
    $other = Tenant::factory()->create(['slug' => 'other']);

    $this->get(tenantRoute($other, 'appointments.manage', ['appointment' => $this->appointment->id, 'token' => $this->token]))
        ->assertNotFound();
});

describe('cancelling', function () {
    test('customers cancel within the allowed window', function () {
        $this->post(manageRoute('appointments.cancel'), ['token' => $this->token, 'reason' => 'Sick'])
            ->assertRedirect(manageRoute('appointments.manage', ['token' => $this->token]));

        $appointment = $this->appointment->fresh();
        expect($appointment->status)->toBe(AppointmentStatus::Cancelled)
            ->and($appointment->cancellation_reason)->toBe('Sick')
            ->and($appointment->cancelled_at)->not->toBeNull();
    });

    test('customers cannot cancel inside the notice window', function () {
        // 23 hours before the appointment, with a 24-hour notice.
        $this->travelTo(CarbonImmutable::parse('2027-03-03 11:00', 'America/Sao_Paulo'));

        $this->get(manageRoute('appointments.manage', ['token' => $this->token]))
            ->assertInertia(fn (Assert $page) => $page->where('canChange', false));

        $this->post(manageRoute('appointments.cancel'), ['token' => $this->token])
            ->assertSessionHasErrors('appointment');

        expect($this->appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed);
    });

    test('the notice window comes from the booking settings', function () {
        $this->tenant->update(['booking_settings' => [...$this->tenant->booking_settings, 'cancellation_notice_hours' => 2]]);
        $this->travelTo(CarbonImmutable::parse('2027-03-04 07:30', 'America/Sao_Paulo'));

        $this->post(manageRoute('appointments.cancel'), ['token' => $this->token])->assertSessionHasNoErrors();

        expect($this->appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled);
    });

    test('a cancelled appointment cannot be cancelled again', function () {
        $this->post(manageRoute('appointments.cancel'), ['token' => $this->token]);

        $this->post(manageRoute('appointments.cancel'), ['token' => $this->token])
            ->assertSessionHasErrors(['appointment' => 'This appointment can no longer be changed.']);
    });
});

describe('rescheduling', function () {
    test('customers move to another free time within the window', function () {
        $this->post(manageRoute('appointments.reschedule'), ['token' => $this->token, 'starts_at' => '2027-03-05T14:30:00Z'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $appointment = $this->appointment->fresh();
        expect($appointment->starts_at->tz('America/Sao_Paulo')->format('Y-m-d H:i'))->toBe('2027-03-05 11:30')
            ->and($appointment->ends_at->diffInMinutes($appointment->starts_at, true))->toEqual(30)
            ->and($appointment->status)->toBe(AppointmentStatus::Confirmed);
    });

    test('an overlapping move within its own time is allowed', function () {
        // 10:15 overlaps the current 10:00-10:30, which is the appointment itself.
        $this->post(manageRoute('appointments.reschedule'), ['token' => $this->token, 'starts_at' => '2027-03-04T13:15:00Z'])
            ->assertSessionHasNoErrors();

        expect($this->appointment->fresh()->starts_at->toIso8601ZuluString())->toBe('2027-03-04T13:15:00Z');
    });

    test('customers can only move to offered times', function () {
        app(CurrentTenant::class)->runAs($this->tenant, fn () => Appointment::factory()->for($this->staff)
            ->at(CarbonImmutable::parse('2027-03-05 11:30', 'America/Sao_Paulo'))->create());

        $this->post(manageRoute('appointments.reschedule'), ['token' => $this->token, 'starts_at' => '2027-03-05T14:30:00Z'])
            ->assertSessionHasErrors('starts_at');
        $this->post(manageRoute('appointments.reschedule'), ['token' => $this->token, 'starts_at' => '2027-03-05T20:00:00Z'])
            ->assertSessionHasErrors('starts_at');

        expect($this->appointment->fresh()->starts_at->toIso8601ZuluString())->toBe('2027-03-04T13:00:00Z');
    });

    test('customers cannot reschedule inside the notice window', function () {
        $this->travelTo(CarbonImmutable::parse('2027-03-04 09:00', 'America/Sao_Paulo'));

        $this->post(manageRoute('appointments.reschedule'), ['token' => $this->token, 'starts_at' => '2027-03-05T14:30:00Z'])
            ->assertSessionHasErrors('appointment');
    });

    test('availability for rescheduling counts the current time as free', function () {
        $this->getJson(manageRoute('appointments.manage.slots', ['token' => $this->token, 'date' => '2027-03-04']))
            ->assertOk()
            ->assertJsonFragment(['label' => '10:00']);

        $this->getJson(manageRoute('appointments.manage.days', ['token' => $this->token, 'month' => '2027-03']))
            ->assertOk()
            ->assertJsonFragment(['2027-03-04']);

        $this->getJson(manageRoute('appointments.manage.slots', ['token' => 'nope', 'date' => '2027-03-04']))
            ->assertNotFound();
    });
});
