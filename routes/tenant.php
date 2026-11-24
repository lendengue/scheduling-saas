<?php

use App\Http\Controllers\Booking\AppointmentCalendarController;
use App\Http\Controllers\Booking\AvailabilityController;
use App\Http\Controllers\Booking\BookingController;
use App\Http\Controllers\Booking\BookingPageController;
use App\Http\Controllers\Booking\ManageAppointmentController;
use App\Http\Controllers\Tenant\AcceptInvitationController;
use App\Http\Controllers\Tenant\AgendaController;
use App\Http\Controllers\Tenant\AppointmentController;
use App\Http\Controllers\Tenant\InvitationController;
use App\Http\Controllers\Tenant\LoginHandoffController;
use App\Http\Controllers\Tenant\OnboardingController;
use App\Http\Controllers\Tenant\ServiceController;
use App\Http\Controllers\Tenant\TeamController;
use App\Http\Controllers\Tenant\TeamMemberController;
use App\Http\Controllers\Tenant\TimeOffController;
use App\Http\Controllers\Tenant\WorkingHoursController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Routes ({slug}.{APP_DOMAIN})
|--------------------------------------------------------------------------
*/

/*
| Public booking (no login): customers book, then manage their appointment
| through the secret link they receive.
*/

Route::get('book', BookingPageController::class)->name('booking.show');
Route::middleware('throttle:public-availability')->group(function () {
    Route::get('book/availability/days', [AvailabilityController::class, 'days'])->name('booking.availability.days');
    Route::get('book/availability/slots', [AvailabilityController::class, 'slots'])->name('booking.availability.slots');
});
Route::post('book', [BookingController::class, 'store'])
    ->middleware('throttle:public-booking')
    ->name('booking.store');
Route::get('book/confirmed/{appointment}', [BookingController::class, 'confirmed'])->name('booking.confirmed');
Route::get('appointments/{appointment}/calendar.ics', AppointmentCalendarController::class)
    ->middleware('throttle:30,1')
    ->name('booking.calendar');

Route::prefix('appointments/{appointment}')->middleware('throttle:60,1')->group(function () {
    Route::get('manage', [ManageAppointmentController::class, 'show'])->name('appointments.manage');
    Route::get('manage/days', [ManageAppointmentController::class, 'days'])->name('appointments.manage.days');
    Route::get('manage/slots', [ManageAppointmentController::class, 'slots'])->name('appointments.manage.slots');
    Route::post('cancel', [ManageAppointmentController::class, 'cancel'])->name('appointments.cancel');
    Route::post('reschedule', [ManageAppointmentController::class, 'reschedule'])->name('appointments.reschedule');
});

Route::get('auth/handoff/{token}', LoginHandoffController::class)
    ->middleware('throttle:10,1')
    ->name('login.handoff');

Route::middleware('guest')->group(function () {
    Route::get('invitations/{invitation}', [AcceptInvitationController::class, 'show'])
        ->middleware('signed:relative')
        ->name('invitations.show');
    Route::post('invitations/{invitation}', [AcceptInvitationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('invitations.accept');
});

Route::middleware(['auth', 'tenant.member'])->prefix('app')->group(function () {
    Route::inertia('/', 'Tenant/Dashboard')->name('dashboard');
    Route::get('onboarding', OnboardingController::class)->name('onboarding');

    Route::get('agenda', [AgendaController::class, 'index'])->name('agenda');
    Route::get('agenda/events', [AgendaController::class, 'events'])->name('agenda.events');
    Route::post('appointments', [AppointmentController::class, 'store'])->name('panel.appointments.store');
    Route::put('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('panel.appointments.status');
    Route::put('appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])->name('panel.appointments.reschedule');
    Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('panel.appointments.cancel');

    Route::get('team', [TeamController::class, 'index'])->name('team.index');
    Route::post('team/invitations', [InvitationController::class, 'store'])->name('team.invitations.store');
    Route::delete('team/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('team.invitations.destroy');

    Route::get('team/{member}', [TeamMemberController::class, 'show'])->name('team.members.show');
    Route::put('team/{member}', [TeamMemberController::class, 'update'])->name('team.members.update');
    Route::put('team/{member}/working-hours', [WorkingHoursController::class, 'update'])->name('team.members.working-hours.update');
    Route::post('team/{member}/time-offs', [TimeOffController::class, 'store'])->name('team.members.time-offs.store');
    Route::delete('team/{member}/time-offs/{timeOff}', [TimeOffController::class, 'destroy'])->name('team.members.time-offs.destroy');

    Route::put('services/order', [ServiceController::class, 'reorder'])->name('services.reorder');
    Route::resource('services', ServiceController::class)->only(['index', 'store', 'update', 'destroy']);

    require __DIR__.'/settings.php';
});
