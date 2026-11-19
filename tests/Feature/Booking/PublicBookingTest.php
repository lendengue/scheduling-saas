<?php

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Booking\BookingController;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'slug' => 'barber',
        'name' => 'Barber Co',
        'timezone' => 'America/Sao_Paulo',
        'brand_color' => '#112233',
    ]);
    $this->travelTo(CarbonImmutable::parse('2027-03-01 08:00', 'America/Sao_Paulo'));

    app(CurrentTenant::class)->runAs($this->tenant, function () {
        $this->service = Service::factory()->duration(30)->create(['name' => 'Haircut', 'price_cents' => 5000]);
        Service::factory()->inactive()->create(['name' => 'Hidden']);
        $this->staff = StaffProfile::factory()
            ->for(User::factory()->staff()->state(['name' => 'Bruno']))
            ->performing($this->service)
            ->working('09:00', '12:00', [1, 2, 3, 4, 5])
            ->create();
    });
});

function bookingPayload(array $overrides = []): array
{
    return [
        'service_id' => test()->service->id,
        'staff_profile_id' => null,
        'starts_at' => '2027-03-02T13:00:00Z', // 10:00 in São Paulo
        'name' => 'Ana Souza',
        'email' => 'Ana@Example.com',
        'phone' => '(11) 98765-4321',
        'notes' => 'First time',
        'website' => '',
        ...$overrides,
    ];
}

test('the booking page lists active services and bookable professionals', function () {
    $this->get(tenantRoute($this->tenant, 'booking.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Booking/Show')
            ->where('business.name', 'Barber Co')
            ->where('business.brand_color', '#112233')
            ->has('services', 1)
            ->where('services.0.name', 'Haircut')
            ->where('services.0.price_formatted', 'R$50.00')
            ->where('services.0.staff_profile_ids', [$this->staff->id])
            ->has('staff', 1)
            ->where('staff.0.name', 'Bruno')
            ->where('today', '2027-03-01')
            ->where('maxDate', '2027-04-30'),
        );
});

test('available days and slots are served as JSON', function () {
    $query = ['service_id' => $this->service->id, 'month' => '2027-03'];

    $this->getJson(tenantRoute($this->tenant, 'booking.availability.days', $query))
        ->assertOk()
        ->assertJsonPath('days.0', '2027-03-01')
        ->assertJsonPath('days.1', '2027-03-02')
        ->assertJsonMissing(['2027-03-06']);

    $this->getJson(tenantRoute($this->tenant, 'booking.availability.slots', [
        'service_id' => $this->service->id,
        'staff_profile_id' => $this->staff->id,
        'date' => '2027-03-02',
    ]))
        ->assertOk()
        ->assertJsonCount(11, 'slots')
        ->assertJsonPath('slots.0', ['starts_at' => '2027-03-02T12:00:00Z', 'label' => '09:00']);
});

test('availability of hidden or foreign services is not disclosed', function () {
    $foreign = Service::factory()->create();

    $this->getJson(tenantRoute($this->tenant, 'booking.availability.slots', ['service_id' => $foreign->id, 'date' => '2027-03-02']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('service_id');
});

test('a customer books and lands on the confirmation page', function () {
    $response = $this->post(tenantRoute($this->tenant, 'booking.store'), bookingPayload());

    $appointment = Appointment::withoutGlobalScopes()->sole();
    $response->assertRedirect(tenantRoute($this->tenant, 'booking.confirmed', ['appointment' => $appointment->id]));

    $customer = Customer::withoutGlobalScopes()->sole();
    expect($appointment->tenant_id)->toBe($this->tenant->id)
        ->and($appointment->status)->toBe(AppointmentStatus::Confirmed)
        ->and($appointment->staff_profile_id)->toBe($this->staff->id)
        ->and($customer->email)->toBe('ana@example.com')
        ->and($customer->phone)->toBe('+5511987654321');

    $token = session(BookingController::SESSION_KEY.'.'.$appointment->id);

    $this->get(tenantRoute($this->tenant, 'booking.confirmed', ['appointment' => $appointment->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Booking/Confirmed')
            ->where('appointment.service', 'Haircut')
            ->where('appointment.staff', 'Bruno')
            ->where('appointment.time', '10:00')
            ->where('appointment.ends_at_time', '10:30')
            ->where('isPending', false)
            ->where('calendarUrl', "/appointments/{$appointment->id}/calendar.ics?token={$token}"),
        );
});

test('the confirmation page is only shown to the browser that booked', function () {
    $appointment = app(CurrentTenant::class)->runAs($this->tenant, fn () => Appointment::factory()->for($this->staff)->create());

    $this->get(tenantRoute($this->tenant, 'booking.confirmed', ['appointment' => $appointment->id]))
        ->assertNotFound();
});

test('the appointment can be downloaded as an iCalendar file', function () {
    $this->post(tenantRoute($this->tenant, 'booking.store'), bookingPayload());
    $appointment = Appointment::withoutGlobalScopes()->sole();
    $token = session(BookingController::SESSION_KEY.'.'.$appointment->id);

    $response = $this->get(tenantRoute($this->tenant, 'booking.calendar', ['appointment' => $appointment->id, 'token' => $token]));

    $response->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    expect($response->getContent())
        ->toContain("BEGIN:VEVENT\r\n")
        ->toContain("DTSTART:20270302T130000Z\r\n")
        ->toContain("DTEND:20270302T133000Z\r\n")
        ->toContain('SUMMARY:Haircut at Barber Co')
        ->toContain("UID:{$appointment->id}@scheduling-saas");

    $this->get(tenantRoute($this->tenant, 'booking.calendar', ['appointment' => $appointment->id, 'token' => 'wrong']))
        ->assertNotFound();
});

test('a time taken in the meantime sends the customer back with a friendly error', function () {
    $this->post(tenantRoute($this->tenant, 'booking.store'), bookingPayload());

    $this->from(tenantRoute($this->tenant, 'booking.show'))
        ->post(tenantRoute($this->tenant, 'booking.store'), bookingPayload(['email' => 'bob@example.com']))
        ->assertRedirect(tenantRoute($this->tenant, 'booking.show'))
        ->assertSessionHasErrors(['starts_at' => 'Sorry, this time is no longer available. Please choose another one.']);

    expect(Appointment::withoutGlobalScopes()->count())->toBe(1);
});

test('API clients get 409 Conflict when the time is taken', function () {
    $this->post(tenantRoute($this->tenant, 'booking.store'), bookingPayload());

    $this->postJson(tenantRoute($this->tenant, 'booking.store'), bookingPayload(['email' => 'bob@example.com']))
        ->assertStatus(409)
        ->assertJsonPath('message', 'Sorry, this time is no longer available. Please choose another one.');
});

test('booking input is validated', function (array $override, string $field) {
    $override = array_map(fn ($value) => $value instanceof Closure ? $value() : $value, $override);

    $this->post(tenantRoute($this->tenant, 'booking.store'), bookingPayload($override))
        ->assertSessionHasErrors($field);

    expect(Appointment::withoutGlobalScopes()->count())->toBe(0);
})->with([
    'invalid phone' => [['phone' => '1234'], 'phone'],
    'missing phone' => [['phone' => ''], 'phone'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'missing name' => [['name' => ''], 'name'],
    'inactive service' => [['service_id' => fn () => Service::withoutGlobalScopes()->where('name', 'Hidden')->value('id')], 'service_id'],
    'foreign service' => [['service_id' => fn () => Service::factory()->create()->id], 'service_id'],
    'honeypot filled' => [['website' => 'https://spam.example'], 'website'],
]);

test('public bookings are rate limited per IP', function () {
    config(['scheduling.public_bookings_per_hour' => 2]);

    foreach (['09:00', '09:30'] as $index => $time) {
        $this->post(tenantRoute($this->tenant, 'booking.store'), bookingPayload([
            'starts_at' => CarbonImmutable::parse("2027-03-02 {$time}", 'America/Sao_Paulo')->toIso8601ZuluString(),
            'email' => "customer{$index}@example.com",
        ]))->assertRedirect();
    }

    $this->post(tenantRoute($this->tenant, 'booking.store'), bookingPayload())->assertTooManyRequests();
});

test('the booking page of an unknown tenant does not exist', function () {
    $this->get('http://nope.scheduling-saas.test/book')->assertNotFound();
});
