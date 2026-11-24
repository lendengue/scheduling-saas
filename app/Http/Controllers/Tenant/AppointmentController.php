<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Appointments\BookAppointment;
use App\Actions\Appointments\CancelAppointment;
use App\Actions\Appointments\ChangeAppointmentStatus;
use App\Actions\Appointments\RescheduleAppointment;
use App\Enums\BookingChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointments\CancelAppointmentRequest;
use App\Http\Requests\Appointments\RescheduleAppointmentRequest;
use App\Http\Requests\Appointments\StoreAppointmentRequest;
use App\Http\Requests\Appointments\UpdateAppointmentStatusRequest;
use App\Models\Appointment;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Appointment changes made by the team from the agenda.
 */
class AppointmentController extends Controller
{
    public function store(StoreAppointmentRequest $request, BookAppointment $book): RedirectResponse
    {
        $book->handle($request->toBookingData());

        return $this->done(__('Appointment booked.'));
    }

    public function updateStatus(UpdateAppointmentStatusRequest $request, Appointment $appointment, ChangeAppointmentStatus $change): RedirectResponse
    {
        $appointment = $change->handle($appointment, $request->status());

        return $this->done(__('Appointment marked as :status.', ['status' => mb_strtolower($appointment->status->label())]));
    }

    public function cancel(CancelAppointmentRequest $request, Appointment $appointment, CancelAppointment $cancel): RedirectResponse
    {
        $cancel->handle($appointment, $request->reason(), byCustomer: false);

        return $this->done(__('Appointment cancelled.'));
    }

    public function reschedule(RescheduleAppointmentRequest $request, Appointment $appointment, RescheduleAppointment $reschedule): RedirectResponse
    {
        $reschedule->handle($appointment, $request->startsAt(), BookingChannel::Panel, $request->staffProfile());

        return $this->done(__('Appointment rescheduled.'));
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
