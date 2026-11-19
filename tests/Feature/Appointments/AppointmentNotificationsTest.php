<?php

use App\Actions\Appointments\BookAppointment;
use App\Actions\Appointments\CancelAppointment;
use App\Actions\Appointments\RescheduleAppointment;
use App\Data\Appointments\BookingData;
use App\Enums\BookingChannel;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Appointments\AppointmentBookedNotification;
use App\Notifications\Appointments\AppointmentCancelledNotification;
use App\Notifications\Appointments\AppointmentRescheduledNotification;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'timezone' => 'America/Sao_Paulo']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->travelTo(CarbonImmutable::parse('2027-03-01 08:00', 'America/Sao_Paulo'));
    $this->service = Service::factory()->duration(30)->create();
    $this->staff = StaffProfile::factory()->for(User::factory()->staff())->performing($this->service)->working('09:00', '12:00')->create();
    $this->booked = app(BookAppointment::class)->handle(new BookingData(
        $this->service, null, CarbonImmutable::parse('2027-03-03 09:00', 'America/Sao_Paulo'), 'Ana', 'ana@example.com', '+5511987654321',
    ));
});

afterEach(fn () => app(CurrentTenant::class)->forget());

test('customer and professional are emailed when an appointment is booked', function () {
    $appointment = $this->booked->appointment;

    Notification::assertSentTo($appointment->customer, AppointmentBookedNotification::class,
        fn ($n) => str_contains($n->manageUrl, "http://acme.scheduling-saas.test/appointments/{$appointment->id}/manage?token={$this->booked->manageToken}"));
    Notification::assertSentTo($this->staff->user, AppointmentBookedNotification::class);

    $mail = (new AppointmentBookedNotification($appointment, 'http://x'))->toMail($appointment->customer);
    expect($mail->rawAttachments)->toHaveCount(1);
});

test('cancellation and rescheduling notify both sides', function () {
    $appointment = $this->booked->appointment;

    app(RescheduleAppointment::class)->handle($appointment, CarbonImmutable::parse('2027-03-03 11:00', 'America/Sao_Paulo'), BookingChannel::Panel);
    Notification::assertSentTo($appointment->customer, AppointmentRescheduledNotification::class);
    Notification::assertSentTo($this->staff->user, AppointmentRescheduledNotification::class);

    app(CancelAppointment::class)->handle($appointment->fresh(), 'Closed', byCustomer: false);
    Notification::assertSentTo($appointment->customer, AppointmentCancelledNotification::class);
    Notification::assertSentTo($this->staff->user, AppointmentCancelledNotification::class);
});
