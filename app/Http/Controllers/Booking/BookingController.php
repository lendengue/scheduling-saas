<?php

namespace App\Http\Controllers\Booking;

use App\Actions\Appointments\BookAppointment;
use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Models\Appointment;
use App\Support\Booking\AppointmentSummary;
use App\Support\Booking\PublicBusiness;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    /**
     * Session key holding the management tokens of the appointments booked
     * in this browser session, so the confirmation page can show the links.
     */
    public const string SESSION_KEY = 'booking.tokens';

    public function store(StoreBookingRequest $request, BookAppointment $book): RedirectResponse
    {
        $booked = $book->handle($request->toBookingData());

        $request->session()->put(self::SESSION_KEY.'.'.$booked->appointment->id, $booked->manageToken);

        return redirect()->route('booking.confirmed', ['appointment' => $booked->appointment->id]);
    }

    public function confirmed(Request $request, Appointment $appointment, CurrentTenant $currentTenant): Response
    {
        $token = $request->session()->get(self::SESSION_KEY.'.'.$appointment->id);

        // Only the browser that made the booking sees this page.
        abort_unless(is_string($token) && $appointment->matchesManageToken($token), 404);

        $tenant = $currentTenant->getOrFail();

        return Inertia::render('Booking/Confirmed', [
            'business' => PublicBusiness::from($tenant),
            'appointment' => AppointmentSummary::from($appointment, $tenant),
            'isPending' => $appointment->status === AppointmentStatus::Pending,
            'calendarUrl' => route('booking.calendar', ['appointment' => $appointment->id, 'token' => $token], absolute: false),
            'manageUrl' => route('appointments.manage', ['appointment' => $appointment->id, 'token' => $token], absolute: false),
        ]);
    }
}
