<?php

use App\Enums\AppointmentStatus;
use App\Events\AppointmentBooked;
use App\Events\AppointmentCancelled;
use App\Events\AppointmentRescheduled;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\TimeOff;
use App\Models\User;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['slug' => 'barber', 'timezone' => 'America/Sao_Paulo']);
    // Monday 1 March 2027, 08:00 in São Paulo.
    $this->travelTo(CarbonImmutable::parse('2027-03-01 08:00', 'America/Sao_Paulo'));

    app(CurrentTenant::class)->runAs($this->tenant, function () {
        $this->owner = User::factory()->owner()->create();
        $this->admin = User::factory()->admin()->create();
        $this->service = Service::factory()->duration(30)->create(['name' => 'Haircut']);
        $this->anaUser = User::factory()->staff()->create(['name' => 'Ana']);
        $this->ana = StaffProfile::factory()->for($this->anaUser)->performing($this->service)->working('09:00', '18:00')->create();
        $this->bruno = StaffProfile::factory()->for(User::factory()->staff()->state(['name' => 'Bruno']))->performing($this->service)->working('09:00', '18:00')->create();
        $this->anaAppointment = Appointment::factory()->for($this->ana)->for($this->service)
            ->at(CarbonImmutable::parse('2027-03-02 10:00', 'America/Sao_Paulo'))->create();
        $this->brunoAppointment = Appointment::factory()->for($this->bruno)->for($this->service)
            ->at(CarbonImmutable::parse('2027-03-02 11:00', 'America/Sao_Paulo'))->create();
    });
});

afterEach(fn () => app(CurrentTenant::class)->forget());

function agendaRoute(string $name, array $parameters = []): string
{
    return tenantRoute(test()->tenant, $name, $parameters);
}

/**
 * @return list<string>
 */
function agendaEventIds(User $user, array $query = []): array
{
    return collect(test()->actingAs($user)
        ->getJson(agendaRoute('agenda.events', ['start' => '2027-03-01T00:00:00', 'end' => '2027-03-08T00:00:00', ...$query]))
        ->assertOk()
        ->json())
        ->pluck('id')
        ->all();
}

describe('viewing', function () {
    test('owners and admins see every professional', function (string $who) {
        $this->actingAs($this->{$who})
            ->get(agendaRoute('agenda'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Agenda')
                ->where('timezone', 'America/Sao_Paulo')
                ->where('can.viewAll', true)
                ->has('staff', 2)
                ->where('staff.0.name', 'Ana')
                ->where('staff.0.business_hours.0.startTime', '09:00'),
            );

        expect(agendaEventIds($this->{$who}))->toEqualCanonicalizing([$this->anaAppointment->id, $this->brunoAppointment->id]);
    })->with(['owner', 'admin']);

    test('owners and admins filter by professional', function () {
        expect(agendaEventIds($this->owner, ['staff_profile_id' => $this->bruno->id]))->toBe([$this->brunoAppointment->id]);
    });

    test('staff only see their own agenda, whatever they ask for', function () {
        $staff = $this->anaUser;

        $this->actingAs($staff)
            ->get(agendaRoute('agenda'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.viewAll', false)
                ->has('staff', 1)
                ->where('staff.0.id', $this->ana->id),
            );

        expect(agendaEventIds($staff))->toBe([$this->anaAppointment->id])
            ->and(agendaEventIds($staff, ['staff_profile_id' => $this->bruno->id]))->toBe([$this->anaAppointment->id]);
    });

    test('events carry the blocked time in UTC and the details shown in the agenda', function () {
        $event = collect($this->actingAs($this->owner)
            ->getJson(agendaRoute('agenda.events', ['start' => '2027-03-01T00:00:00', 'end' => '2027-03-08T00:00:00']))
            ->json())->firstWhere('id', $this->anaAppointment->id);

        expect($event['start'])->toBe('2027-03-02T13:00:00Z')
            ->and($event['end'])->toBe('2027-03-02T13:30:00Z')
            ->and($event['color'])->toBe($this->ana->color)
            ->and($event['editable'])->toBeTrue()
            ->and($event['extendedProps']['service'])->toBe('Haircut')
            ->and($event['extendedProps']['staff'])->toBe('Ana')
            ->and($event['extendedProps']['status'])->toBe('confirmed');
    });

    test('the period is read in the business timezone', function () {
        // 10:00 in São Paulo is 13:00 UTC: a period ending at 10:30 local still includes it.
        expect(agendaEventIds($this->owner, ['start' => '2027-03-02T10:15:00', 'end' => '2027-03-02T10:30:00']))
            ->toBe([$this->anaAppointment->id]);
    });

    test('cancelled appointments are hidden unless asked for', function () {
        $this->anaAppointment->forceFill(['status' => AppointmentStatus::Cancelled])->save();

        expect(agendaEventIds($this->owner))->toBe([$this->brunoAppointment->id])
            ->and(agendaEventIds($this->owner, ['include_cancelled' => 1]))->toHaveCount(2);
    });

    test("a professional's time off is shown behind the appointments", function () {
        app(CurrentTenant::class)->runAs($this->tenant, fn () => TimeOff::factory()->for($this->ana)->create([
            'starts_at' => CarbonImmutable::parse('2027-03-03 09:00', 'America/Sao_Paulo'),
            'ends_at' => CarbonImmutable::parse('2027-03-03 12:00', 'America/Sao_Paulo'),
        ]));

        $events = collect($this->actingAs($this->owner)
            ->getJson(agendaRoute('agenda.events', ['start' => '2027-03-01T00:00:00', 'end' => '2027-03-08T00:00:00', 'staff_profile_id' => $this->ana->id]))
            ->json());

        expect($events->where('display', 'background'))->toHaveCount(1);
    });

    test('the period must be short', function () {
        $this->actingAs($this->owner)
            ->getJson(agendaRoute('agenda.events', ['start' => '2027-01-01', 'end' => '2027-06-01']))
            ->assertUnprocessable();
    });

    test('guests are sent to the login page', function () {
        $this->get(agendaRoute('agenda'))->assertRedirect();
        $this->getJson(agendaRoute('agenda.events', ['start' => '2027-03-01', 'end' => '2027-03-08']))->assertUnauthorized();
    });
});

describe('booking from the panel', function () {
    beforeEach(fn () => Event::fake([AppointmentBooked::class]));

    function panelBooking(array $overrides = []): array
    {
        return [
            'service_id' => test()->service->id,
            'staff_profile_id' => test()->ana->id,
            // Wall-clock time of the business.
            'starts_at' => '2027-03-02T14:00',
            'name' => 'Walk-in Customer',
            'email' => 'Walk.In@Example.com',
            'phone' => '(11) 98765-4321',
            ...$overrides,
        ];
    }

    test('owners book for any professional, in the business timezone', function () {
        $this->actingAs($this->owner)
            ->post(agendaRoute('panel.appointments.store'), panelBooking(['staff_profile_id' => $this->bruno->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $appointment = Appointment::withoutGlobalScopes()->where('staff_profile_id', $this->bruno->id)->latest('starts_at')->first();
        expect($appointment->starts_at->toIso8601ZuluString())->toBe('2027-03-02T17:00:00Z')
            ->and($appointment->status)->toBe(AppointmentStatus::Confirmed)
            ->and(Customer::withoutGlobalScopes()->find($appointment->customer_id)->email)->toBe('walk.in@example.com');

        Event::assertDispatched(AppointmentBooked::class);
    });

    test('the team may book outside the hours offered online', function () {
        // 07:00 is before working hours and inside the minimum notice.
        $this->actingAs($this->owner)
            ->post(agendaRoute('panel.appointments.store'), panelBooking(['starts_at' => '2027-03-01T07:00']))
            ->assertSessionHasNoErrors();
    });

    test('an overlapping booking is rejected', function () {
        $this->actingAs($this->owner)
            ->post(agendaRoute('panel.appointments.store'), panelBooking(['starts_at' => '2027-03-02T10:15']))
            ->assertSessionHasErrors('starts_at');
    });

    test('staff book only in their own agenda', function () {
        $staff = $this->anaUser;

        $this->actingAs($staff)
            ->post(agendaRoute('panel.appointments.store'), panelBooking())
            ->assertSessionHasNoErrors();

        $this->actingAs($staff)
            ->post(agendaRoute('panel.appointments.store'), panelBooking(['staff_profile_id' => $this->bruno->id]))
            ->assertForbidden();
    });

    test('the fields are validated', function () {
        $this->actingAs($this->owner)
            ->post(agendaRoute('panel.appointments.store'), panelBooking(['email' => 'nope', 'phone' => '123', 'starts_at' => '']))
            ->assertSessionHasErrors(['email', 'phone', 'starts_at']);
    });
});

describe('changing appointments', function () {
    test('drag and drop reschedules and rotates the customer link', function () {
        Event::fake([AppointmentRescheduled::class]);
        $hash = $this->anaAppointment->manage_token_hash;

        $this->actingAs($this->owner)
            ->put(agendaRoute('panel.appointments.reschedule', ['appointment' => $this->anaAppointment->id]), ['starts_at' => '2027-03-02T15:00'])
            ->assertSessionHasNoErrors();

        $appointment = $this->anaAppointment->fresh();
        expect($appointment->starts_at->toIso8601ZuluString())->toBe('2027-03-02T18:00:00Z')
            ->and($appointment->ends_at->toIso8601ZuluString())->toBe('2027-03-02T18:30:00Z')
            ->and($appointment->manage_token_hash)->not->toBe($hash);

        Event::assertDispatched(AppointmentRescheduled::class);
    });

    test('owners move an appointment to another professional', function () {
        Event::fake([AppointmentRescheduled::class]);

        $this->actingAs($this->owner)
            ->put(agendaRoute('panel.appointments.reschedule', ['appointment' => $this->anaAppointment->id]), [
                'starts_at' => '2027-03-02T10:00',
                'staff_profile_id' => $this->bruno->id,
            ])
            ->assertSessionHasNoErrors();

        expect($this->anaAppointment->fresh()->staff_profile_id)->toBe($this->bruno->id);
    });

    test('moving onto another appointment is rejected', function () {
        $this->actingAs($this->owner)
            ->put(agendaRoute('panel.appointments.reschedule', ['appointment' => $this->anaAppointment->id]), [
                'starts_at' => '2027-03-02T11:00',
                'staff_profile_id' => $this->bruno->id,
            ])
            ->assertSessionHasErrors('starts_at');

        expect($this->anaAppointment->fresh()->staff_profile_id)->toBe($this->ana->id);
    });

    test("staff cannot touch another professional's appointments", function () {
        $staff = $this->anaUser;
        $bruno = ['appointment' => $this->brunoAppointment->id];

        $this->actingAs($staff)->put(agendaRoute('panel.appointments.reschedule', $bruno), ['starts_at' => '2027-03-02T15:00'])->assertForbidden();
        $this->actingAs($staff)->post(agendaRoute('panel.appointments.cancel', $bruno))->assertForbidden();
        $this->actingAs($staff)->put(agendaRoute('panel.appointments.status', $bruno), ['status' => 'confirmed'])->assertForbidden();

        // Nor move their own appointment into someone else's agenda.
        $this->actingAs($staff)
            ->put(agendaRoute('panel.appointments.reschedule', ['appointment' => $this->anaAppointment->id]), [
                'starts_at' => '2027-03-02T15:00',
                'staff_profile_id' => $this->bruno->id,
            ])
            ->assertForbidden();
    });

    test('the team cancels with a reason, even close to the start', function () {
        Event::fake([AppointmentCancelled::class]);
        $this->travelTo(CarbonImmutable::parse('2027-03-02 09:30', 'America/Sao_Paulo'));

        $this->actingAs($this->anaUser)
            ->post(agendaRoute('panel.appointments.cancel', ['appointment' => $this->anaAppointment->id]), ['reason' => 'Barber is sick'])
            ->assertSessionHasNoErrors();

        $appointment = $this->anaAppointment->fresh();
        expect($appointment->status)->toBe(AppointmentStatus::Cancelled)
            ->and($appointment->cancellation_reason)->toBe('Barber is sick');

        Event::assertDispatched(AppointmentCancelled::class, fn (AppointmentCancelled $event) => $event->byCustomer === false);
    });

    test('pending appointments are confirmed', function () {
        $this->anaAppointment->forceFill(['status' => AppointmentStatus::Pending])->save();

        $this->actingAs($this->admin)
            ->put(agendaRoute('panel.appointments.status', ['appointment' => $this->anaAppointment->id]), ['status' => 'confirmed'])
            ->assertSessionHasNoErrors();

        expect($this->anaAppointment->fresh()->status)->toBe(AppointmentStatus::Confirmed);
    });

    test('completed and no-show are recorded once the appointment has started', function (string $status) {
        $route = agendaRoute('panel.appointments.status', ['appointment' => $this->anaAppointment->id]);

        $this->actingAs($this->owner)->put($route, ['status' => $status])->assertSessionHasErrors('appointment');
        expect($this->anaAppointment->fresh()->status)->toBe(AppointmentStatus::Confirmed);

        $this->travelTo(CarbonImmutable::parse('2027-03-02 10:05', 'America/Sao_Paulo'));
        $this->actingAs($this->owner)->put($route, ['status' => $status])->assertSessionHasNoErrors();
        expect($this->anaAppointment->fresh()->status)->toBe(AppointmentStatus::from($status));
    })->with(['completed', 'no_show']);

    test('finished appointments cannot change any more', function () {
        $this->anaAppointment->forceFill(['status' => AppointmentStatus::Completed])->save();
        $id = ['appointment' => $this->anaAppointment->id];

        $this->actingAs($this->owner)->put(agendaRoute('panel.appointments.status', $id), ['status' => 'no_show'])->assertSessionHasErrors('appointment');
        $this->actingAs($this->owner)->post(agendaRoute('panel.appointments.cancel', $id))->assertSessionHasErrors('appointment');
        $this->actingAs($this->owner)->put(agendaRoute('panel.appointments.reschedule', $id), ['starts_at' => '2027-03-02T15:00'])->assertSessionHasErrors('appointment');
    });

    test('cancelling or rescheduling is not a status change', function () {
        $this->actingAs($this->owner)
            ->put(agendaRoute('panel.appointments.status', ['appointment' => $this->anaAppointment->id]), ['status' => 'cancelled'])
            ->assertSessionHasErrors('status');
    });
});

test('appointments of another tenant are out of reach', function () {
    $other = Tenant::factory()->create(['slug' => 'other']);
    $intruder = app(CurrentTenant::class)->runAs($other, fn () => User::factory()->owner()->create());
    $id = ['appointment' => $this->anaAppointment->id];

    $this->actingAs($intruder)->put(tenantRoute($other, 'panel.appointments.status', $id), ['status' => 'confirmed'])->assertNotFound();
    $this->actingAs($intruder)->post(tenantRoute($other, 'panel.appointments.cancel', $id))->assertNotFound();
    $this->actingAs($intruder)->put(tenantRoute($other, 'panel.appointments.reschedule', $id), ['starts_at' => '2027-03-02T15:00'])->assertNotFound();

    $events = $this->actingAs($intruder)
        ->getJson(tenantRoute($other, 'agenda.events', ['start' => '2027-03-01', 'end' => '2027-03-08']))
        ->assertOk()
        ->json();
    expect($events)->toBe([]);

    // Nor book into this tenant's professionals from the other tenant.
    $this->actingAs($intruder)
        ->post(tenantRoute($other, 'panel.appointments.store'), [
            'service_id' => $this->service->id,
            'staff_profile_id' => $this->ana->id,
            'starts_at' => '2027-03-02T15:00',
            'name' => 'X',
            'email' => 'x@example.com',
        ])
        ->assertSessionHasErrors(['service_id', 'staff_profile_id']);
});
